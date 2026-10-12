<?php

use Boi\Backend\Enums\StatusChangeRequestStatus;
use Boi\Backend\Events\ReturnApproved;
use Boi\Backend\Events\ReturnRejected;
use Boi\Backend\Events\ReturnRequested;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Exceptions\UnauthorizedStatusChangeException;
use Boi\Backend\Models\Concerns\GuardsDeclinedStatus;
use Boi\Backend\Models\Concerns\HasStatusChangeRequests;
use Boi\Backend\Models\StatusChangeRequest;
use Boi\Backend\Services\ReturnApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class ReturnTestUser extends AuthUser
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

class ReturnTestApplication extends Model
{
    use GuardsDeclinedStatus;
    use HasStatusChangeRequests;

    protected $table = 'applications';

    protected $guarded = [];
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

    config()->set('auth.providers.users.model', ReturnTestUser::class);
    config()->set('boi_backend.return_approval.maker_roles', ['Monitor']);
    config()->set('boi_backend.return_approval.checker_roles', ['Super Admin']);
    Queue::fake();
    Event::fake([ReturnRequested::class, ReturnApproved::class, ReturnRejected::class]);

    $this->monitor = ReturnTestUser::create(['name' => 'Monitor', 'role' => 'Monitor', 'permissions' => ['return_request']]);
    $this->super = ReturnTestUser::create(['name' => 'Super', 'role' => 'Super Admin', 'permissions' => ['return_approve']]);
    $this->super2 = ReturnTestUser::create(['name' => 'Super 2', 'role' => 'Super Admin', 'permissions' => ['return_approve']]);
    $this->officer = ReturnTestUser::create(['name' => 'PO', 'role' => 'Project Officer', 'permissions' => ['return_request', 'return_approve']]);

    $this->submitted = ReturnTestApplication::create([
        'status' => 'submitted',
        'internal_status' => 'submitted',
        'project_officer_id' => 99,
    ]);

    $this->service = app(ReturnApprovalService::class);
});

function requestReturn($test, ?ReturnTestApplication $application = null, $maker = null): StatusChangeRequest
{
    return $test->service->request(
        $application ?? $test->submitted,
        $maker ?? $test->monitor,
        'Missing bank statement.',
        'Additional Documents Required',
        'Please upload your 6-month statement.',
    );
}

it('lets a Monitor raise a pending return without returning the application yet', function () {
    $request = requestReturn($this);

    expect($request->status)->toBe(StatusChangeRequestStatus::PENDING)
        ->and($request->type)->toBe(StatusChangeRequest::TYPE_RETURN)
        ->and($request->to_status)->toBe('returned');

    $this->submitted->refresh();
    expect($this->submitted->internal_status)->toBe('submitted'); // not yet returned
    Event::assertDispatched(ReturnRequested::class);
});

it('refuses a return request from a non-maker', function () {
    expect(fn () => requestReturn($this, maker: $this->officer))
        ->toThrow(UnauthorizedStatusChangeException::class);
});

it('returns the application when a different Super Admin approves', function () {
    $request = requestReturn($this);

    $this->service->approve($request, $this->super, 'Looks right.');

    $this->submitted->refresh();
    expect($this->submitted->internal_status)->toBe('returned')
        ->and($this->submitted->rejection_reason)->toBe('Additional Documents Required')
        ->and($this->submitted->project_officer_comments)->toBe('Please upload your 6-month statement.');

    expect($request->fresh()->status)->toBe(StatusChangeRequestStatus::APPROVED);
    Event::assertDispatched(ReturnApproved::class);
});

it('forbids the requester from approving their own return', function () {
    // One user who is both maker and checker: a Super Admin allowed to request
    // (for this test only) still cannot review the request they raised.
    config()->set('boi_backend.return_approval.maker_roles', ['Monitor', 'Super Admin']);
    $this->super->update(['permissions' => ['return_request', 'return_approve']]);
    $request = requestReturn($this, maker: $this->super);

    expect(fn () => $this->service->approve($request, $this->super))
        ->toThrow(UnauthorizedStatusChangeException::class);
});

it('leaves the application unchanged when the return is rejected', function () {
    $request = requestReturn($this);

    $this->service->reject($request, $this->super, 'Keep it under review.');

    $this->submitted->refresh();
    expect($this->submitted->internal_status)->toBe('submitted');
    expect($request->fresh()->status)->toBe(StatusChangeRequestStatus::REJECTED);
    Event::assertDispatched(ReturnRejected::class);
});

it('will not return a declined application (that needs a decline reversal)', function () {
    $declined = ReturnTestApplication::create(['status' => 'declined', 'internal_status' => 'declined']);

    expect(fn () => requestReturn($this, $declined))
        ->toThrow(StatusChangeRequestException::class);
});

it('will not return an approved or plain-incomplete application', function () {
    $approved = ReturnTestApplication::create(['status' => 'approved', 'internal_status' => 'approved']);
    $incomplete = ReturnTestApplication::create(['status' => 'incomplete', 'internal_status' => 'incomplete']);

    expect(fn () => requestReturn($this, $approved))->toThrow(StatusChangeRequestException::class);
    expect(fn () => requestReturn($this, $incomplete))->toThrow(StatusChangeRequestException::class);
});

it('allows only one pending return per application', function () {
    requestReturn($this);

    expect(fn () => requestReturn($this))->toThrow(StatusChangeRequestException::class);
});

it('cannot approve a request that is no longer pending', function () {
    $request = requestReturn($this);
    $this->service->approve($request, $this->super);

    expect(fn () => $this->service->approve($request->fresh(), $this->super2))
        ->toThrow(StatusChangeRequestException::class);
});
