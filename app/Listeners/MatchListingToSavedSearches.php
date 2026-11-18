<?php

namespace App\Listeners;

use App\Enums\AlertFrequency;
use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Jobs\SendPendingAlerts;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Reverse match: find every alert-enabled saved search the new listing
 * satisfies, and record one alert per user.
 *
 * Safe to retry: rows are written with insertOrIgnore against the unique
 * (user_id, listing_id) index, so a re-run only fills in what's missing.
 */
class MatchListingToSavedSearches implements ShouldQueue
{
    public int $tries = 3;

    /**
     * Matching is kept apart from mail delivery, so a burst of new listings
     * can't hold up emails (and vice versa).
     */
    public function viaQueue(): string
    {
        return 'matching';
    }

    public function handle(ListingWentLive $event): void
    {
        $listing = $event->listing->loadMissing('branch');

        // It may have gone under offer again before this job ran. Its alerts
        // would be hidden anyway, so don't write them.
        if ($listing->status !== ListingStatus::Live) {
            return;
        }

        // One pass per frequency, most frequent first. When several of a
        // user's searches match, the first pass to reach the user wins the
        // unique index, so the alert is attached to their most frequent search
        // (they asked to hear that often on at least one). This needs no
        // per-user grouping in memory, so results can be streamed in chunks.
        //
        // AlertFrequency::alerting() leaves out Never: those searches are
        // bookmarks, so they produce no rows, and at scale they never enter
        // matching at all.
        foreach (AlertFrequency::alerting() as $frequency) {
            SavedSearch::query()
                ->where('alert_frequency', $frequency)
                ->matching($listing)
                ->select(['id', 'user_id'])
                ->chunkById(500, function (Collection $searches) use ($listing, $frequency): void {
                    $now = now();

                    Alert::query()->insertOrIgnore($searches->map(fn (SavedSearch $search) => [
                        'user_id' => $search->user_id,
                        'listing_id' => $listing->id,
                        'saved_search_id' => $search->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());

                    if ($frequency === AlertFrequency::Instantly) {
                        $this->queueInstantSends($searches->pluck('user_id')->unique()->all());
                    }
                });
        }
    }

    /**
     * Delayed and unique per user, so further matches inside the window join
     * the email that's already queued (see SendPendingAlerts). Dispatching for
     * a user whose alert already existed is harmless: the job sends nothing
     * new.
     *
     * @param  list<int>  $userIds
     */
    private function queueInstantSends(array $userIds): void
    {
        $delay = now()->addMinutes(Config::integer('alerts.instant_debounce_minutes'));

        User::query()->whereKey($userIds)->each(
            fn (User $user) => SendPendingAlerts::dispatch($user)->delay($delay),
        );
    }
}
