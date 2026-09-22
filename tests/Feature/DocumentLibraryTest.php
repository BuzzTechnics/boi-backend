<?php

/**
 * The shared Document Library engine: a fund-agnostic version of the customer
 * document exchange (request → upload → completeness gate → submit → review →
 * return → complete). The fund-specific piece is only the owner resolver.
 */

use Boi\Backend\DocumentLibrary\DocumentLibraryService;
use Boi\Backend\DocumentLibrary\Models\Document;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Boi\Backend\DocumentLibrary\Models\DocumentVersion;
use Boi\Backend\DocumentLibrary\Notifications\DocumentLibraryNotification;
use Boi\Backend\Tests\Support\FakeCase;
use Boi\Backend\Tests\Support\FakeOwnerResolver;
use Boi\Backend\Tests\Support\FakeUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('users', function ($t) {
        $t->id();
        $t->string('name');
        $t->string('email');
    });
    Schema::create('cases', function ($t) {
        $t->id();
        $t->string('workflow_id');
        $t->unsignedBigInteger('user_id');
    });

    config([
        'boi_document_library.app' => 'testfund',
        'boi_document_library.resolver' => FakeOwnerResolver::class,
        'boi_document_library.webhook_key' => 'k',
        'boi_document_library.submit_url' => 'https://wf.example.test/submit',
        'boi_document_library.stages' => [
            'disbursement_docs' => [
                'label' => 'Disbursement Documentation',
                'documents' => [
                    ['reference' => 'offer', 'name' => 'Offer Letter', 'mandatory' => true, 'permittedFormats' => 'pdf'],
                    ['reference' => 'agreement', 'name' => 'Loan Agreement', 'mandatory' => true, 'permittedFormats' => 'pdf'],
                ],
            ],
        ],
    ]);

    Notification::fake();
    Http::fake(['wf.example.test/*' => Http::response(['ok' => true], 200)]);

    $this->user = FakeUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);
    $this->case = FakeCase::create(['workflow_id' => 'WF-1', 'user_id' => $this->user->id]);
    $this->service = app(DocumentLibraryService::class);
});

it('builds a request from a stage default set and notifies the customer', function () {
    $request = $this->service->requestFromWebhook([
        'reference' => 'DR-1', 'workflowId' => 'WF-1', 'stage' => 'disbursement_docs',
    ]);

    expect($request)->not->toBeNull()
        ->and($request->app)->toBe('testfund')
        ->and($request->documentable_id)->toBe($this->case->id)
        ->and($request->documents->pluck('name')->all())->toContain('Offer Letter', 'Loan Agreement')
        ->and($request->status)->toBe(DocumentRequest::STATUS_REQUESTED);

    Notification::assertSentTo($this->user, DocumentLibraryNotification::class);
});

it('is polymorphic — the case owns its requests through the shared trait', function () {
    $this->service->requestFromWebhook(['reference' => 'DR-1', 'workflowId' => 'WF-1', 'stage' => 'disbursement_docs']);

    expect($this->case->documentRequests()->count())->toBe(1);
});

it('runs the full review lifecycle and transmits on submit', function () {
    $request = $this->service->requestFromWebhook(['reference' => 'DR-1', 'workflowId' => 'WF-1', 'stage' => 'disbursement_docs']);
    $offer = $request->documents->firstWhere('reference', 'offer');
    $agreement = $request->documents->firstWhere('reference', 'agreement');

    // Upload both, then submit → under review + transmitted.
    foreach ([$offer, $agreement] as $doc) {
        DocumentVersion::create(['boi_document_id' => $doc->id, 'version' => 1, 'file_path' => 'p/'.$doc->id.'.pdf']);
        $doc->update(['status' => Document::STATUS_UPLOADED]);
    }
    $request->load('documents');
    expect($request->isReadyToSubmit())->toBeTrue();
    $request->update(['status' => DocumentRequest::STATUS_UNDER_REVIEW, 'submitted_at' => now()]);
    expect($this->service->transmit($request))->toBeTrue();
    Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://wf.example.test/submit' && $r['reference'] === 'DR-1');

    // Review: offer accepted, agreement returned → status returned + customer notified.
    Notification::fake();
    $this->service->applyReviews('DR-1', [
        ['documentReference' => 'offer', 'decision' => 'accepted'],
        ['documentReference' => 'agreement', 'decision' => 'returned', 'comment' => 'Missing page'],
    ], $this->user);

    expect($request->fresh()->status)->toBe(DocumentRequest::STATUS_RETURNED)
        ->and($agreement->fresh()->status)->toBe(Document::STATUS_RETURNED);
    Notification::assertSentTo($this->user, DocumentLibraryNotification::class);

    // Re-supply + accept → complete.
    DocumentVersion::create(['boi_document_id' => $agreement->id, 'version' => 2, 'file_path' => 'p/a2.pdf']);
    $agreement->update(['status' => Document::STATUS_UPLOADED]);
    $this->service->applyReviews('DR-1', [['documentReference' => 'agreement', 'decision' => 'accepted']], $this->user);

    expect($request->fresh()->status)->toBe(DocumentRequest::STATUS_COMPLETE);
});

it('returns null when no case matches the webhook', function () {
    expect($this->service->requestFromWebhook(['reference' => 'DR-X', 'workflowId' => 'NOPE']))->toBeNull();
});
