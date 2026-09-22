<?php

namespace Boi\Backend\DocumentLibrary\Http\Controllers;

use Boi\Backend\DocumentLibrary\Contracts\DocumentOwnerResolver;
use Boi\Backend\DocumentLibrary\DocumentLibraryService;
use Boi\Backend\DocumentLibrary\Http\Requests\DocumentLibraryWebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Inbound bridge from the BOI credit workflow: a Project Officer's document requests
 * and the review outcomes (approve / return / waive) for a fund.
 */
class DocumentWebhookController extends Controller
{
    public function __construct(private readonly DocumentLibraryService $service) {}

    public function requestDocuments(DocumentLibraryWebhookRequest $request): JsonResponse
    {
        $documentRequest = $this->service->requestFromWebhook($request->validated());

        if (! $documentRequest) {
            return response()->json(['success' => false, 'message' => 'No case found for the request.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document request received',
            'data' => ['id' => $documentRequest->id, 'status' => $documentRequest->status],
        ]);
    }

    public function reviewDocuments(DocumentLibraryWebhookRequest $request): JsonResponse
    {
        // Resolve the customer so a returned document can notify them.
        $resolverClass = config('boi_document_library.resolver');
        $notifiable = $resolverClass
            ? (app($resolverClass) instanceof DocumentOwnerResolver
                ? (app($resolverClass)->resolve($request->validated())['notifiable'] ?? null)
                : null)
            : null;

        $documentRequest = $this->service->applyReviews(
            $request->input('reference'),
            $request->input('reviews', []),
            $notifiable,
        );

        if (! $documentRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found: '.$request->input('reference')], 404);
        }

        return response()->json(['success' => true, 'message' => 'Review recorded', 'data' => ['status' => $documentRequest->status]]);
    }
}
