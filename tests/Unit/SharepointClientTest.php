<?php

use Boi\Backend\Services\SharepointClient;

/**
 * BOI, 21 Oct 2026 (GLOW #48351 "SA’ISH DESIGNS LIMITED"): "Send to SharePoint"
 * crashed with "normalizePayload(): Return value must be of type array, null
 * returned". A payload value held invalid UTF-8 (a prefix cut mid-multibyte
 * character upstream), json_encode returned false, and json_decode('') → null
 * violated the `: array` return type. The client must never crash the whole
 * submission on one bad byte.
 */

const LONE_MULTIBYTE_LEAD = "SA\xe2"; // 'S','A', lone first byte of a 3-byte char — invalid UTF-8

it('returns an array (never null) when a value holds invalid UTF-8', function () {
    $out = SharepointClient::normalizePayload(['CompanyShortname' => LONE_MULTIBYTE_LEAD]);

    expect($out)->toBeArray()
        ->and($out['CompanyShortname'])->toContain("\u{FFFD}"); // bad byte substituted, not dropped
});

it('encodes a payload with invalid UTF-8 to a string (never false)', function () {
    $json = SharepointClient::encode(['CompanyShortname' => LONE_MULTIBYTE_LEAD]);

    expect($json)->toBeString()
        ->and($json)->toContain('CompanyShortname');
});

it('collapses escaped CR-LF in nested string values', function () {
    $out = SharepointClient::normalizePayload(['notes' => ['body' => "line1\r\nline2"]]);

    expect($out['notes']['body'])->not->toContain("\r\n");
});

it('leaves a clean payload unchanged', function () {
    $payload = ['a' => 'hello', 'b' => ['c' => 'naïve café', 'd' => 42]];

    expect(SharepointClient::normalizePayload($payload))->toBe($payload);
});
