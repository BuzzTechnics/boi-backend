<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

if (! function_exists('array_flatten_with_keys')) {
    function array_flatten_with_keys($array, string $prefix = '', string $separator = '.'): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix.$separator.$key;

            if (is_array($value) && ! empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                $result = array_merge($result, array_flatten_with_keys($value, $newKey, $separator));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}

if (! function_exists('asString')) {
    function asString($value, string $default = ''): string
    {
        return ($value === null || trim((string) $value) === '') ? $default : trim((string) $value);
    }
}

if (! function_exists('boi_files_browser_api_base')) {
    /**
     * Base URL for browser file upload/view (must match {@see boi_files_api_view_url}).
     * Empty string → host app `/api/files/*` (not proxied to boi-api).
     */
    function boi_files_browser_api_base(): string
    {
        $configured = rtrim((string) config('boi_files.api_base', ''), '/');
        if ($configured !== '') {
            if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
                return $configured;
            }

            return rtrim(URL::to($configured), '/');
        }
        if (rtrim((string) config('boi_proxy.url', ''), '/') !== '' && config('boi_proxy.key')) {
            return rtrim(URL::to('/api/boi-api'), '/');
        }

        return '';
    }
}

if (! function_exists('boi_files_api_view_url')) {
    /**
     * View URL for a stored file path (boi-ui {@see filesApi.view} / Inertia `boiProxy`).
     *
     * @param  string|null  $targetBucket  When set, appended as `bucket` query for boi-api dynamic S3 disks.
     */
    function boi_files_api_view_url(?string $stored, ?string $targetBucket = null): ?string
    {
        if ($stored === null || trim((string) $stored) === '') {
            return null;
        }
        $stored = (string) $stored;
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $bucket = $targetBucket !== null && trim((string) $targetBucket) !== '' ? trim((string) $targetBucket) : null;
        $suffix = '/api/files/view?path='.rawurlencode($stored);
        if ($bucket !== null) {
            $suffix .= '&bucket='.rawurlencode($bucket);
        }

        $base = boi_files_browser_api_base();
        if ($base === '') {
            return url($suffix);
        }

        return $base.$suffix;
    }
}

if (! function_exists('boi_files_resolved_bank_statement_view_bucket')) {
    /**
     * Bucket name for boi-api–stored statement keys (Inertia `boiFilesBankStatementViewParams`, Nova view links).
     * Uses {@see config('boi_files.bank_statement_view_bucket')} when set; otherwise the {@see boi_files.boi_api_disk} bucket.
     */
    function boi_files_resolved_bank_statement_view_bucket(): string
    {
        $b = trim((string) config('boi_files.bank_statement_view_bucket', ''));
        if ($b !== '') {
            return $b;
        }

        $disk = trim((string) config('boi_files.boi_api_disk', 'boiapi'));
        if ($disk === '') {
            $disk = 'boiapi';
        }

        return trim((string) config("filesystems.disks.{$disk}.bucket", ''));
    }
}

if (! function_exists('clean_request_arrays')) {
    function clean_request_arrays(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $array = $request->input($key, []);

            if (is_array($array)) {
                $request->merge([$key => array_filter($array, fn ($item) => ! is_null($item))]);
            }
        }
    }
}

