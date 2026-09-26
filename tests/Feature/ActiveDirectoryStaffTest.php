<?php

use Boi\Backend\Support\ActiveDirectoryStaff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('boi_integrations.active_directory.base_url', 'https://ad.example.test:8249');
    config()->set('boi_integrations.active_directory.username', 'SPAF');
    config()->set('boi_integrations.active_directory.password', 'spaf-pw');
    config()->set('boi_integrations.active_directory.search_min_length', 3);
    config()->set('boi_integrations.active_directory.role_map', [
        ['role' => 'Project Officer', 'patterns' => ['/\bproject officer\b/', '/\bte(?:am|an) me(?:mb|m|b)er\b/', '/^tm\b/']],
        ['role' => 'Group Head', 'patterns' => ['/\bstate manager\b/', '/\bgroup head\b/', '/^sm\b/', '/^gh\b/']],
    ]);
});

// JWT-shaped so BOI::isValidToken accepts the cached AD token on later reads.
function adJwt(): string
{
    return 'eyJ.eyJhZA.'.str_repeat('a', 16);
}

// --- roleNameFor (title → app role) ---

it('maps case-carrying job titles to Project Officer', function () {
    expect(ActiveDirectoryStaff::roleNameFor('PROJECT OFFICER'))->toBe('Project Officer');
    expect(ActiveDirectoryStaff::roleNameFor('TEAM MEMBER'))->toBe('Project Officer');
    expect(ActiveDirectoryStaff::roleNameFor('TM, MICRO ENTERPRISES'))->toBe('Project Officer');
});

it('maps first-level supervisor titles to Group Head', function () {
    expect(ActiveDirectoryStaff::roleNameFor('GROUP HEAD'))->toBe('Group Head');
    expect(ActiveDirectoryStaff::roleNameFor('AG. STATE MANAGER'))->toBe('Group Head');
    expect(ActiveDirectoryStaff::roleNameFor('GH, GENDER BUSINESS'))->toBe('Group Head');
});

it('returns null when AD has no opinion on the title', function () {
    expect(ActiveDirectoryStaff::roleNameFor('Executive Director'))->toBeNull();
    expect(ActiveDirectoryStaff::roleNameFor(''))->toBeNull();
    expect(ActiveDirectoryStaff::roleNameFor(null))->toBeNull();
});

// --- isAssignable (filter service/machine/security accounts) ---

it('accepts a real person and rejects service/machine/security accounts', function () {
    expect(ActiveDirectoryStaff::isAssignable('jdoe', 'Jane Doe', 'jdoe@boi.ng', '12345'))->toBeTrue();

    // Exchange system mailbox
    expect(ActiveDirectoryStaff::isAssignable('SystemMailbox', 'SM', 'SystemMailbox{guid}@boi.ng', ''))->toBeFalse();
    // Machine/service account
    expect(ActiveDirectoryStaff::isAssignable('IBOINIGERIA$', 'Svc', 'svc@boi.ng', ''))->toBeFalse();
    // Security-account twin
    expect(ActiveDirectoryStaff::isAssignable('jdoe', 'Jane Doe', 'jdoe@boi.ng', 'SECURITY ACCOUNT'))->toBeFalse();
    // Not a real mailbox
    expect(ActiveDirectoryStaff::isAssignable('jdoe', 'Jane Doe', 'not-an-email', '1'))->toBeFalse();
});

// --- normalize (shape, dedupe, sort, suggested_role) ---

it('normalises AD entries: filters, dedupes by email, sorts by name, and suggests a role', function () {
    $normalized = ActiveDirectoryStaff::normalize([
        ['samAccountName' => 'zoe', 'displayName' => 'Zoe Zulu', 'email' => 'zoe@boi.ng', 'role' => 'PROJECT OFFICER'],
        ['samAccountName' => 'amy', 'displayName' => 'Amy Ada', 'email' => 'amy@boi.ng', 'role' => 'GROUP HEAD'],
        // duplicate of amy (different case email) — deduped
        ['samAccountName' => 'amy2', 'displayName' => 'Amy Ada', 'email' => 'AMY@boi.ng', 'role' => 'GROUP HEAD'],
        // service account — filtered
        ['samAccountName' => 'IBOINIGERIA$', 'displayName' => 'Svc', 'email' => 'svc@boi.ng', 'role' => ''],
    ]);

    expect($normalized)->toHaveCount(2);
    // Sorted by name: Amy before Zoe (dedupe keeps one entry per email, last wins).
    expect(strtolower($normalized[0]['email']))->toBe('amy@boi.ng');
    expect($normalized[0]['suggested_role'])->toBe('Group Head');
    expect(strtolower($normalized[1]['email']))->toBe('zoe@boi.ng');
    expect($normalized[1]['suggested_role'])->toBe('Project Officer');
});

// --- search (auth + endpoint + extraction + caching) ---

it('authenticates against the AD host, hits the Employee endpoint and returns assignable staff', function () {
    Http::fake([
        'ad.example.test:8249/api/Authentication/Authenticate' => Http::response(adJwt(), 200),
        'ad.example.test:8249/api/Employee/SearchUsersActiveDirectory*' => Http::response([
            'data' => [
                ['samAccountName' => 'jdoe', 'displayName' => 'Jane Doe', 'email' => 'jdoe@boi.ng', 'role' => 'PROJECT OFFICER', 'employeeId' => '900'],
                ['samAccountName' => 'IBOINIGERIA$', 'displayName' => 'Svc', 'email' => 'svc@boi.ng', 'role' => ''],
            ],
        ], 200),
    ]);

    $results = ActiveDirectoryStaff::search('jane');

    expect($results)->toHaveCount(1);
    expect($results[0]['email'])->toBe('jdoe@boi.ng');
    expect($results[0]['suggested_role'])->toBe('Project Officer');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/Employee/SearchUsersActiveDirectory')
        && str_contains($request->url(), 'searchText=jane'));
});

it('returns [] for a term below the minimum length without calling AD', function () {
    Http::fake();

    expect(ActiveDirectoryStaff::search('ab'))->toBe([]);

    Http::assertNothingSent();
});
