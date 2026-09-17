<?php

use Boi\Backend\Support\EdocErrorMapper;

/**
 * BOI, 17 Sep 2026: an applicant's statement card read
 *
 *   Bank statement status: Failed
 *   <html><head> <meta http-equiv="content-type" content="text/html;charset=utf-8">
 *   <title>502 Server Error</title> </head> <body text=#000000 bgcolor=#ffffff
 *   <h1>Error: Server Error</h1> <h2>The server encountered a temporary error…
 *
 * eDoc sits behind a gateway that answers with an HTML page when it is down. No
 * keyword matched it, so the mapper fell through to echoing the body — the one
 * branch that assumed anything arriving here was a sentence.
 */
const FALLBACK = 'We could not process this statement. Please try again.';

it('names a gateway outage instead of printing its error page', function (string $body) {
    expect(EdocErrorMapper::friendly(502, $body, FALLBACK))
        ->toBe('The bank statement service is not responding at the moment. Please try again in a few minutes.');
})->with([
    'the page the applicant was shown' => '<html><head> <meta http-equiv="content-type" content="text/html;charset=utf-8"> <title>502 Server Error</title> </head> <body text=#000000 bgcolor=#ffffff><h1>Error: Server Error</h1><h2>The server encountered a temporary error and could not complete your request.<p>Please try again in 30 seconds.</h2></body></html>',
    'nginx' => '<html><head><title>502 Bad Gateway</title></head><body><center><h1>502 Bad Gateway</h1></center></body></html>',
    'gateway timeout' => '<html><head><title>504 Gateway Time-out</title></head></html>',
    'unavailable' => '<html><body>503 Service Unavailable</body></html>',
]);

it('never puts markup in front of a customer', function (?string $body) {
    expect(EdocErrorMapper::friendly(500, $body, FALLBACK))->toBe(FALLBACK);
})->with([
    'an html page with no recognisable wording' => '<html><body><h1>Something went wrong</h1></body></html>',
    'a transport error' => 'cURL error 28: Operation timed out after 120001 milliseconds',
    'nothing at all' => null,
    'an empty body' => '',
]);

it('still quotes eDoc when eDoc is the one talking', function () {
    // A JSON body carries eDoc's own sentence, which is worth repeating verbatim
    // when no rule recognises it — that is the whole point of the fallback branch.
    expect(EdocErrorMapper::friendly(400, '{"codes":400,"message":"Statement duration exceeds the allowed range"}', FALLBACK))
        ->toBe('Statement duration exceeds the allowed range');
});

it('keeps mapping the rejections it already knew', function (string $body, string $expected) {
    expect(EdocErrorMapper::friendly(400, $body, FALLBACK))->toContain($expected);
})->with([
    'password-locked pdf' => ['{"message":"The PDF is password protected"}', 'password-protected'],
    'photo instead of a statement' => ['{"message":"No data available for the selected period"}', "couldn't read any transactions"],
    'corrupt file' => ['{"message":"Not a valid PDF"}', 'corrupted'],
]);
