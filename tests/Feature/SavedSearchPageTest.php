<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SavedSearchPageTest extends TestCase
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

    public function test_index_lists_only_the_users_own_searches(): void
    {
        $user = $this->demoUser();
        $someoneElse = User::factory()->create();

        SavedSearch::factory(2)->for($user)->create(['region' => 'Leeds']);
        SavedSearch::factory(3)->for($someoneElse)->create(['region' => 'Leeds']);

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 2)
                ->has('branches')
                ->has('propertyTypes')
            );
    }

    public function test_index_describes_each_search_and_links_to_its_matches(): void
    {
        $user = $this->demoUser();
        SavedSearch::factory()->for($user)->create([
            'property_type' => PropertyType::Flat,
            'max_price' => 300_000,
            'region' => 'Leeds',
        ]);

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('savedSearches.0.label', 'Flat · Leeds · up to £300,000')
                ->where('savedSearches.0.criteria', [
                    'property_type' => 'flat',
                    'max_price' => 300_000,
                    'region' => 'Leeds',
                ])
                ->where('savedSearches.0.alerts_count', 0)
            );
    }

    public function test_a_user_can_save_a_search(): void
    {
        $user = $this->demoUser();

        $this->post('/saved-searches', [
            'name' => 'Near Mum',
            'property_type' => 'flat',
            'max_price' => '300000',
            'region' => 'Leeds',
        ])->assertRedirect('/?property_type=flat&max_price=300000&region=Leeds');

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $user->id,
            'name' => 'Near Mum',
            'property_type' => 'flat',
            'max_price' => 300_000,
            'min_bedrooms' => null,
            'region' => 'Leeds',
        ]);
    }

    /**
     * An empty search matches every new listing, which is exactly the thing
     * Support asked us not to build.
     */
    public function test_a_search_with_no_criteria_is_rejected(): void
    {
        $this->demoUser();

        $this->post('/saved-searches', [
            'name' => 'Everything',
            'property_type' => '',
            'max_price' => '',
            'min_bedrooms' => '',
            'region' => '',
        ])->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    /**
     * "0 or more bedrooms" matches every listing, so it must not count as the
     * one criterion that gets a search past the guard — filled() says a
     * present 0 is a value, which is how this got through.
     */
    public function test_a_minimum_of_zero_bedrooms_is_not_a_criterion(): void
    {
        $this->demoUser();

        $this->post('/saved-searches', ['min_bedrooms' => '0'])
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_a_zero_minimum_is_dropped_rather_than_saved(): void
    {
        $this->demoUser();

        $this->post('/saved-searches', ['region' => 'Leeds', 'min_bedrooms' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('saved_searches', [
            'region' => 'Leeds',
            'min_bedrooms' => null,
        ]);
    }

    public function test_a_search_is_validated_the_same_way_as_the_browse_filters(): void
    {
        $this->demoUser();

        $this->post('/saved-searches', ['property_type' => 'castle'])
            ->assertSessionHasErrors('property_type');

        $this->post('/saved-searches', ['min_bedrooms' => 99])
            ->assertSessionHasErrors('min_bedrooms');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_a_user_can_delete_their_own_search(): void
    {
        $user = $this->demoUser();
        $search = SavedSearch::factory()->for($user)->create(['region' => 'Leeds']);

        $this->delete("/saved-searches/{$search->id}")->assertRedirect();

        $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
    }

    public function test_a_user_cannot_delete_someone_elses_search(): void
    {
        $this->demoUser();
        $theirs = SavedSearch::factory()->for(User::factory())->create(['region' => 'Leeds']);

        $this->delete("/saved-searches/{$theirs->id}")->assertNotFound();

        $this->assertDatabaseHas('saved_searches', ['id' => $theirs->id]);
    }

    /**
     * The auth stub resolves to null until the database is seeded, which is the
     * same shape as an unauthenticated request — user-scoped pages must refuse
     * it rather than fail on a null.
     */
    public function test_user_scoped_pages_refuse_a_request_with_no_user(): void
    {
        $this->get('/saved-searches')->assertForbidden();
        $this->post('/saved-searches', ['region' => 'Leeds'])->assertForbidden();
    }
}
