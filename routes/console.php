<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alerts are generated and delivered once a day rather than as listings go
// live, so a buyer gets one digest instead of a message per property. The
// evening slot means a day's listings arrive together.
//
// The command is idempotent, so running it by hand to demonstrate the feature
// is safe — see the README.
Schedule::command('alerts:dispatch')->dailyAt('18:00');
