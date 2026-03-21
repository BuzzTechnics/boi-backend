<?php

namespace Boi\Backend\Models;

use Boi\Backend\Database\Factories\BankStatementFactory;
use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Eloquent model for shared `bank_statements` table (boi-api DB connection in consuming apps).
 *
 * Rows are usually created via boi-api HTTP; consuming apps use this for relationships and checks.
 */
class BankStatement extends Model
{
    use HasFactory;
    use UsesBoiApiDatabase;

    /**
     * Resolved URLs for opening stored files / external links (e.g. Nova, APIs).
     *
     * @var list<string>
     */
    protected $appends = [
        'bank_statement_view_url',
        'csv_view_url',
    ];

    protected $fillable = [
        'application_id',
        'app',
        'bank',
        'account_number',
        'account_name',
        'account_type',
        'bvn',
        'email',
        'bank_statement',
        'csv_url',
        'consent_id',
        'edoc_status',
        'statement_generated',
    ];

    protected function casts(): array
    {
        return [
            'statement_generated' => 'boolean',
        ];
    }

    protected static function newFactory(): BankStatementFactory
    {
        return BankStatementFactory::new();
    }

    /**
     * URL for the uploaded statement file (usually PDF on S3).
     *
     * For manual-upload → EDOC flows, this must stay the PDF key; the derived CSV is {@see getCsvViewUrlAttribute}.
     * For EDOC-only (no PDF), `bank_statement` may hold the CSV object key instead.
     */
    public function getBankStatementViewUrlAttribute(): ?string
    {
        $val = $this->attributes['bank_statement'] ?? null;
        if ($val === null || $val === '') {
            return null;
        }

        $val = (string) $val;

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        return self::resolveStorageViewUrl($val);
    }

    /**
     * URL for the EDOC CSV: remote `https://` URL and/or S3 object key (presigned when not a full URL).
     */
    public function getCsvViewUrlAttribute(): ?string
    {
        $val = $this->attributes['csv_url'] ?? null;
        if ($val === null || $val === '') {
            return null;
        }

        $val = (string) $val;

        if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) {
            return $val;
        }

        return self::resolveStorageViewUrl($val);
    }

    /**
     * Presigned (5 min) or public URL for an object key on the `s3` disk.
     */
    protected static function resolveStorageViewUrl(string $path): ?string
    {
        try {
            $s3 = Storage::disk('s3');

            return method_exists($s3, 'temporaryUrl')
                ? $s3->temporaryUrl($path, now()->addMinutes(5))
                : $s3->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
