<?php

use Boi\Backend\Enums\StatusChangeRequestStatus;
use Boi\Backend\Events\DeclineReversalApproved;
use Boi\Backend\Events\DeclineReversalRejected;
use Boi\Backend\Events\DeclineReversalRequested;
use Boi\Backend\Exceptions\DeclinedApplicationLockedException;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Exceptions\UnauthorizedStatusChangeException;
use Boi\Backend\Jobs\LogAction;
use Boi\Backend\Models\Concerns\GuardsDeclinedStatus;
use Boi\Backend\Models\Concerns\HasStatusChangeRequests;
use Boi\Backend\Models\StatusChangeRequest;
use Boi\Backend\Services\DeclineReversalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class ReversalTestUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = ['permissions' => 'array'];

    public function hasRoleName(string|array $names): bool
    {
        return in_array($this->role, (array) $names, true);
    }

    public function hasPermissionTo(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}

class ReversalTestApplication extends Model
{
    use GuardsDeclinedStatus;
    use HasStatusChangeRequests;

    protected $table = 'applications';

    protected $guarded = [];
}

class ConflictCheckingTestApplication extends ReversalTestApplication implements \Boi\Backend\Contracts\ChecksReopenConflicts
{
    public static ?string $conflict = null;

    public static int $saves = 0;

    protected static function booted(): void
    {
        static::updating(fn () => static::$saves++);
    }

    public function reopenConflict(): ?string
    {
        return static::$conflict;
    }
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role')->nullable();
        $table->json('permissions')->nullable();
        $table->timestamps();
    });
    Schema::create('applications', function (Blueprint $table) {
        $table->id();
        $table->string('status');
        $table->string('internal_status')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->text('project_officer_comments')->nullable();
        $table->unsignedBigInteger('project_officer_id')->nullable();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', ReversalTestUser::class);
    Queue::fake();
    Event::fake([DeclineReversalRequested::class, DeclineReversalApproved::class, DeclineReversalRejected::class]);

    $this->admin = ReversalTestUser::create(['name' => 'Admin', 'role' => 'Admin', 'permissions' => ['decline_reversal_request']]);
    $this->admin2 = ReversalTestUser::create(['name' => 'Admin 2', 'role' => 'Admin', 'permissions' => ['decline_reversal_request']]);
    $this->super = ReversalTestUser::create(['name' => 'Super', 'role' => 'Super Admin', 'permissions' => ['decline_reversal_approve']]);
    $this->super2 = ReversalTestUser::create(['name' => 'Super 2', 'role' => 'Super Admin', 'permissions' => ['decline_reversal_approve']]);
    $this->officer = ReversalTestUser::create(['name' => 'PO', 'role' => 'Project Officer', 'permissions' => ['decline_reversal_request', 'decline_reversal_approve']]);

    $this->declined = ReversalTestApplication::create([
        'status' => 'declined',
        'internal_status' => 'declined',
        'rejection_reason' => 'Original decline reason',
        'project_officer_comments' => '<p>Original decline reason</p>',
        'project_officer_id' => 99,
    ]);

    $this->service = app(DeclineReversalService::class);

    ConflictCheckingTestApplication::$conflict = null;
    ConflictCheckingTestApplication::$saves = 0;
});

function requestReversal($test, ?ReversalTestApplication $application = null, $maker = null): StatusChangeRequest
{
    return $test->service->request(
        $application ?? $test->declined,
        $maker ?? $test->admin,
        'Declined in error — documents were on file.',
        'Additional Documents Required',
        'Please upload your CAC certificate.',
    );
}

// ── Maker ────────────────────────────────────────────────────────────────

