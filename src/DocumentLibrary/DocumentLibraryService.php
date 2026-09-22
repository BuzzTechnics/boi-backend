<?php

namespace Boi\Backend\DocumentLibrary;

use Boi\Backend\DocumentLibrary\Contracts\DocumentOwnerResolver;
use Boi\Backend\DocumentLibrary\Models\Document;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Boi\Backend\DocumentLibrary\Notifications\DocumentLibraryNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The shared document-library engine: turns a workflow's requests and review
 * outcomes into records, and pushes a submitted package back. Fund-agnostic — the
 * only fund-specific piece is the {@see DocumentOwnerResolver} it is given.
 */
class DocumentLibraryService
{
    private function app(): string
    {
        return (string) config('boi_document_library.app');
    }

    private function resolver(): ?DocumentOwnerResolver
    {
        $class = config('boi_document_library.resolver');

        return $class ? app($class) : null;
    }

    /**
     * A Project Officer has requested documents. Create (or refresh) the request and
     * its documents, then notify the customer.
     *
     * @param  array<string,mixed>  $payload
     */
    public function requestFromWebhook(array $payload): ?DocumentRequest
    {
        $owner = $this->resolver()?->resolve($payload);
        if (! $owner || empty($owner['documentable'])) {
            return null;
        }

        $documentable = $owner['documentable'];
        $stage = $payload['stage'] ?? 'default';
        $rows = ! empty($payload['documents'])
            ? $payload['documents']
            : (array) config("boi_document_library.stages.{$stage}.documents", []);

        $request = DB::transaction(function () use ($payload, $documentable, $owner, $stage, $rows) {
            $request = DocumentRequest::updateOrCreate(
                ['app' => $this->app(), 'reference' => $payload['reference']],
                [
                    'documentable_type' => $documentable->getMorphClass(),
                    'documentable_id' => $documentable->getKey(),
                    'user_id' => $owner['user_id'] ?? null,
                    'company_id' => $owner['company_id'] ?? null,
                    'stage' => $stage,
                    'project_officer_id' => $payload['projectOfficerId'] ?? null,
                    'message' => $payload['message'] ?? null,
                    'requested_at' => now(),
                    'due_at' => $payload['dueAt'] ?? null,
                ]
            );

            foreach ($rows as $doc) {
                $document = Document::firstOrNew([
                    'boi_document_request_id' => $request->id,
                    'reference' => $doc['reference'],
                ]);

                $document->fill([
                    'name' => $doc['name'],
                    'category' => $doc['category'] ?? null,
                    'is_mandatory' => (bool) ($doc['mandatory'] ?? true),
                    'instructions' => $doc['instructions'] ?? null,
                    'permitted_formats' => $doc['permittedFormats'] ?? null,
                    'max_size_kb' => $doc['maxSizeKb'] ?? null,
                ]);

                if (! $document->exists) {
                    $document->status = Document::STATUS_REQUESTED;
                }

                $document->save();
            }

            $request->load('documents');
            $request->recomputeStatus();

            return $request;
        });

        $this->notify(
            $owner['notifiable'] ?? null,
            'Documents requested',
            array_values(array_filter([
                $payload['message'] ?? null,
                'Please log into the portal and provide the requested documents in your document library.',
            ])),
        );

        return $request;
    }

    /**
     * Apply the workflow's review outcomes to a request's documents and re-derive the
     * overall status; when anything is returned, ask the customer to re-supply.
     *
     * @param  array<int,array<string,mixed>>  $reviews
     */
    public function applyReviews(string $reference, array $reviews, ?object $notifiable = null): ?DocumentRequest
    {
        $request = DocumentRequest::where('app', $this->app())->where('reference', $reference)->first();
        if (! $request) {
            return null;
        }

        $returned = false;

        DB::transaction(function () use ($request, $reviews, &$returned) {
            foreach ($reviews as $review) {
                $document = $request->documents()->where('reference', $review['documentReference'])->first();
                if (! $document) {
                    continue;
                }

                $status = match ($review['decision']) {
                    'accepted' => Document::STATUS_ACCEPTED,
                    'returned' => Document::STATUS_RETURNED,
                    'waived' => Document::STATUS_WAIVED,
                    default => $document->status,
                };

                $document->update(['status' => $status, 'officer_comment' => $review['comment'] ?? null]);
                $document->latestVersion()->update([
                    'review_decision' => $review['decision'],
                    'review_comment' => $review['comment'] ?? null,
                    'reviewed_at' => now(),
                ]);

                if ($status === Document::STATUS_RETURNED) {
                    $returned = true;
                }
            }

            $request->load('documents');
            $request->recomputeStatus();
        });

        if ($returned) {
            $this->notify(
                $notifiable,
                'Documents returned for re-upload',
                ['One or more documents were returned. Please review the comments and re-upload the corrected documents in your document library.'],
            );
        }

        return $request;
    }

