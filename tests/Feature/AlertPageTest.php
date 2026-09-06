<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The auth stub resolves the first user by id, so this is "the signed-in
     * buyer" and any user created afterwards is somebody else.
     */
    private function buyer(): User
    {
        return User::factory()->create();
    }

    public function test_it_lists_only_the_buyers_own_alerts(): void
    {
        $buyer = $this->buyer();
        Alert::factory(2)->for($buyer)->create();
        Alert::factory(3)->for(User::factory())->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
            );
    }

    public function test_it_shows_newest_first(): void
    {
        $buyer = $this->buyer();
        $older = Alert::factory()->for($buyer)->create();
        $newer = Alert::factory()->for($buyer)->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
            );
    }

    public function test_an_alert_names_the_search_that_matched_and_the_listing(): void
    {
        $buyer = $this->buyer();
        $search = SavedSearch::factory()->for($buyer)->create(['region' => 'Leeds']);
        $listing = Listing::factory()->live()->create();

        Alert::factory()->for($buyer)->create([
            'listing_id' => $listing->id,
            'saved_search_id' => $search->id,
        ]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.saved_search_label', 'Leeds')
                ->where('alerts.data.0.listing.id', $listing->id)
                ->where('alerts.data.0.read_at', null)
            );
    }

    /**
     * Deleting a search must not retract alerts the buyer has already seen, so
     * the alert survives with nothing to attribute it to.
     */
    public function test_an_alert_outlives_the_search_that_produced_it(): void
    {
        $buyer = $this->buyer();
        $search = SavedSearch::factory()->for($buyer)->create(['region' => 'Leeds']);
        $alert = Alert::factory()->for($buyer)->create(['saved_search_id' => $search->id]);

        $search->delete();

        $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'saved_search_id' => null]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.saved_search_label', null)
            );
    }

    public function test_the_unread_count_is_shared_with_every_page(): void
    {
        $buyer = $this->buyer();
        Alert::factory(2)->for($buyer)->create();
        Alert::factory()->for($buyer)->read()->create();
        Alert::factory(5)->for(User::factory())->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.unread_count', 2)
            );
    }

    public function test_a_buyer_can_mark_all_their_alerts_read(): void
    {
        $buyer = $this->buyer();
        Alert::factory(3)->for($buyer)->create();
        $theirs = Alert::factory()->for(User::factory())->create();

        $this->post('/alerts/read')->assertRedirect();

        $this->assertSame(0, $buyer->alerts()->unread()->count());
        $this->assertNull($theirs->refresh()->read_at, 'Must not touch anyone else’s alerts.');
    }

    public function test_alerts_are_refused_when_there_is_no_user(): void
    {
        $this->get('/alerts')->assertForbidden();
        $this->post('/alerts/read')->assertForbidden();
    }
}