if (! function_exists('is_json')) {
    function is_json($string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (! function_exists('split_name')) {
    /**
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    function split_name($name): array
    {
        $parts = array_values(array_filter(explode(' ', trim((string) $name))));
        $n = count($parts);

        if ($n === 0) {
            return ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
        }
        if ($n === 1) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => ''];
        }
        if ($n === 2) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => $parts[1]];
        }

        return [
            'first_name' => $parts[0],
            'middle_name' => $parts[1],
            'last_name' => implode(' ', array_slice($parts, 2)),
        ];
    }
}

if (! function_exists('rounded_currency')) {
    function rounded_currency($value): ?string
    {
        return $value !== null
            ? '₦'.number_format(BigDecimal::of($value)->toScale(2, RoundingMode::DOWN)->toFloat(), 2)
            : null;
    }
}

if (! function_exists('apply_search_filter')) {
    /**
     * Postgres-oriented flexible search (ILIKE + normalized match). Column names must be trusted (not user input).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    function apply_search_filter($query, ?string $search, array $searchableColumns)
    {
        return $query->when($search, function ($query) use ($search, $searchableColumns) {
            $trimmedSearch = trim($search);
            if ($trimmedSearch !== '') {
                $query->where(function ($q) use ($searchableColumns, $trimmedSearch) {
                    foreach ($searchableColumns as $column) {
                        if (strpos($column, '.') !== false) {
                            [$relation, $relatedColumn] = explode('.', $column);

                            $q->orWhereHas($relation, function ($subQuery) use ($relatedColumn, $trimmedSearch) {
                                $subQuery->whereRaw("CAST({$relatedColumn} AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%'])
                                    ->orWhereRaw("REGEXP_REPLACE(CAST({$relatedColumn} AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?",
                                        ['%'.preg_replace('/[^a-zA-Z0-9]/', '', $trimmedSearch).'%']);
                            });
                        } else {
                            $q->orWhereRaw("CAST({$column} AS TEXT) ILIKE ?", ['%'.$trimmedSearch.'%'])
                                ->orWhereRaw("REGEXP_REPLACE(CAST({$column} AS TEXT), '[^a-zA-Z0-9]', '', 'g') ILIKE ?",
                                    ['%'.preg_replace('/[^a-zA-Z0-9]/', '', $trimmedSearch).'%']);
                        }
                    }
                });
            }
        });
    }
}

if (! function_exists('get_lga_ids_by_internal_region')) {
    /** @return list<int|string> */
    function get_lga_ids_by_internal_region(int|string $internal_region_id): array
    {
        return \Boi\Backend\Models\Lga::idsForInternalRegion($internal_region_id);
    }
}

if (! function_exists('sharepoint_bool')) {
    function sharepoint_bool($value): string
    {
        return (bool) $value ? 'Yes' : 'No';
    }
}

if (! function_exists('sharepoint_format_document')) {
    /**
     * SharePoint-style document payload. Uses {@see config('boi_files.disk')} (default s3).
     *
     * @return array{ContentBytes: string, ContentType: string, FileName: string}
     */
    function sharepoint_format_document(?string $path): array
    {
        if (! $path) {
            return ['ContentBytes' => '', 'ContentType' => 'application/pdf', 'FileName' => ''];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $disk = (string) config('boi_files.disk', 's3');

        try {
            $fileUrl = Storage::disk($disk)->url($path) ?: '';
        } catch (\Throwable) {
            $fileUrl = '';
        }

        return [
            'ContentBytes' => $fileUrl,
            'ContentType' => $contentType,
            'FileName' => basename($path) ?: '',
        ];
    }
}

if (! function_exists('sharepoint_shorten_directorate')) {
    function sharepoint_shorten_directorate(?string $directorate): string
    {
        if (! $directorate) {
            return '';
        }

        $directorate = trim($directorate);

        if ($directorate === 'PSIP' || $directorate === 'Public_Sector_and_Intervention_Programs_Directorate') {
            return 'PS/IP';
        }

        return match ($directorate) {
            'Large_Enterprises_Directorate' => 'LE',
            'MSME_Directorate' => 'MSME',
            'Treasury_and_Financial_Institutions' => 'TFI',
            default => $directorate,
        };
    }
}

if (! function_exists('boi_inertia_shared_props')) {
    /**
     * Props for Inertia apps using boi-api proxy (merge into {@see Middleware\HandleInertiaRequests::share}).
     *
     * @return array{boiProxy: string, boiFilesApiBase: string, boiFilesBankStatementViewParams: array<string, string>|\stdClass}
     */
    function boi_inertia_shared_props(): array
    {
        $boiUrl = rtrim((string) config('boi_proxy.url', ''), '/');
        $boiKey = (string) config('boi_proxy.key', '');
        $proxy = ($boiUrl !== '' && $boiKey !== '') ? rtrim(URL::to('/api/boi-api'), '/') : '';

        $bankStatementViewBucket = boi_files_resolved_bank_statement_view_bucket();
        $boiFilesBankStatementViewParams = $bankStatementViewBucket !== ''
            ? ['bucket' => $bankStatementViewBucket]
            : new \stdClass();

        return [
            'boiProxy' => $proxy,
            'boiFilesApiBase' => boi_files_browser_api_base(),
            'boiFilesBankStatementViewParams' => $boiFilesBankStatementViewParams,
        ];
    }
}
