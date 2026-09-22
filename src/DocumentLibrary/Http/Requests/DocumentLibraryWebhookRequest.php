<?php

namespace Boi\Backend\DocumentLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared-secret auth + validation for the document-library webhooks the workflow
 * calls: a document request, and a batch of review outcomes.
 */
class DocumentLibraryWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $key = $this->header('X-Webhook-Key');
        $expected = config('boi_document_library.webhook_key');

        return $key && $expected && hash_equals((string) $expected, (string) $key);
    }

    public function rules(): array
    {
        return [
            'reference' => 'required|string|max:255',
            'workflowId' => 'nullable|string|max:255',

            'stage' => 'nullable|string|max:100',
            'message' => 'nullable|string',
            'dueAt' => 'nullable|date',
            'documents' => 'nullable|array',
            'documents.*.reference' => 'required_with:documents|string|max:255',
            'documents.*.name' => 'required_with:documents|string|max:255',
            'documents.*.mandatory' => 'nullable|boolean',
            'documents.*.category' => 'nullable|string|max:255',
            'documents.*.instructions' => 'nullable|string',
            'documents.*.permittedFormats' => 'nullable|string|max:255',
            'documents.*.maxSizeKb' => 'nullable|integer|min:1',

            'reviews' => 'nullable|array',
            'reviews.*.documentReference' => 'required_with:reviews|string|max:255',
            'reviews.*.decision' => 'required_with:reviews|string|in:accepted,returned,waived',
            'reviews.*.comment' => 'nullable|string',
        ];
    }
}
