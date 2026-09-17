<?php

namespace Boi\Backend\Support;

/**
 * Turns a raw eDoc error response into a clear, customer-facing message.
 *
 * eDoc rejections (e.g. a photo/scan saved as a PDF, a password-locked file)
 * otherwise surface to customers as opaque "HTTP 4xx" strings. This maps the
 * known cases to actionable guidance and falls back to the cleaned raw eDoc
 * message — never an empty string.
 *
 * The keyword list is best-effort and should be tuned against real eDoc
 * responses captured in logs.
 */
final class EdocErrorMapper
{
    /**
     * @param  int|null  $status   HTTP status from the eDoc response (if any)
     * @param  string|null  $rawBody  Raw eDoc response body (JSON or text)
     * @param  string  $fallback  Used when no message can be derived
     */
    public static function friendly(?int $status, ?string $rawBody, string $fallback): string
    {
        // Match keywords against the WHOLE body, lowercased — eDoc nests the
        // useful detail (e.g. "No data available for the selected period")
        // inside data.data as stringified JSON, so the top-level message alone
        // ("Error while fetching Dashboard") isn't enough to classify.
        $rawLower = strtolower((string) $rawBody);

        foreach (self::rules() as [$keywords, $friendly]) {
            foreach ($keywords as $keyword) {
                if (str_contains($rawLower, $keyword)) {
                    return $friendly;
                }
            }
        }

        // No known pattern. The body is only worth repeating when it is eDoc's own
        // JSON — the other things that arrive here are a gateway's HTML error page
        // or a transport error, and neither is a sentence a customer can act on.
        // Echoing one put "<html><head><title>502 Server Error</title>…" on an
        // applicant's screen under "Bank statement status: Failed", truncated
        // mid-tag (BOI, 2026-09-17).
        if (! self::looksLikeJson($rawBody)) {
            return $fallback;
        }

        $message = self::extractMessage($rawBody);

        return $message === '' ? $fallback : self::clean($message);
    }

    /**
     * Ordered highest-confidence first. Keywords are matched (lowercased)
     * against the full eDoc response body.
     *
     * The first rule covers the real observed signals when a customer uploads
     * an image/photo instead of a digital PDF: eDoc accepts the upload but
     * never produces a CSV, so downstream calls report no data / missing file.
     *
     * @return array<int, array{0: array<int, string>, 1: string}>
     */
    private static function rules(): array
    {
        return [
            [
                // The service itself is down rather than rejecting the statement.
                // eDoc sits behind a gateway that answers with an HTML error page,
                // so these are matched before anything that reads the body as a
                // rejection reason.
                ['502 server error', 'bad gateway', 'gateway time-out', 'gateway timeout', '503 service', 'service unavailable', 'temporarily unavailable'],
                'The bank statement service is not responding at the moment. Please try again in a few minutes.',
            ],
            [
                // Observed eDoc wordings for "nothing could be parsed":
                //  - /consent/metrics 404: "No data available for the selected period"
                //  - csvUrl object: "NoSuchKey" / "The specified key does not exist" / "No such object"
                //  - /dashboard 400: "Consent is not active"
                ['no data available', 'no such key', 'specified key does not exist', 'no such object', 'consent is not active', 'no transactions', 'no statement data'],
                "We couldn't read any transactions from this statement. Please upload the original PDF downloaded from your bank's app or internet banking — photos or scanned images can't be processed.",
            ],
            [
                ['image', 'scan', 'no text', 'unreadable', 'could not extract', 'cannot extract', 'no readable text', 'not machine readable', 'ocr'],
                "This looks like a scanned image or photo. Please upload the original PDF statement downloaded from your bank's app or internet banking.",
            ],
            [
                ['password', 'encrypted', 'protected', 'locked'],
                'This PDF is password-protected. Please remove the password and upload an unlocked statement.',
            ],
            [
                ['corrupt', 'damaged', 'not a valid pdf', 'invalid pdf', 'malformed'],
                'This file appears to be corrupted. Please download a fresh copy of your statement and upload it again.',
            ],
            [
                ['unsupported', 'invalid format', 'invalid file', 'parse', 'could not read', 'unable to read', 'format not'],
                "We couldn't read this statement. Please upload the original PDF downloaded from your bank.",
            ],
        ];
    }

    /**
     * Pull a human message out of the eDoc body (JSON message/error/errors[0])
     * or return the trimmed raw text.
     */
    private static function extractMessage(?string $rawBody): string
    {
        $rawBody = (string) $rawBody;
        if (trim($rawBody) === '') {
            return '';
        }

        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                if (! empty($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }

            if (! empty($decoded['errors'])) {
                $errors = $decoded['errors'];
                if (is_string($errors)) {
                    return $errors;
                }
                if (is_array($errors)) {
                    $first = reset($errors);
                    if (is_string($first)) {
                        return $first;
                    }
                    if (is_array($first)) {
                        $nested = reset($first);
                        if (is_string($nested)) {
                            return $nested;
                        }
                    }
                }
            }

            // JSON with no recognised key — don't echo the raw structure.
            return '';
        }

        return $rawBody;
    }

    /**
     * eDoc answers with JSON; a gateway in front of it answers with HTML, and a
     * transport failure has no body at all. Only the first is worth quoting back.
     */
    private static function looksLikeJson(?string $rawBody): bool
    {
        $trimmed = ltrim((string) $rawBody);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private static function clean(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        return mb_strlen($message) > 300 ? mb_substr($message, 0, 297).'…' : $message;
    }
}
