<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;

/**
 * Only spots the moment a listing goes live. All matching happens on the queue
 * (MatchListingToSavedSearches), never in the request.
 */
class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Live) {
            ListingWentLive::dispatch($listing);
        }
    }

    public function updated(Listing $listing): void
    {
        if ($listing->wasChanged('status') && $listing->status === ListingStatus::Live) {
            ListingWentLive::dispatch($listing);
        }
    }
}
