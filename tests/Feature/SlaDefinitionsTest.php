<?php

/**
 * The configurable half: a portal ships its SLAs in config and BOI adjusts them in
 * the database afterwards, without a deploy. That order matters — a fund goes live
 * on numbers that are reviewable and versioned.
 */

use Boi\Backend\Sla\BusinessCalendar;
use Boi\Backend\Sla\Models\SlaDefinition;
use Boi\Backend\Sla\Support\SlaDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'boi_sla.app' => 'spaf',
        'boi_sla.definitions' => [
            'nsdc_review' => [
                'name' => 'NSDC Application Review',
                'minutes' => 960,
                'assignment_minutes' => 240,
                'owner_roles' => ['NSDC Reviewer'],
                'level_1_roles' => ['Group Head'],
            ],
        ],
    ]);

    app(SlaDefinitions::class)->forget();
});

it('falls back to what the portal shipped with', function () {
    $definitions = app(SlaDefinitions::class);

    expect($definitions->minutes('nsdc_review', 'owner'))->toBe(960)
        ->and($definitions->minutes('nsdc_review', 'assignment'))->toBe(240)
        ->and($definitions->name('nsdc_review'))->toBe('NSDC Application Review')
        ->and($definitions->rolesFor('nsdc_review', 'owner_roles'))->toBe(['NSDC Reviewer']);
});

it('prefers a stored row over the shipped default', function () {
    SlaDefinition::create([
        'app' => 'spaf',
        'case_type' => 'nsdc_review',
        'name' => 'NSDC Review (revised)',
        'sla_minutes' => 480,
        'level_1_roles' => ['Divisional Head'],
    ]);

    $definitions = app(SlaDefinitions::class);

    expect($definitions->minutes('nsdc_review', 'owner'))->toBe(480)
        ->and($definitions->name('nsdc_review'))->toBe('NSDC Review (revised)')
        ->and($definitions->rolesFor('nsdc_review', 'level_1_roles'))->toBe(['Divisional Head'])
        // A slot the row leaves empty still falls back.
        ->and($definitions->rolesFor('nsdc_review', 'owner_roles'))->toBe(['NSDC Reviewer']);
});

it('ignores a row belonging to another fund', function () {
    SlaDefinition::create([
        'app' => 'glow',
        'case_type' => 'nsdc_review',
        'name' => 'Someone else',
        'sla_minutes' => 60,
    ]);

    expect(app(SlaDefinitions::class)->minutes('nsdc_review', 'owner'))->toBe(960);
});

it('ignores a row switched off', function () {
    SlaDefinition::create([
        'app' => 'spaf',
        'case_type' => 'nsdc_review',
        'name' => 'Retired',
        'sla_minutes' => 60,
        'is_active' => false,
    ]);

    expect(app(SlaDefinitions::class)->minutes('nsdc_review', 'owner'))->toBe(960);
});

it('lets one case type carry its own thresholds', function () {
    SlaDefinition::create([
        'app' => 'spaf',
        'case_type' => 'nsdc_review',
        'name' => 'NSDC',
        'sla_minutes' => 960,
        // The online portal stops at level 2 for its own queues.
        'thresholds' => ['escalation_3' => null],
    ]);

    $thresholds = app(SlaDefinitions::class)->thresholds('nsdc_review');

    expect($thresholds)->toHaveKeys(['reminder', 'escalation_1', 'escalation_2'])
        ->and($thresholds)->not->toHaveKey('escalation_3');
});

it('counts working time the way the document does', function (string $from, string $to, int $expected) {
    expect(app(BusinessCalendar::class)->workingMinutesBetween(Carbon::parse($from), Carbon::parse($to)))
        ->toBe($expected);
})->with([
    'inside one day' => ['2026-09-16 09:00', '2026-09-16 12:00', 180],
    'before opening earns nothing' => ['2026-09-16 05:00', '2026-09-16 09:00', 60],
    'after closing earns nothing' => ['2026-09-16 15:00', '2026-09-16 23:00', 60],
    'overnight skips the night' => ['2026-09-16 15:00', '2026-09-17 09:00', 120],
    'the weekend is skipped' => ['2026-09-11 15:00', '2026-09-14 09:00', 120],
    'a public holiday is skipped' => ['2026-12-24 15:00', '2026-12-28 09:00', 120],
]);
