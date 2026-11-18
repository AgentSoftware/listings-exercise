<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_only_the_users_own_alerts_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Alert::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
        $newer = Alert::factory()->for($user)->create(['created_at' => now()->subDay()]);
        Alert::factory()->create(); // someone else's

        $this->actingAs($user)
            ->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.0.listing.id', $newer->listing_id)
                ->where('alerts.data.0.search_summary', $newer->savedSearch?->criteria()->describe())
                ->where('alerts.data.1.id', $older->id)
                ->has('alerts.meta')
                ->has('alerts.links')
            );
    }

    public function test_alerts_for_listings_that_are_no_longer_live_are_hidden_until_they_are_live_again(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->for($user)->create();

        $alert->listing->update(['status' => ListingStatus::UnderOffer]);

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 0)
                ->where('auth.unread_alerts', 0)
            );

        $alert->listing->update(['status' => ListingStatus::Live]);

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.id', $alert->id)
            );
    }

    public function test_viewing_marks_the_displayed_alerts_as_read_but_still_highlights_them_once(): void
    {
        $user = User::factory()->create();
        $unread = Alert::factory()->for($user)->create(['created_at' => now()->subHour()]);
        $read = Alert::factory()->for($user)->read()->create(['created_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.unread_alerts', 1));

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.id', $unread->id)
                ->where('alerts.data.0.is_unread', true)
                ->where('alerts.data.1.id', $read->id)
                ->where('alerts.data.1.is_unread', false)
                // The nav count is resolved after viewing.
                ->where('auth.unread_alerts', 0)
            );

        $this->assertNotNull($unread->fresh()?->read_at);

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('alerts.data.0.is_unread', false));
    }

    public function test_only_the_displayed_page_is_marked_as_read(): void
    {
        $user = User::factory()->create();
        Alert::factory(16)->for($user)->sequence(fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index)])->create();

        $this->actingAs($user)->get('/alerts');

        $this->assertSame(1, $user->alerts()->unread()->count());
    }

    public function test_an_alert_from_a_deleted_search_is_still_shown(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->for($user)->create();

        $alert->savedSearch?->delete();

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.id', $alert->id)
                ->where('alerts.data.0.search_summary', null)
            );
    }
}