    /**
     * Push a submitted package to the workflow. Best effort: a missing URL or a
     * transport error never loses the submission — it is recorded and re-pushable.
     */
    public function transmit(DocumentRequest $request): bool
    {
        $url = config('boi_document_library.submit_url');
        if (empty($url)) {
            Log::info('Document library: no submit URL configured; package recorded but not transmitted', ['request_id' => $request->id]);

            return false;
        }

        $request->loadMissing('documents.latestVersion');

        $payload = [
            'app' => $request->app,
            'reference' => $request->reference,
            'stage' => $request->stage,
            'submittedAt' => optional($request->submitted_at)->toIso8601String(),
            'documents' => $request->documents->map(fn (Document $d) => [
                'reference' => $d->reference,
                'name' => $d->name,
                'status' => $d->status,
                'filePath' => optional($d->latestVersion)->file_path,
                'version' => optional($d->latestVersion)->version,
            ])->values()->all(),
        ];

        try {
            $response = Http::withOptions(['verify' => config('boi_document_library.verify_ssl', true)])
                ->withHeaders(['X-Webhook-Key' => (string) config('boi_document_library.webhook_key')])
                ->timeout(60)
                ->post($url, $payload)
                ->json();

            $request->update(['workflow_response' => $response]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Document library: transmission to workflow failed', ['request_id' => $request->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Extensions a customer may upload for a document, as a list. */
    public function allowedExtensions(Document $document): array
    {
        $formats = trim((string) $document->permitted_formats);
        if ($formats === '') {
            return (array) config('boi_document_library.default_formats', ['pdf']);
        }

        return collect(explode(',', strtolower($formats)))
            ->map(fn ($e) => trim(ltrim($e, '.')))->filter()->unique()->values()->all();
    }

    /** Shape a request + its documents for the applicant UI. */
    public function present(DocumentRequest $request): array
    {
        return [
            'id' => $request->id,
            'reference' => $request->reference,
            'stage' => $request->stage,
            'stage_label' => config("boi_document_library.stages.{$request->stage}.label", $request->stage),
            'status' => $request->status,
            'message' => $request->message,
            'requested_at' => optional($request->requested_at)->toDateString(),
            'due_at' => optional($request->due_at)->toDateString(),
            'submitted_at' => optional($request->submitted_at)->toIso8601String(),
            'can_submit' => $request->isReadyToSubmit() && $request->submitted_at === null,
            'outstanding_count' => $request->outstandingMandatory()->count(),
            'documents' => $request->documents->map(fn (Document $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'category' => $d->category,
                'is_mandatory' => $d->is_mandatory,
                'instructions' => $d->instructions,
                'permitted_formats' => $d->permitted_formats,
                'max_size_kb' => $d->max_size_kb,
                'status' => $d->status,
                'officer_comment' => $d->officer_comment,
                'uploadable' => $d->isUploadable(),
                'latest_version' => $d->latestVersion ? [
                    'version' => $d->latestVersion->version,
                    'original_name' => $d->latestVersion->original_name,
                    'uploaded_at' => optional($d->latestVersion->created_at)->toIso8601String(),
                ] : null,
            ])->values()->all(),
        ];
    }

    /** @param array<int,string> $lines */
    private function notify(?object $notifiable, string $subject, array $lines): void
    {
        if (! $notifiable) {
            return;
        }

        $url = rtrim((string) config('boi_document_library.portal_url'), '/');
        NotificationFacade::send($notifiable, new DocumentLibraryNotification($subject, $lines, $url ?: null));
    }
}
