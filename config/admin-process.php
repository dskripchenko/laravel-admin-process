<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Refresh intervals, seconds
    |--------------------------------------------------------------------------
    | `list` — the instance and incident lists; `flow` — the diagram on the
    | instance page (at least 1); `widget` — the overview counters. 0 or null
    | turns the auto-refresh of a list or the widget off.
    */
    'refresh' => [
        'list' => 15,
        'flow' => 5,
        'widget' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal
    |--------------------------------------------------------------------------
    | How many latest journal entries the instance page shows.
    */
    'journal_limit' => 200,

    /*
    |--------------------------------------------------------------------------
    | Processes started by hand
    |--------------------------------------------------------------------------
    | Process classes the diagram page may start (permission
    | admin.process.start): Class::class, or Class::class => [the context the
    | start form opens with]. Stages of switchable states can be switched off
    | for that one run.
    */
    'startable' => [],

    /*
    |--------------------------------------------------------------------------
    | Health checks (with dskripchenko/laravel-admin-health installed)
    |--------------------------------------------------------------------------
    | process.incidents — open incidents: warning from `warning`, failing from
    | `failing`. process.unknown_effects — effects of unknown outcome older
    | than `warning_after` / `failing_after`. process.timers — overdue timers
    | (the scheduler does not run). process.queues — events and due effects
    | waiting longer than `warning_after` / `failing_after` (the workers
    | stand). Each also takes `frequency` (1m, 5m, 15m, 1h) and `timeout`.
    */
    'health' => [
        'enabled' => true,
        'incidents' => ['warning' => 1, 'failing' => 10],
        'unknown_effects' => ['warning_after' => '15 minutes', 'failing_after' => '1 hour'],
        'timers' => ['failing' => 1],
        'queues' => ['warning_after' => '5 minutes', 'failing_after' => '15 minutes'],
    ],
];
