<?php

use App\Console\Commands\SendAlertDigests;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Digest emails. Every 15 minutes keeps digests close to their cadence; the
// per-user unique job stops this from doubling up with queued instant sends.
Schedule::command(SendAlertDigests::class)->everyFifteenMinutes()->withoutOverlapping();

// Retention: deletes alerts selected by Alert::prunable().
Schedule::command('model:prune')->daily();
