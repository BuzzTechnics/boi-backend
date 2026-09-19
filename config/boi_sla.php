<?php

/**
 * Shared SLA engine for BOI intervention portals.
 *
 * Defaults come from "SPAF Portal Project – Task Reminder and Escalation
 * Notifications", which BOI applies across the funds: weekends and six public
 * holidays excluded, a workday of 08:00–16:00, a reminder at 75% of the SLA and
 * escalations at 100%, 150% and 200%.
 *
 * Everything here is a default. Each portal overrides in its own config, and
 * anything a business owner should be able to change without a deploy belongs in
 * the boi_sla_definitions table instead ({@see Boi\Backend\Sla\Models\SlaDefinition}).
 */
return [
    // Names this portal in shared tables, so one report can cover every fund.
    'app' => env('BOI_APP', env('APP_NAME')),

    // Where the SLA tables live. Null keeps them on the portal's own database;
    // naming a connection — boi-api's — puts every fund's clocks and escalations in
    // one place, told apart by the `app` column above. Its own setting on purpose: a
    // portal reading shared reference data from boi-api has not thereby agreed to
    // keep its audit trail there.
    'connection' => env('BOI_SLA_CONNECTION'),

    'workday' => [
        'start_hour' => (int) env('BOI_SLA_WORKDAY_START_HOUR', 8),
        'end_hour' => (int) env('BOI_SLA_WORKDAY_END_HOUR', 16),
        'holidays' => [
            '01-01', // New Year's Day
            '05-01', // Worker's Day
            '06-12', // Democracy Day
            '10-01', // Independence Day
            '12-25', // Christmas Day
            '12-26', // Boxing Day
        ],
    ],

    // Fractions of an SLA at which each notification fires. A level set to null is
    // not used by this portal — the online portal, for instance, stops at level 2
    // for its own queues and reserves level 3 for SharePoint.
    'thresholds' => [
        'reminder' => 0.75,
        'escalation_1' => 1.0,
        'escalation_2' => 1.5,
        'escalation_3' => 2.0,
    ],

    // Working minutes in one "day" of SLA, for definitions expressed in days.
    'minutes_per_day' => (int) env('BOI_SLA_MINUTES_PER_DAY', 8 * 60),

    /*
     * Fallback SLA definitions, keyed by case type, used when the database holds no
     * row for that key. A portal can run entirely from here; seeding the table is
     * what makes the values editable in Nova.
     *
     *   'nsdc_review' => [
     *       'name' => 'NSDC Application Review',
     *       'minutes' => 2 * 8 * 60,
     *       'assignment_minutes' => null,
     *       'owner_roles' => ['NSDC Reviewer'],
     *       'level_1_roles' => ['Group Head'],
     *       'level_2_roles' => ['Super Admin'],
     *       'level_3_roles' => ['Super Admin'],
     *   ],
     */
    'definitions' => [],

    'notifications' => [
        // Shown in every reminder and escalation as {Loan Application Portal Link}.
        'portal_name' => env('BOI_SLA_PORTAL_NAME', env('APP_NAME', 'BOI Portal')),
    ],

    // Records every reminder and escalation attempt (BRD §5.4). Turn off only for a
    // portal that has its own trail.
    'log_notifications' => (bool) env('BOI_SLA_LOG_NOTIFICATIONS', true),
];
