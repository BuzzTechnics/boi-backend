<?php

/**
 * The shared SLA timeline: a reminder at 75% of a stage's SLA and escalations at
 * 100%, 150% and 200%, all measured in working time.
 *
 * Every intervention fund had been building some part of this for itself, and each
 * one that measured wall-clock time escalated short SLAs over weekends. These tests
 * are the contract the funds now share.
 */

use Boi\Backend\Sla\Contracts\SlaDirectory;
use Boi\Backend\Sla\Models\SlaNotificationLog;
use Boi\Backend\Sla\Models\SlaTracker;
use Boi\Backend\Sla\Notifications\SlaEscalationNotification;
use Boi\Backend\Sla\Notifications\SlaReminderNotification;
use Boi\Backend\Sla\SlaEngine;
use Boi\Backend\Tests\Support\FakeDirectory;
use Boi\Backend\Tests\Support\FakeUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
    });

    config([
        'boi_sla.app' => 'spaf',
        // NSDC review: two working days = 960 minutes.
        'boi_sla.definitions' => [
            'nsdc_review' => [
                'name' => 'NSDC Application Review',
                'minutes' => 2 * 8 * 60,
                'assignment_minutes' => 4 * 60,
            ],
        ],
    ]);

    $this->directory = new FakeDirectory;
    app()->instance(SlaDirectory::class, $this->directory);

    $this->directory->people = [
        'owners' => collect([FakeUser::create(['name' => 'Reviewer', 'email' => 'reviewer@nsdc.gov.ng'])]),
        'level_1' => collect([FakeUser::create(['name' => 'Group Head', 'email' => 'head@boi.ng'])]),
        'level_2' => collect([FakeUser::create(['name' => 'Divisional Head', 'email' => 'divisional@boi.ng'])]),
        'level_3' => collect([FakeUser::create(['name' => 'Super Admin', 'email' => 'admin@boi.ng'])]),
    ];

    // A plain Wednesday, mid-morning.
    Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00'));
});

afterEach(fn () => Carbon::setTestNow());

function engine(): SlaEngine
{
    return app(SlaEngine::class);
}

function trackerEnteredAt(string $when, string $type = SlaTracker::TYPE_OWNER, string $caseType = 'nsdc_review'): SlaTracker
{
    return SlaTracker::create([
        'app' => 'spaf',
        'type' => $type,
        'case_type' => $caseType,
        'case_id' => 42,
        'start_time' => Carbon::parse($when),
        'status' => SlaTracker::STATUS_IN_PROGRESS,
    ]);
}