it('creates a pending request and leaves the application declined', function () {
    $request = requestReversal($this);

    expect($request->status)->toBe(StatusChangeRequestStatus::PENDING)
        ->and($request->type)->toBe(StatusChangeRequest::TYPE_DECLINE_REVERSAL)
        ->and($request->from_status)->toBe('declined')
        ->and($request->to_status)->toBe('returned')
        ->and($request->requested_by)->toBe($this->admin->id)
        ->and($request->reason)->toBe('Declined in error — documents were on file.')
        ->and($request->payload)->toBe(['rejection_reason' => 'Additional Documents Required', 'comments' => 'Please upload your CAC certificate.'])
        ->and($request->snapshot['status'])->toBe('declined')
        ->and($request->snapshot['internal_status'])->toBe('declined')
        ->and($request->snapshot['rejection_reason'])->toBe('Original decline reason')
        ->and($request->snapshot['project_officer_id'])->toBe(99);

    $fresh = $this->declined->fresh();
    expect($fresh->status)->toBe('declined')
        ->and($fresh->internal_status)->toBe('declined')
        ->and($fresh->rejection_reason)->toBe('Original decline reason');

    Event::assertDispatched(DeclineReversalRequested::class, fn ($e) => $e->request->is($request));
    Queue::assertPushed(LogAction::class);
});

it('refuses makers without the role or the permission', function ($who) {
    $user = match ($who) {
        'super admin' => $this->super,
        'project officer' => $this->officer,
        'admin without permission' => ReversalTestUser::create(['name' => 'x', 'role' => 'Admin', 'permissions' => []]),
        'guest' => null,
    };

    expect(fn () => $this->service->request($this->declined, $user, 'why', 'Other'))
        ->toThrow(UnauthorizedStatusChangeException::class);
    expect(StatusChangeRequest::count())->toBe(0);
})->with(['super admin', 'project officer', 'admin without permission', 'guest']);

it('only accepts declined applications (not stepped down, not other states)', function (string $status, string $internal) {
    $application = ReversalTestApplication::create(['status' => $status, 'internal_status' => $internal]);

    expect(fn () => requestReversal($this, $application))->toThrow(StatusChangeRequestException::class);
    expect(StatusChangeRequest::count())->toBe(0);
})->with([
    'stepped down' => ['declined', 'stepped_down'],
    'submitted' => ['submitted', 'pending'],
    'incomplete' => ['incomplete', 'returned'],
    'approved' => ['approved', 'approved'],
]);

it('allows only one pending request per application', function () {
    requestReversal($this);

    expect(fn () => requestReversal($this, maker: $this->admin2))
        ->toThrow(StatusChangeRequestException::class, 'already pending');
    expect(StatusChangeRequest::count())->toBe(1);
});

