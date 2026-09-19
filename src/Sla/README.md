# Shared SLA engine

The reminder and escalation timeline BOI applies across its intervention funds, in
one place instead of five.

    Reminder       75% of the SLA   → the task owner
    Escalation 1  100%              → first-level supervisor, copying the owner
    Escalation 2  150%              → second level, copying those below
    Escalation 3  200%              → as configured

Elapsed time is **working time**: weekdays 08:00–16:00, minus six public holidays
("SPAF Portal Project – Task Reminder and Escalation Notifications"). Every fund that
measured wall-clock time escalated its short SLAs over weekends.

## What a portal provides

Only the part this package cannot know: who its people are and what its cases are
called. Implement `Boi\Backend\Sla\Contracts\SlaDirectory` and bind it:

```php
$this->app->bind(SlaDirectory::class, MyPortalDirectory::class);
```

`describe()` returns an `SlaCaseSummary` (reference, task name, company, registration
number, url). `owners()` and `supervisors($tracker, $level)` return notifiable models.
`Boi\Backend\Sla\Support\RoleBasedDirectory` covers the common "everyone holding role
X" shape.

## Starting and stopping clocks

```php
$engine = app(SlaEngine::class);

$engine->start('nsdc_review', $application->id);                       // owner clock
$engine->start('business_unit', $application->id, 'assignment');       // pool clock
$engine->reassign('nsdc_review', $application->id, $officer->id);
$engine->complete('nsdc_review', $application->id);                    // stage over
```

`start()` closes any clock of the same type already running on that case, so entering
a stage twice cannot make a case late twice over.

## Running it

Schedule `boi:sla-check` every fifteen minutes. Each level stamps itself, so running
more often only makes it timelier.

## Configuring

`config/boi_sla.php` holds the workday, the holidays, the thresholds and each case
type's defaults — publish it with `--tag=boi-backend-sla`. Seed
`boi_sla_definitions` to put those numbers in front of BOI in Nova instead; a stored
row beats config, and an empty column on that row falls back to it.

Nova resources are included and left for the portal to register and gate:
`Boi\Backend\Nova\SlaDefinition` (editable), `SlaTracker` and `SlaNotificationLog`
(read-only).

## The trail

Every reminder and escalation attempt is recorded in `boi_sla_notification_logs` —
what went out, to whom, on which channel, and whether the channel accepted it. The
listener is registered by this package; registering it again in a portal writes every
row twice.
