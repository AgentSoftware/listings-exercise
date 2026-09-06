<?php

namespace App\Console\Commands;

use App\Alerts\GenerateAlerts;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewListingMatches;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class DispatchAlerts extends Command
{
    protected $signature = 'alerts:dispatch';

    protected $description = 'Alert buyers about new listings matching their saved searches';

    public function handle(GenerateAlerts $generateAlerts): int
    {
        $alerts = $generateAlerts();

        if ($alerts->isEmpty()) {
            $this->info('No new matches.');

            return self::SUCCESS;
        }

        $alerts->load('listing.branch', 'user');

        /** @var \Illuminate\Support\Collection<int, Collection<int, Alert>> $byUser */
        $byUser = $alerts->groupBy('user_id');

        foreach ($byUser as $theirs) {
            /** @var User $user */
            $user = $theirs->first()->user;

            // One message per buyer per run, covering every match. Sending per
            // alert here would undo the reason this runs on a schedule at all.
            $user->notify(new NewListingMatches($theirs));
        }

        // "queued", not "sent": NewListingMatches is a queued notification, so
        // on the default database queue these are delivered by a worker.
        $this->info(sprintf(
            '%d alert(s) across %d listing(s); %d digest(s) queued.',
            $alerts->count(),
            $alerts->unique('listing_id')->count(),
            $byUser->count(),
        ));

        return self::SUCCESS;
    }
}