it('enforces the single pending request in the database itself', function () {
    $first = requestReversal($this);

    // A second insert that skipped the service check (a racing maker) must
    // be stopped by the unique pending_key.
    expect(fn () => DB::table('status_change_requests')->insert([
        'requestable_type' => $first->requestable_type,
        'requestable_id' => $first->requestable_id,
        'type' => $first->type,
        'status' => 'pending',
        'from_status' => 'declined',
        'to_status' => 'returned',
        'reason' => 'race',
        'requested_by' => $this->admin2->id,
        'pending_key' => $first->pending_key,
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('requires a justification and a return reason', function () {
    expect(fn () => $this->service->request($this->declined, $this->admin, '  ', 'Other'))
        ->toThrow(StatusChangeRequestException::class);
    expect(fn () => $this->service->request($this->declined, $this->admin, 'why', ''))
        ->toThrow(StatusChangeRequestException::class);
});

// ── Checker: approve ─────────────────────────────────────────────────────

it('approves: declined → returned with the requested return reason', function () {
    $request = requestReversal($this);

    $approved = $this->service->approve($request, $this->super, 'Checked the file.');

    expect($approved->status)->toBe(StatusChangeRequestStatus::APPROVED)
        ->and($approved->reviewed_by)->toBe($this->super->id)
        ->and($approved->reviewed_at)->not->toBeNull()
        ->and($approved->review_comment)->toBe('Checked the file.')
        ->and($approved->pending_key)->toBeNull();

    $fresh = $this->declined->fresh();
    expect($fresh->internal_status)->toBe('returned')
        ->and($fresh->rejection_reason)->toBe('Additional Documents Required')
        ->and($fresh->project_officer_comments)->toBe('Please upload your CAC certificate.')
        // The package does not reopen status itself — the app observer does
        // (status → incomplete) on internal_status = returned.
        ->and($fresh->status)->toBe('declined');

    Event::assertDispatched(DeclineReversalApproved::class);
});

it('refuses approval by anyone but an authorised checker', function ($who) {
    $request = requestReversal($this);
    $user = match ($who) {
        'admin' => $this->admin2,
        'project officer with both permissions' => $this->officer,
        'super admin without permission' => ReversalTestUser::create(['name' => 'x', 'role' => 'Super Admin', 'permissions' => []]),
        'guest' => null,
    };

    expect(fn () => $this->service->approve($request, $user))->toThrow(UnauthorizedStatusChangeException::class);
    expect($request->fresh()->status)->toBe('pending')
        ->and($this->declined->fresh()->internal_status)->toBe('declined');
})->with(['admin', 'project officer with both permissions', 'super admin without permission', 'guest']);

it('blocks self-approval even when the maker is also a checker', function () {
    // Widen the maker rule so a Super Admin may raise requests, then make
    // sure they still cannot approve (or reject) their own.
    config()->set('boi_backend.decline_reversal.maker_roles', ['Admin', 'Super Admin']);
    $this->super->update(['permissions' => ['decline_reversal_request', 'decline_reversal_approve']]);

    $request = requestReversal($this, maker: $this->super);

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(UnauthorizedStatusChangeException::class, 'you requested');
    expect(fn () => $this->service->reject($request, $this->super, 'no'))
        ->toThrow(UnauthorizedStatusChangeException::class, 'you requested');

    expect($request->fresh()->status)->toBe('pending');

    $this->service->approve($request, $this->super2);
    expect($request->fresh()->status)->toBe('approved');
});

it('cannot approve the same request twice (replay)', function () {
    $request = requestReversal($this);
    $this->service->approve($request, $this->super);

    expect(fn () => $this->service->approve($request, $this->super2))
        ->toThrow(StatusChangeRequestException::class, 'already been processed');
    expect($request->fresh()->reviewed_by)->toBe($this->super->id);
});

it('cannot reject an approved request, nor approve a rejected one', function () {
    $approvedReq = requestReversal($this);
    $this->service->approve($approvedReq, $this->super);
    expect(fn () => $this->service->reject($approvedReq, $this->super2, 'late'))
        ->toThrow(StatusChangeRequestException::class);
    expect($approvedReq->fresh()->status)->toBe('approved');

    $other = ReversalTestApplication::create(['status' => 'declined', 'internal_status' => 'declined']);
    $rejectedReq = requestReversal($this, $other);
    $this->service->reject($rejectedReq, $this->super, 'Decline stands.');
    expect(fn () => $this->service->approve($rejectedReq, $this->super2))
        ->toThrow(StatusChangeRequestException::class);
    expect($other->fresh()->status)->toBe('declined')
        ->and($other->fresh()->internal_status)->toBe('declined');
});

it('stale copies of a request cannot both be approved', function () {
    $request = requestReversal($this);
    $copyA = StatusChangeRequest::find($request->id);
    $copyB = StatusChangeRequest::find($request->id);

    $this->service->approve($copyA, $this->super);

    // copyB still says "pending" in memory — the service re-reads under lock.
    expect($copyB->status)->toBe('pending');
    expect(fn () => $this->service->approve($copyB, $this->super2))
        ->toThrow(StatusChangeRequestException::class);

    expect(StatusChangeRequest::where('status', 'approved')->count())->toBe(1)
        ->and($request->fresh()->reviewed_by)->toBe($this->super->id);
});

it('the compare-and-swap claim stops a concurrent winner even without row locks', function () {
    $request = requestReversal($this);

    // Simulate a second checker committing between our locked read and our
    // claim (what happens on a database where FOR UPDATE is a no-op).
    $racing = new class(app(\Boi\Backend\Support\DeclineReversalAuthorizer::class)) extends DeclineReversalService
    {
        // Last check before the claim: a rival commits here.
        protected function assertNoReopenConflict(Model $application): void
        {
            parent::assertNoReopenConflict($application);
            DB::table('status_change_requests')->where('requestable_id', $application->getKey())
                ->update(['status' => 'rejected', 'pending_key' => null]);
        }
    };

    expect(fn () => $racing->approve($request, $this->super))
        ->toThrow(StatusChangeRequestException::class, 'already been processed');

    // The whole approval rolled back: the application was never reopened.
    expect($this->declined->fresh()->internal_status)->toBe('declined')
        ->and($this->declined->fresh()->status)->toBe('declined');
    Event::assertNotDispatched(DeclineReversalApproved::class);
});

it('refuses approval when the application left the requested declined state', function () {
    $request = requestReversal($this);

    // e.g. a data fix moved it on directly in the database
    DB::table('applications')->where('id', $this->declined->id)->update(['status' => 'approved', 'internal_status' => 'approved']);

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(StatusChangeRequestException::class, 'no longer in the declined state');
    expect($request->fresh()->status)->toBe('pending');
});

it('refuses a request whose recorded transition is not declined → returned', function () {
    $request = requestReversal($this);
    DB::table('status_change_requests')->where('id', $request->id)->update(['to_status' => 'approved']);

    expect(fn () => $this->service->approve($request->fresh(), $this->super))
        ->toThrow(StatusChangeRequestException::class);
    expect($this->declined->fresh()->internal_status)->toBe('declined');
});

it('turns an app-level unique-constraint clash on reopen into a clean, rolled-back refusal', function () {
    // Mirrors GLOW/ADF's "one incomplete application per applicant" partial
    // unique index: here, one returned application per project officer.
    DB::statement("CREATE UNIQUE INDEX one_returned_per_po ON applications (project_officer_id) WHERE internal_status = 'returned'");
    ReversalTestApplication::create(['status' => 'incomplete', 'internal_status' => 'returned', 'project_officer_id' => 99]);

    $request = requestReversal($this);

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(StatusChangeRequestException::class, 'Nothing was changed; the request is still pending.');

    expect($request->fresh()->status)->toBe('pending')
        ->and($request->fresh()->reviewed_by)->toBeNull();
    $fresh = $this->declined->fresh();
    expect($fresh->status)->toBe('declined')
        ->and($fresh->internal_status)->toBe('declined')
        ->and($fresh->rejection_reason)->toBe('Original decline reason');
    Event::assertNotDispatched(DeclineReversalApproved::class);

    // Once the clash is resolved the same request can still be approved.
    DB::statement('DROP INDEX one_returned_per_po');
    $this->service->approve($request, $this->super);
    expect($request->fresh()->status)->toBe('approved');
});

it('refuses a request when the app reports a reopen conflict', function () {
    ConflictCheckingTestApplication::$conflict = 'The applicant has already started a new application.';
    $application = ConflictCheckingTestApplication::find($this->declined->id);

    expect(fn () => requestReversal($this, $application))
        ->toThrow(StatusChangeRequestException::class, 'already started a new application');
    expect(StatusChangeRequest::count())->toBe(0);
});

it('checks the app reopen conflict at approval time, before saving anything', function () {
    $request = requestReversal($this, ConflictCheckingTestApplication::find($this->declined->id));

    // The conflict appears after the request was raised.
    ConflictCheckingTestApplication::$conflict = 'The applicant has already started a new application.';

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(StatusChangeRequestException::class, 'already started a new application');

    // No save was attempted, so no app observer could have notified anyone.
    expect(ConflictCheckingTestApplication::$saves)->toBe(0)
        ->and($request->fresh()->status)->toBe('pending')
        ->and($this->declined->fresh()->internal_status)->toBe('declined');

    ConflictCheckingTestApplication::$conflict = null;
    $this->service->approve($request, $this->super);
    expect(ConflictCheckingTestApplication::$saves)->toBe(1)
        ->and($this->declined->fresh()->internal_status)->toBe('returned');
});

it('fails cleanly if the application was deleted', function () {
    $request = requestReversal($this);
    DB::table('applications')->where('id', $this->declined->id)->delete();

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(StatusChangeRequestException::class, 'no longer exists');
    expect($request->fresh()->status)->toBe('pending');
});

// ── Checker: reject ──────────────────────────────────────────────────────

it('rejects: request closed, application stays declined', function () {
    $request = requestReversal($this);

    $rejected = $this->service->reject($request, $this->super, 'The decline stands.');

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->review_comment)->toBe('The decline stands.')
        ->and($rejected->reviewed_by)->toBe($this->super->id)
        ->and($rejected->pending_key)->toBeNull();

    $fresh = $this->declined->fresh();
    expect($fresh->status)->toBe('declined')
        ->and($fresh->internal_status)->toBe('declined')
        ->and($fresh->rejection_reason)->toBe('Original decline reason');

    Event::assertDispatched(DeclineReversalRejected::class);
});

it('requires a reason to reject and only lets checkers reject', function () {
    $request = requestReversal($this);

    expect(fn () => $this->service->reject($request, $this->super, ' '))->toThrow(StatusChangeRequestException::class);
    expect(fn () => $this->service->reject($request, $this->admin2, 'no'))->toThrow(UnauthorizedStatusChangeException::class);
    expect($request->fresh()->status)->toBe('pending');
});

it('allows a fresh request after a rejection', function () {
    $this->service->reject(requestReversal($this), $this->super, 'Not yet.');

    $second = requestReversal($this, maker: $this->admin2);

    expect($second->status)->toBe('pending')
        ->and(StatusChangeRequest::count())->toBe(2);
});

// ── Model guard ──────────────────────────────────────────────────────────

it('blocks any direct save that reopens a declined application', function (array $change) {
    expect(fn () => $this->declined->update($change))->toThrow(DeclinedApplicationLockedException::class);

    $fresh = $this->declined->fresh();
    expect($fresh->status)->toBe('declined')->and($fresh->internal_status)->toBe('declined');
})->with([
    'internal_status → returned' => [['internal_status' => 'returned']],
    'status → incomplete' => [['status' => 'incomplete']],
    'status → submitted' => [['status' => 'submitted']],
    'both' => [['status' => 'incomplete', 'internal_status' => 'returned']],
]);

it('still blocks a direct reopen while a request is pending', function () {
    requestReversal($this);

    expect(fn () => $this->declined->fresh()->update(['internal_status' => 'returned']))
        ->toThrow(DeclinedApplicationLockedException::class);
});

it('does not interfere with saves that keep a declined application declined', function () {
    $this->declined->update(['project_officer_comments' => 'note added later']);

    expect($this->declined->fresh()->project_officer_comments)->toBe('note added later');
});

it('does not interfere with returning applications that were never declined', function () {
    $submitted = ReversalTestApplication::create(['status' => 'submitted', 'internal_status' => 'pending']);

    $submitted->update(['internal_status' => 'returned', 'status' => 'incomplete']);

    expect($submitted->fresh()->internal_status)->toBe('returned');
});

it('exposes the request history on the application', function () {
    $request = requestReversal($this);

    expect($this->declined->statusChangeRequests()->pending()->sole()->is($request))->toBeTrue()
        ->and($request->requestable->is($this->declined))->toBeTrue()
        ->and($request->requester->is($this->admin))->toBeTrue();

    $this->service->approve($request, $this->super);
    expect($this->declined->statusChangeRequests()->pending()->exists())->toBeFalse()
        ->and($request->fresh()->reviewer->is($this->super))->toBeTrue();
});

it('fails closed when a permission is not configured', function () {
    config()->set('boi_backend.decline_reversal.maker_permission', null);
    config()->set('boi_backend.decline_reversal.checker_permission', '');

    expect(fn () => requestReversal($this))->toThrow(UnauthorizedStatusChangeException::class);

    config()->set('boi_backend.decline_reversal.maker_permission', 'decline_reversal_request');
    $request = requestReversal($this);
    expect(fn () => $this->service->approve($request, $this->super))->toThrow(UnauthorizedStatusChangeException::class);
});

it('cannot be approved by mass assignment', function () {
    $request = requestReversal($this);

    expect(fn () => $request->fill(['status' => 'approved']))
        ->toThrow(\Illuminate\Database\Eloquent\MassAssignmentException::class);
});
