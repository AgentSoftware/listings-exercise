<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instant alert debounce
    |--------------------------------------------------------------------------
    |
    | An "Instantly" alert is emailed this many minutes after it's created, so
    | a burst of new listings becomes one email rather than several.
    |
    */

    'instant_debounce_minutes' => (int) env('ALERTS_INSTANT_DEBOUNCE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Alerts older than retention_days are pruned. Read alerts go sooner, after
    | read_retention_days. See Alert::prunable().
    |
    */

    'retention_days' => (int) env('ALERTS_RETENTION_DAYS', 90),

    'read_retention_days' => (int) env('ALERTS_READ_RETENTION_DAYS', 30),

];