it('reminds the task owner at 75% and nobody else', function () {
    Notification::fake();

    // Monday 14:00 -> Wednesday 10:00 is 12 working hours of a 16-hour SLA.
    $tracker = trackerEnteredAt('2026-09-14 14:00:00');

    expect(engine()->run()['reminder'])->toBe(1);

    Notification::assertSentTo($this->directory->people['owners']->first(), SlaReminderNotification::class);
    Notification::assertNotSentTo($this->directory->people['level_1']->first(), SlaEscalationNotification::class);
    expect($tracker->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($tracker->fresh()->status)->toBe(SlaTracker::STATUS_IN_PROGRESS);
});

it('escalates to the first level at 100% and marks the clock breached', function () {
    Notification::fake();

    $tracker = trackerEnteredAt('2026-09-14 08:00:00'); // 16 working hours

    engine()->run();

    Notification::assertSentTo(
        $this->directory->people['level_1']->first(),
        SlaEscalationNotification::class,
        fn ($notification) => $notification->level === 1
            && in_array('reviewer@nsdc.gov.ng', $notification->copyTo, true)
    );

    expect($tracker->fresh()->status)->toBe(SlaTracker::STATUS_BREACHED);
});

it('copies everyone below at each higher level', function () {
    Notification::fake();

    trackerEnteredAt('2026-09-10 08:00:00'); // past 200%

    engine()->run();

    Notification::assertSentTo(
        $this->directory->people['level_2']->first(),
        SlaEscalationNotification::class,
        fn ($n) => $n->level === 2
            && in_array('reviewer@nsdc.gov.ng', $n->copyTo, true)
            && in_array('head@boi.ng', $n->copyTo, true)
    );

    Notification::assertSentTo($this->directory->people['level_3']->first(), SlaEscalationNotification::class, fn ($n) => $n->level === 3);
});

it('sends each level once however often it runs', function () {
    Notification::fake();

    trackerEnteredAt('2026-09-14 08:00:00');

    engine()->run();

    Notification::fake();
    expect(engine()->run())->toBe(['reminder' => 0, 'escalation_1' => 0, 'escalation_2' => 0, 'escalation_3' => 0]);
    Notification::assertNothingSent();
});

it('does not burn a four-hour SLA over the weekend', function () {
    Notification::fake();

    Carbon::setTestNow(Carbon::parse('2026-09-14 09:00:00')); // Monday 09:00
    trackerEnteredAt('2026-09-11 15:00:00', SlaTracker::TYPE_ASSIGNMENT); // Friday 15:00

    // One working hour on Friday plus one on Monday: half of the four allowed.
    engine()->run();

    Notification::assertNothingSent();
});

it('leaves a clock alone while it is inside its SLA', function () {
    Notification::fake();

    trackerEnteredAt('2026-09-16 09:00:00');

    engine()->run();

    Notification::assertNothingSent();
});

it('closes a clock whose case has gone rather than escalating about nothing', function () {
    Notification::fake();
    $this->directory->caseExists = false;

    $tracker = trackerEnteredAt('2026-09-14 08:00:00');

    engine()->run();

    Notification::assertNothingSent();
    expect($tracker->fresh()->status)->toBe(SlaTracker::STATUS_COMPLETED);
});

it('records every attempt in the trail', function () {
    trackerEnteredAt('2026-09-14 08:00:00');

    engine()->run();

    $reminder = SlaNotificationLog::where('notification_type', 'reminder')->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->app)->toBe('spaf')
        ->and($reminder->recipient_email)->toBe('reviewer@nsdc.gov.ng')
        ->and($reminder->case_type)->toBe('nsdc_review')
        ->and($reminder->status)->toBe('sent')
        ->and(SlaNotificationLog::where('notification_type', 'escalation_1')->first()?->recipient_email)
        ->toBe('head@boi.ng');
});

it('starts a clock with a deadline in working time', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-11 15:00:00')); // Friday

    $tracker = engine()->start('nsdc_review', 7, SlaTracker::TYPE_ASSIGNMENT);

    // Four working hours: one on Friday, three on Monday morning.
    expect($tracker->deadline->toDateTimeString())->toBe('2026-09-14 11:00:00');
});

it('closes the previous clock of the same type when a new one starts', function () {
    $first = engine()->start('nsdc_review', 7);
    $second = engine()->start('nsdc_review', 7);

    expect($first->fresh()->status)->toBe(SlaTracker::STATUS_COMPLETED)
        ->and($second->fresh()->status)->toBe(SlaTracker::STATUS_IN_PROGRESS);
});

it('keeps a breach on the record when the case is finished late', function () {
    $tracker = trackerEnteredAt('2026-09-14 08:00:00');
    engine()->run();

    engine()->complete('nsdc_review', 42);

    // Finishing late does not unsay that it was late.
    expect($tracker->fresh()->status)->toBe(SlaTracker::STATUS_BREACHED)
        ->and($tracker->fresh()->completed_at)->not->toBeNull();
});

it('keeps escalating after the breach, on later runs', function () {
    Notification::fake();

    // Enters at 100% on this run, then crosses 150% by the next.
    $tracker = trackerEnteredAt('2026-09-14 08:00:00');
    engine()->run();
    expect($tracker->fresh()->status)->toBe(SlaTracker::STATUS_BREACHED);

    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00')); // next working day's worth

    expect(engine()->run()['escalation_2'])->toBe(1);
});
