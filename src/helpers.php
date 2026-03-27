<?php

use Illuminate\Http\Request;

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

if (! function_exists('boi_files_api_view_url')) {
    /**
     * View URL for a stored file path (boi-ui {@see filesApi.view} / Inertia `boiProxy`).
     */
    function boi_files_api_view_url(?string $stored): ?string
    {
        if ($stored === null || trim((string) $stored) === '') {
            return null;
        }
        $stored = (string) $stored;
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        $base = rtrim((string) config('boi_files.api_base', ''), '/');
        if ($base === '' && rtrim((string) config('boi_proxy.url', ''), '/') !== '' && config('boi_proxy.key')) {
            $base = '/api/boi-api';
        }
        $path = $base.'/api/files/view?path='.rawurlencode($stored);

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://') ? $path : url($path);
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
