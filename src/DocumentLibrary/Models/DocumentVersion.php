<?php

namespace Boi\Backend\DocumentLibrary\Models;

use Boi\Backend\DocumentLibrary\Models\Concerns\StoresDocumentRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single customer upload against a {@see Document}. Never replaced in place — a
 * re-supply after a return is a new row — so the original and every later version
 * stay linked for the audit trail, and a review decision is attributable to the
 * version reviewed.
 */
class DocumentVersion extends Model
{
    use StoresDocumentRecords;

    protected $table = 'boi_document_versions';

    protected $fillable = [
        'boi_document_id',
        'version',
        'file_path',
        'original_name',
        'size_kb',
        'uploaded_by',
        'review_decision',
        'review_comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_kb' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'boi_document_id');
    }
}
