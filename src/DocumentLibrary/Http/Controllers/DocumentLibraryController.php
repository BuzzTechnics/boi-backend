<?php

namespace Boi\Backend\DocumentLibrary\Http\Controllers;

use Boi\Backend\DocumentLibrary\DocumentLibraryService;
use Boi\Backend\DocumentLibrary\Models\Document;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Boi\Backend\DocumentLibrary\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The customer's document library, scoped to the signed-in user. JSON endpoints a
 * fund mounts behind its own auth; the boi-ui DocumentLibrary component drives them.
 */
class DocumentLibraryController extends Controller
{
    public function __construct(private readonly DocumentLibraryService $service) {}

    /** The signed-in customer's document requests for this fund. */
    public function index(Request $request): JsonResponse
    {
        $requests = DocumentRequest::query()
            ->where('app', config('boi_document_library.app'))
            ->where('user_id', $request->user()->getKey())
            ->with(['documents.latestVersion'])
            ->latest()
            ->get()
            ->map(fn (DocumentRequest $r) => $this->service->present($r));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /** Record a customer upload as a new version — multipart `file`, or an S3 `path`. */
    public function upload(Request $request, DocumentRequest $documentRequest, Document $document): JsonResponse
    {
        $this->authorizeUpload($request, $documentRequest, $document);

        if (! $document->isUploadable()) {
            throw ValidationException::withMessages(['document' => 'This document can no longer be uploaded.']);
        }

        [$path, $name, $sizeKb] = $request->hasFile('file')
            ? $this->storeFile($request, $document)
            : $this->resolvePath($request, $document);

        DB::transaction(function () use ($request, $document, $documentRequest, $path, $name, $sizeKb) {
            DocumentVersion::create([
                'boi_document_id' => $document->id,
                'version' => (int) ($document->versions()->max('version') ?? 0) + 1,
                'file_path' => $path,
                'original_name' => $name,
                'size_kb' => $sizeKb,
                'uploaded_by' => $request->user()->getKey(),
            ]);
            $document->update(['status' => Document::STATUS_UPLOADED]);
            $documentRequest->load('documents');
            $documentRequest->recomputeStatus();
        });

        return response()->json(['success' => true, 'message' => 'Document uploaded', 'data' => $this->service->present($documentRequest->fresh(['documents.latestVersion']))]);
    }

    /** Submit the package once every mandatory document is in. */
    public function submit(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $this->authorizeRequest($request, $documentRequest);
        $documentRequest->load('documents');

        $outstanding = $documentRequest->outstandingMandatory();
        if ($outstanding->isNotEmpty()) {
            $names = $outstanding->pluck('name')->implode(' and ');
            throw ValidationException::withMessages([
                'submit' => "Submission cannot be completed. {$outstanding->count()} required document(s) are outstanding: {$names}.",
            ]);
        }

        $documentRequest->update(['status' => DocumentRequest::STATUS_UNDER_REVIEW, 'submitted_at' => now()]);
        $this->service->transmit($documentRequest);

        return response()->json(['success' => true, 'message' => 'Documents submitted for review', 'data' => $this->service->present($documentRequest->fresh(['documents.latestVersion']))]);
    }

    /** @return array{0:string,1:string,2:int} */
    private function storeFile(Request $request, Document $document): array
    {
        $allowed = $this->service->allowedExtensions($document);
        $maxKb = $document->max_size_kb ?: 10240;
        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}", 'mimes:'.implode(',', $allowed)],
        ], ['file.mimes' => 'This document must be one of: '.implode(', ', $allowed).'.']);

        $uploaded = $request->file('file');
        $disk = config('boi_document_library.disk', 's3');
        $path = $uploaded->store('document-library', $disk);

        return [$path, $uploaded->getClientOriginalName(), (int) ceil($uploaded->getSize() / 1024)];
    }

    /** @return array{0:string,1:string,2:int} */
    private function resolvePath(Request $request, Document $document): array
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:4096', 'regex:/^[a-zA-Z0-9\/_.\-]+$/'],
            'original_name' => ['nullable', 'string', 'max:255'],
        ]);
        $path = $validated['path'];
        if (str_contains($path, '..')) {
            throw ValidationException::withMessages(['path' => 'Invalid storage path.']);
        }
        $allowed = $this->service->allowedExtensions($document);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages(['path' => 'This document must be one of: '.implode(', ', $allowed).'.']);
        }
        $disk = config('boi_document_library.disk', 's3');
        if (! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages(['path' => 'The file was not found in storage.']);
        }

        return [$path, $validated['original_name'] ?? basename($path), (int) ceil((Storage::disk($disk)->size($path) ?: 0) / 1024)];
    }

    private function authorizeUpload(Request $request, DocumentRequest $documentRequest, Document $document): void
    {
        abort_unless((int) $document->boi_document_request_id === (int) $documentRequest->id, 404);
        $this->authorizeRequest($request, $documentRequest);
    }

    private function authorizeRequest(Request $request, DocumentRequest $documentRequest): void
    {
        abort_unless(
            $documentRequest->app === config('boi_document_library.app')
                && (int) $documentRequest->user_id === (int) $request->user()->getKey(),
            404,
        );
    }
}
