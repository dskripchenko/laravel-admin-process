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
];
