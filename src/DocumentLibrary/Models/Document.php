<?php

namespace Boi\Backend\DocumentLibrary\Models;

use Boi\Backend\DocumentLibrary\Models\Concerns\StoresDocumentRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One required item within a {@see DocumentRequest}. Each customer upload is a
 * {@see DocumentVersion}; the status reflects the latest review outcome.
 */
class Document extends Model
{
    use StoresDocumentRecords;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_WAIVED = 'waived';

    protected $table = 'boi_documents';

    protected $fillable = [
        'boi_document_request_id',
        'reference',
        'name',
        'category',
        'is_mandatory',
        'instructions',
        'permitted_formats',
        'max_size_kb',
        'status',
        'officer_comment',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'max_size_kb' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'boi_document_request_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'boi_document_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'boi_document_id')->latestOfMany('version');
    }

    /** Provided and not bounced back: uploaded, accepted or waived count. */
    public function isSatisfied(): bool
    {
        return in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_ACCEPTED, self::STATUS_WAIVED], true);
    }

    /** Whether the customer may (re)upload against this document right now. */
    public function isUploadable(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_UPLOADED, self::STATUS_RETURNED], true);
    }
}
