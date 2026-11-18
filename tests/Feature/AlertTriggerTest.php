<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Listeners\MatchListingToSavedSearches;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Daily searches are used throughout, so these tests are only about matching,
 * not about instant delivery.
 */
class AlertTriggerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $leeds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leeds = Branch::factory()->create(['region' => 'Leeds']);
    }

    public function test_a_draft_going_live_creates_an_alert(): void
    {
        $listing = Listing::factory()->draft()->for($this->leeds)->create();
        $search = $this->leedsSearch();

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        $alert = Alert::query()->sole();
        $this->assertSame($search->user_id, $alert->user_id);
        $this->assertSame($listing->id, $alert->listing_id);
        $this->assertSame($search->id, $alert->saved_search_id);
        $this->assertNull($alert->notified_at);
        $this->assertNull($alert->read_at);
    }

    public function test_a_listing_created_as_live_creates_an_alert(): void
    {
        $this->leedsSearch();

        Listing::factory()->live()->for($this->leeds)->create();

        $this->assertSame(1, Alert::count());
    }

    public function test_a_live_listing_being_sold_creates_no_alert(): void
    {
        $listing = Listing::factory()->live()->for($this->leeds)->create();
        $this->leedsSearch();

        $listing->update(['status' => ListingStatus::Sold]);

        $this->assertSame(0, Alert::count());
    }

    public function test_other_changes_to_a_live_listing_create_no_alert(): void
    {
        $listing = Listing::factory()->live()->for($this->leeds)->create();
        $this->leedsSearch();

        $listing->update(['price' => $listing->price - 10_000]);

        $this->assertSame(0, Alert::count());
    }

    /**
     * Deliberate anti-spam choice: live → under offer → live again is the same
     * listing, so the unique index stops a second alert.
     */
    public function test_going_live_again_does_not_create_a_second_alert(): void
    {
        $this->leedsSearch();
        $listing = Listing::factory()->live()->for($this->leeds)->create();

        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        $this->assertSame(1, Alert::count());
    }

    public function test_listings_already_live_when_the_search_is_saved_are_not_backfilled(): void
    {
        Listing::factory(3)->live()->for($this->leeds)->create();

        $this->leedsSearch();

        $this->assertSame(0, Alert::count());
    }

    public function test_never_searches_produce_no_alerts(): void
    {
        SavedSearch::factory()->never()->forCriteria(['region' => 'Leeds'])->create();

        Listing::factory()->live()->for($this->leeds)->create();

        $this->assertSame(0, Alert::count());
    }

    public function test_searches_that_do_not_match_produce_no_alerts(): void
    {
        SavedSearch::factory()->daily()->forCriteria(['region' => 'Bristol'])->create();

        Listing::factory()->live()->for($this->leeds)->create();

        $this->assertSame(0, Alert::count());
    }

    public function test_overlapping_searches_give_one_alert_attached_to_the_most_frequent(): void
    {
        $user = User::factory()->create();
        // The less frequent search is created first, so a lower id doesn't win.
        SavedSearch::factory()->for($user)->every7Days()->forCriteria(['region' => 'Leeds'])->create();
        SavedSearch::factory()->for($user)->every3Days()->forCriteria(['max_price' => 900_000])->create();
        $daily = SavedSearch::factory()->for($user)->daily()->forCriteria(['region' => 'Leeds', 'min_bedrooms' => 1])->create();
        $otherUsersSearch = $this->leedsSearch();

        $listing = Listing::factory()->live()->for($this->leeds)->create(['price' => 300_000, 'bedrooms' => 2]);

        $this->assertSame(2, Alert::count(), 'One alert per user.');
        $this->assertSame($daily->id, Alert::query()->where('user_id', $user->id)->where('listing_id', $listing->id)->sole()->saved_search_id);
        $this->assertSame($otherUsersSearch->id, Alert::query()->where('user_id', $otherUsersSearch->user_id)->sole()->saved_search_id);
    }

    public function test_matching_is_safe_to_repeat(): void
    {
        $this->leedsSearch();
        $listing = Listing::factory()->live()->for($this->leeds)->create();

        app(MatchListingToSavedSearches::class)->handle(new ListingWentLive($listing));

        $this->assertSame(1, Alert::count());
    }

    public function test_the_observer_only_dispatches_the_event_when_a_listing_goes_live(): void
    {
        Event::fake([ListingWentLive::class]);

        $draft = Listing::factory()->draft()->for($this->leeds)->create();
        $draft->update(['price' => 100_000]);
        Event::assertNotDispatched(ListingWentLive::class);

        $draft->update(['status' => ListingStatus::Live]);
        Event::assertDispatchedTimes(ListingWentLive::class, 1);

        Listing::factory()->live()->for($this->leeds)->create();
        Event::assertDispatchedTimes(ListingWentLive::class, 2);
    }

    public function test_matching_runs_on_the_matching_queue_and_not_in_the_request(): void
    {
        Queue::fake();
        $this->leedsSearch();

        Listing::factory()->live()->for($this->leeds)->create();

        Queue::assertPushedOn('matching', CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === MatchListingToSavedSearches::class);
        $this->assertSame(0, Alert::count());
    }

    private function leedsSearch(): SavedSearch
    {
        return SavedSearch::factory()->daily()->forCriteria(['region' => 'Leeds'])->create();
    }
}
