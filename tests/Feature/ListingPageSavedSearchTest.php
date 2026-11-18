<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The server side of the listings page's "Save search" / "✓ Saved" button and
 * save modal. Everything follows the submitted search (the URL).
 */
class ListingPageSavedSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SavedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->search = SavedSearch::factory()->for($this->user)
            ->forCriteria(['region' => 'Leeds', 'max_price' => 300_000])
            ->create();
    }

    public function test_an_empty_search_has_nothing_to_save(): void
    {
        $this->actingAs($this->user)
            ->get('/?property_type=&region=&per_page=30')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('criteria.is_empty', true)
                ->where('savedSearchId', null)
            );
    }

    public function test_the_url_of_a_saved_search_returns_its_id(): void
    {
        $this->actingAs($this->user)
            ->get('/?region=Leeds&max_price=300000')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('criteria.is_empty', false)
                ->where('savedSearchId', $this->search->id)
            );
    }

    /**
     * Paging and blank fields aren't criteria, so they don't change the match.
     */
    public function test_paging_and_blank_fields_do_not_affect_the_match(): void
    {
        $this->actingAs($this->user)
            ->get('/?max_price=300000&region=Leeds&property_type=&min_bedrooms=&page=2&per_page=30')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchId', $this->search->id));
    }

    #[DataProvider('changedCriteria')]
    public function test_changing_any_criterion_returns_null(string $query): void
    {
        $this->actingAs($this->user)
            ->get("/?{$query}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('criteria.is_empty', false)
                ->where('savedSearchId', null)
            );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function changedCriteria(): array
    {
        return [
            'different price' => ['region=Leeds&max_price=350000'],
            'different region' => ['region=Bristol&max_price=300000'],
            'one criterion removed' => ['region=Leeds'],
            'one criterion added' => ['region=Leeds&max_price=300000&min_bedrooms=2'],
        ];
    }

    public function test_another_users_identical_search_is_not_yours(): void
    {
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get('/?region=Leeds&max_price=300000')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchId', null));
    }

    public function test_saving_then_deleting_flips_the_saved_state(): void
    {
        $url = '/?region=Bristol&property_type=flat';

        $this->actingAs($this->user)
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchId', null));

        $this->actingAs($this->user)
            ->from($url)
            ->post('/saved-searches', ['region' => 'Bristol', 'property_type' => 'flat', 'alert_frequency' => 'instantly'])
            ->assertRedirect($url);

        $saved = $this->user->savedSearches()->latest('id')->firstOrFail();
        $this->actingAs($this->user)
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchId', $saved->id));

        $this->actingAs($this->user)
            ->from($url)
            ->delete("/saved-searches/{$saved->id}")
            ->assertRedirect($url);

        $this->actingAs($this->user)
            ->get($url)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('savedSearchId', null));
    }

    public function test_the_modal_gets_the_summary_default_name_and_frequencies_from_the_server(): void
    {
        $this->actingAs($this->user)
            ->get('/?property_type=flat&region=Manchester&min_bedrooms=2&max_price=200000')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('criteria.summary', 'Flats in Manchester · 2+ beds · up to £200,000')
                ->where('criteria.default_name', 'Manchester')
                ->where('criteria.query', [
                    'property_type' => 'flat',
                    'max_price' => 200000,
                    'min_bedrooms' => 2,
                    'region' => 'Manchester',
                ])
                ->has('alertFrequencies', count(AlertFrequency::cases()))
                ->where('alertFrequencies.0', ['value' => 'never', 'label' => 'Never'])
            );

        $this->actingAs($this->user)
            ->get('/?property_type=flat')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('criteria.default_name', 'All areas'));
    }
}
