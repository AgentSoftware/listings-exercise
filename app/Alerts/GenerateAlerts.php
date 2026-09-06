<?php

namespace App\Alerts;

use App\Enums\ListingStatus;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Records an alert for every user whose saved search matches a recently
 * published listing.
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
 *
 * It is deliberately one statement. Matching listings against saved searches is
 * a join, and doing it as a join keeps the whole run to a single round trip and
 * a single write, rather than a query per listing and two more per alert. See
 * NOTES.md for the numbers and for where this still doesn't scale.
 */
class GenerateAlerts
{
    /**
     * How far back a run looks.
     *
     * This bounds the scan; it is not what makes the run correct. Correctness
     * comes from the "no alert yet" condition, which means the only cost of a
     * missed run is that it must be caught within this window.
     */
    public const RECOVERY_WINDOW_DAYS = 7;

    /**
     * @return int the number of alerts created
     */
    public function __invoke(): int
    {
        $now = now()->toDateTimeString();

        // insertOrIgnore rather than insert: the NOT EXISTS below and the unique
        // index on (user_id, listing_id) can disagree if a second run overlaps
        // this one — a manual run alongside the scheduled one, say. Ignoring the
        // collision is exactly right, because the alert already exists.
        return DB::table('alerts')->insertOrIgnoreUsing(
            ['user_id', 'listing_id', 'saved_search_id', 'created_at', 'updated_at'],
            $this->unalertedMatches($now),
        );
    }

    /**
     * Every (user, listing) pair that should produce an alert and hasn't yet.
     *
     * This is ListingCriteria::applyTo() turned inside out and applied to every
     * new listing at once. A null criterion means "any", so each one is an
     * IS NULL OR comparison.
     *
     * Note this restates those comparisons rather than reusing the value
     * object: matching every new listing against every saved search in one
     * statement is not something an object built for one query at a time can
     * express. So a new criterion has to be added in both places, and only
     * Tests\Support\CriteriaOracle will tell you if they drift.
     */
    private function unalertedMatches(string $now): QueryBuilder
    {
        return DB::table('listings as l')
            ->join('branches as b', 'b.id', '=', 'l.branch_id')
            ->join('saved_searches as s', function (JoinClause $join) {
                $join
                    ->where(fn (JoinClause $q) => $q
                        ->whereNull('s.property_type')
                        ->orWhereColumn('s.property_type', 'l.property_type'))
                    ->where(fn (JoinClause $q) => $q
                        ->whereNull('s.max_price')
                        ->orWhereColumn('s.max_price', '>=', 'l.price'))
                    ->where(fn (JoinClause $q) => $q
                        ->whereNull('s.min_bedrooms')
                        ->orWhereColumn('s.min_bedrooms', '<=', 'l.bedrooms'))
                    ->where(fn (JoinClause $q) => $q
                        ->whereNull('s.region')
                        ->orWhereColumn('s.region', 'b.region'))
                    // The backfill policy: you hear about listings that go live
                    // after you save a search, not the whole market.
                    ->whereColumn('s.created_at', '<=', 'l.listed_at');
            })
            ->where('l.status', ListingStatus::Live->value)
            ->whereNotNull('l.listed_at')
            ->where('l.listed_at', '>=', now()->subDays(self::RECOVERY_WINDOW_DAYS))
            ->whereNotExists(fn (QueryBuilder $alerts) => $alerts
                ->select(DB::raw(1))
                ->from('alerts as a')
                ->whereColumn('a.user_id', 's.user_id')
                ->whereColumn('a.listing_id', 'l.id'))
            // One alert per user per listing, however many of their searches
            // matched — the anti-spam rule, applied in the database rather than
            // by pulling every matching search into PHP to deduplicate.
            //
            // GROUP BY needs an aggregate for the search id regardless; min()
            // makes it the oldest matching search rather than whichever row the
            // database reached first. That is a tie-break within this run only.
            // A later run never revisits the pair, because the NOT EXISTS above
            // sees the alert — so the credit can't change once it is written.
            ->groupBy('s.user_id', 'l.id')
            ->selectRaw('s.user_id, l.id, min(s.id), ?, ?', [$now, $now]);
    }
}
