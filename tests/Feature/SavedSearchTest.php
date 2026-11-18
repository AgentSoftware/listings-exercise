<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_a_search(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/?region=Leeds&max_price=300000')
            ->post('/saved-searches', [
                'region' => 'Leeds',
                'max_price' => '300000',
                'name' => 'Leeds starter homes',
                'alert_frequency' => 'daily',
            ])
            ->assertRedirect('/?region=Leeds&max_price=300000')
            ->assertSessionHasNoErrors();

        $search = $user->savedSearches()->sole();
        $this->assertSame('Leeds starter homes', $search->name);
        $this->assertSame('Leeds', $search->region);
        $this->assertSame(300000, $search->max_price);
        $this->assertNull($search->property_type);
        $this->assertNull($search->min_bedrooms);
        $this->assertSame(AlertFrequency::Daily, $search->alert_frequency);
    }

    /**
     * @param  array<string, string>  $criteria
     */
    #[DataProvider('defaultNames')]
    public function test_a_blank_name_defaults_to_the_region_or_all_areas(array $criteria, string $expected): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', [...$criteria, 'name' => '', 'alert_frequency' => 'never'])
            ->assertSessionHasNoErrors();

        $this->assertSame($expected, $user->savedSearches()->sole()->name);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function defaultNames(): array
    {
        return [
            'with a region' => [['region' => 'Bristol', 'min_bedrooms' => '2'], 'Bristol'],
            'without a region' => [['property_type' => 'flat'], 'All areas'],
        ];
    }

    public function test_a_search_needs_at_least_one_criterion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['region' => '', 'alert_frequency' => 'instantly'])
            ->assertSessionHasErrors('criteria')
            ->assertSessionMissing('message');

        $this->assertSame(0, SavedSearch::count());
    }

    public function test_an_identical_saved_search_is_rejected(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->forCriteria(['region' => 'Leeds', 'property_type' => 'flat'])->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['region' => 'Leeds', 'property_type' => 'flat', 'alert_frequency' => 'daily'])
            ->assertSessionHasErrors('criteria')
            ->assertSessionMissing('message');

        $this->assertSame(1, SavedSearch::count());
    }

    public function test_a_search_that_differs_by_one_criterion_is_not_a_duplicate(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->forCriteria(['region' => 'Leeds', 'property_type' => 'flat'])->create();

        // A subset of the existing criteria: the unset property_type has to be
        // compared as NULL, not skipped.
        $this->actingAs($user)
            ->post('/saved-searches', ['region' => 'Leeds', 'alert_frequency' => 'daily'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $user->savedSearches()->count());
    }

    public function test_another_users_identical_search_is_not_a_duplicate(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->forCriteria(['region' => 'Leeds'])->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['region' => 'Leeds', 'alert_frequency' => 'daily'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $user->savedSearches()->count());
    }

    public function test_alert_frequency_is_required_and_must_be_known(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['region' => 'Leeds', 'alert_frequency' => 'hourly'])
            ->assertSessionHasErrors('alert_frequency');
    }

    public function test_alerts_are_off_unless_a_frequency_is_chosen(): void
    {
        $user = User::factory()->create();

        $search = $user->savedSearches()->create(['region' => 'Leeds'])->fresh();

        $this->assertSame(AlertFrequency::Never, $search?->alert_frequency);
    }

    public function test_the_index_lists_the_users_searches_with_summary_alert_status_and_results_link(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->daily()->forCriteria([
            'region' => 'Manchester', 'property_type' => 'flat', 'min_bedrooms' => 2, 'max_price' => 200000,
        ])->create(['name' => 'Manchester flats', 'created_at' => now()->subDay()]);
        SavedSearch::factory()->for($user)->never()->forCriteria(['region' => 'Leeds'])->create(['name' => 'Leeds']);
        SavedSearch::factory()->create(); // someone else's

        $this->actingAs($user)
            ->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 2)
                ->where('savedSearches.0.name', 'Leeds')
                ->where('savedSearches.0.alerts_enabled', false)
                ->where('savedSearches.0.results_url', '/?region=Leeds')
                ->where('savedSearches.1.name', 'Manchester flats')
                ->where('savedSearches.1.summary', 'Flats in Manchester · 2+ beds · up to £200,000')
                ->where('savedSearches.1.alerts_enabled', true)
                ->where('savedSearches.1.alert_frequency.value', 'daily')
                ->where('savedSearches.1.alert_frequency.label', 'Daily')
                ->where('savedSearches.1.results_url', '/?property_type=flat&max_price=200000&min_bedrooms=2&region=Manchester')
            );
    }

    public function test_a_user_can_delete_their_saved_search(): void
    {
        $owner = User::factory()->create();
        $search = SavedSearch::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->from('/saved-searches')
            ->delete("/saved-searches/{$search->id}")
            ->assertRedirect('/saved-searches');

        $this->assertModelMissing($search);
    }

    public function test_a_user_cannot_delete_another_users_saved_search(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $search = SavedSearch::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->delete("/saved-searches/{$search->id}")
            ->assertForbidden();

        $this->assertModelExists($search);
    }

    public function test_deleting_a_search_that_no_longer_exists_redirects_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/?region=Leeds')
            ->delete('/saved-searches/999')
            ->assertRedirect('/?region=Leeds')
            ->assertSessionMissing('message');
    }

    public function test_saving_flashes_a_message_that_is_shared_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/?region=Leeds')
            ->post('/saved-searches', ['region' => 'Leeds', 'alert_frequency' => 'instantly'])
            ->assertSessionHas('message', 'Search saved');

        // Following the redirect: HandleInertiaRequests shares the message…
        $this->get('/?region=Leeds')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('flash.message', 'Search saved'));

        // …and only once.
        $this->get('/?region=Leeds')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('flash.message', null));
    }

    /**
     * The listings page's "✓ Saved" button and the saved searches page use the
     * same route, so both get the same message and land back where they were.
     */
    #[DataProvider('deleteOrigins')]
    public function test_deleting_redirects_back_with_the_same_message(string $from): void
    {
        $owner = User::factory()->create();
        $search = SavedSearch::factory()->for($owner)->forCriteria(['region' => 'Leeds'])->create();

        $this->actingAs($owner)
            ->from($from)
            ->delete("/saved-searches/{$search->id}")
            ->assertRedirect($from)
            ->assertSessionHas('message', 'Saved search removed');

        $this->get($from)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('flash.message', 'Saved search removed'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleteOrigins(): array
    {
        return [
            'listings page' => ['/?region=Leeds'],
            'saved searches page' => ['/saved-searches'],
        ];
    }

    public function test_saved_searches_store_the_property_type_as_an_enum(): void
    {
        $search = SavedSearch::factory()->forCriteria(['property_type' => 'flat'])->create()->fresh();

        $this->assertSame(PropertyType::Flat, $search?->property_type);
    }
}
