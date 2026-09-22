<?php

namespace Boi\Backend\DocumentLibrary\Models;

use Boi\Backend\DocumentLibrary\Models\Concerns\StoresDocumentRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * A request for a customer's documents against one case (the polymorphic
 * {@see documentable}) and stage. Its overall {@see status} is the documentation
 * status, kept separate from each document's own status.
 */
class DocumentRequest extends Model
{
    use StoresDocumentRecords;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_AWAITING_CUSTOMER = 'awaiting_customer';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLETE = 'complete';

    protected $table = 'boi_document_requests';

    protected $fillable = [
        'app',
        'reference',
        'documentable_type',
        'documentable_id',
        'user_id',
        'company_id',
        'stage',
        'status',
        'project_officer_id',
        'message',
        'requested_at',
        'submitted_at',
        'due_at',
        'workflow_response',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'workflow_response' => 'array',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'boi_document_request_id');
    }

    /** Mandatory documents not yet satisfied — the ones blocking submission. */
    public function outstandingMandatory(): Collection
    {
        return $this->documents
            ->where('is_mandatory', true)
            ->filter(fn (Document $doc) => ! $doc->isSatisfied())
            ->values();
    }

    public function isReadyToSubmit(): bool
    {
        return $this->outstandingMandatory()->isEmpty();
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && $this->status !== self::STATUS_COMPLETE;
    }

    /**
     * Re-derive the overall documentation status from the documents' statuses, so it
     * always reflects the latest review outcome (a return reopens the request).
     */
    public function recomputeStatus(): void
    {
        $documents = $this->documents;

        if ($documents->isEmpty()) {
            return;
        }

        $mandatory = $documents->where('is_mandatory', true);

        if ($mandatory->every(fn (Document $d) => in_array($d->status, [Document::STATUS_ACCEPTED, Document::STATUS_WAIVED], true))) {
            $this->status = self::STATUS_COMPLETE;
        } elseif ($documents->contains(fn (Document $d) => $d->status === Document::STATUS_RETURNED)) {
            $this->status = self::STATUS_RETURNED;
        } elseif ($this->submitted_at !== null) {
            $this->status = self::STATUS_UNDER_REVIEW;
        } elseif ($documents->contains(fn (Document $d) => $d->status === Document::STATUS_UPLOADED)) {
            $this->status = self::STATUS_AWAITING_CUSTOMER;
        } else {
            $this->status = self::STATUS_REQUESTED;
        }

        $this->save();
    }
}
