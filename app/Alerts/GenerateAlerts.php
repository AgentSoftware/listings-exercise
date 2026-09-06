<?php

namespace App\Alerts;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Finds recently published listings that match saved searches and records an
 * alert for each buyer who should hear about one.
 *
 * This runs on a schedule rather than the moment a listing goes live, because
 * delivery has to be batched — twenty listings published across a Tuesday must
 * not be twenty interruptions. Batching also filters out churn: a listing that
 * goes live and sells before the run simply isn't in the result set, so nobody
 * is told about a property that has already gone.
 *
 * The run is idempotent. It selects on the absence of an alert rather than on a
 * time window, so a failed or skipped run is picked up by the next one and it
 * is always safe to run by hand.
 */
class GenerateAlerts
{
    /**
     * How far back a run looks.
     *
     * This bounds the scan; it is not what makes the run correct. Correctness
     * comes from the "no alert yet" condition below, which means the only cost
     * of a missed run is that it must be caught within this window.
     */
    public const RECOVERY_WINDOW_DAYS = 7;

    /**
     * @return Collection<int, Alert> the alerts created by this run
     */
    public function __invoke(): Collection
    {
        /** @var Collection<int, Alert> $created */
        $created = new Collection;

        Listing::query()
            ->live()
            ->whereNotNull('listed_at')
            ->where('listed_at', '>=', now()->subDays(self::RECOVERY_WINDOW_DAYS))
            // matching() reads the branch region for every listing.
            ->with('branch')
            ->orderBy('id')
            ->each(function (Listing $listing) use ($created): void {
                foreach ($this->searchesAwaitingAlert($listing) as $search) {
                    $alert = Alert::query()->firstOrCreate(
                        ['user_id' => $search->user_id, 'listing_id' => $listing->id],
                        ['saved_search_id' => $search->id],
                    );

                    if ($alert->wasRecentlyCreated) {
                        $created->push($alert);
                    }
                }
            });

        return $created;
    }

    /**
     * The saved searches this listing should alert on — at most one per user.
     *
     * A listing matching two of someone's searches is still one alert; the
     * oldest matching search is credited, so the attribution is stable between
     * runs. The unique index on alerts enforces the same rule in the database,
     * and firstOrCreate above makes a re-run a no-op rather than an error.
     *
     * @return Collection<int, SavedSearch>
     */
    private function searchesAwaitingAlert(Listing $listing): Collection
    {
        return SavedSearch::query()
            ->matching($listing)
            // Skip anyone already alerted about this listing, so a re-run inside
            // the recovery window costs one query rather than a write per search.
            ->whereNotExists(fn (QueryBuilder $alerts) => $alerts
                ->select(DB::raw(1))
                ->from('alerts')
                ->whereColumn('alerts.user_id', 'saved_searches.user_id')
                ->where('alerts.listing_id', $listing->id))
            ->orderBy('id')
            ->get()
            ->unique('user_id');
    }
}
