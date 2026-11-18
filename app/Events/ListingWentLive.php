<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A listing has just come onto the market: created as live, or its status
 * changed to live.
 *
 * Dispatched after commit, so the queued matching never sees (or misses) a
 * listing whose transaction is later rolled back.
 */
class ListingWentLive implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Listing $listing) {}
}
