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
     * user" and any user created afterwards is somebody else.
     */
    private function demoUser(): User
    {
        return User::factory()->create();
    }

    public function test_it_lists_only_the_users_own_alerts(): void
    {
        $user = $this->demoUser();
        Alert::factory(2)->for($user)->create();
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
        $user = $this->demoUser();
        $older = Alert::factory()->for($user)->create();
        $newer = Alert::factory()->for($user)->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
            );
    }

    public function test_an_alert_names_the_search_that_matched_and_the_listing(): void
    {
        $user = $this->demoUser();
        $search = SavedSearch::factory()->for($user)->create(['region' => 'Leeds']);
        $listing = Listing::factory()->live()->create();

        Alert::factory()->for($user)->create([
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
     * Deleting a search must not retract alerts the user has already seen, so
     * the alert survives with nothing to attribute it to.
     */
    public function test_an_alert_outlives_the_search_that_produced_it(): void
    {
        $user = $this->demoUser();
        $search = SavedSearch::factory()->for($user)->create(['region' => 'Leeds']);
        $alert = Alert::factory()->for($user)->create(['saved_search_id' => $search->id]);

        $search->delete();

        $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'saved_search_id' => null]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.saved_search_label', null)
            );
    }

    /**
     * Every page, the alerts page included — a page prop silently overrides a
     * shared one of the same name, which is how the badge went missing here.
     */
    /**
     * A property can sell after we alerted on it. The alert stays — we did tell
     * the user about it — so the page has to cope with a listing its own
     * detail route would now 404 on.
     */
    public function test_an_alert_survives_the_listing_leaving_the_market(): void
    {
        $user = $this->demoUser();
        $listing = Listing::factory()->sold()->create();
        Alert::factory()->for($user)->create(['listing_id' => $listing->id]);

        $this->get("/listings/{$listing->id}")->assertNotFound();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing.status', 'sold')
            );
    }

    public function test_the_unread_count_is_shared_with_every_page(): void
    {
        $user = $this->demoUser();
        Alert::factory(2)->for($user)->create();
        Alert::factory()->for($user)->read()->create();
        Alert::factory(5)->for(User::factory())->create();

        foreach (['/', '/saved-searches', '/alerts'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('unreadAlerts', 2)
                );
        }
    }

    public function test_a_user_can_mark_all_their_alerts_read(): void
    {
        $user = $this->demoUser();
        Alert::factory(3)->for($user)->create();
        $theirs = Alert::factory()->for(User::factory())->create();

        $this->post('/alerts/read')->assertRedirect();

        $this->assertSame(0, $user->alerts()->unread()->count());
        $this->assertNull($theirs->refresh()->read_at, 'Must not touch anyone else’s alerts.');
    }

    public function test_alerts_are_refused_when_there_is_no_user(): void
    {
        $this->get('/alerts')->assertForbidden();
        $this->post('/alerts/read')->assertForbidden();
    }
}
