<?php

namespace Tests\Feature;

use App\Alerts\GenerateAlerts;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CriteriaOracle;
use Tests\TestCase;

class GenerateAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_alerts_a_user_whose_saved_search_the_listing_matches(): void
    {
        $user = User::factory()->create();
        $search = $this->savedSearch($user, ['region' => 'Leeds', 'max_price' => 300_000]);
        $listing = $this->liveListing();

        $created = $this->generate();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('alerts', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'saved_search_id' => $search->id,
            'read_at' => null,
        ]);
    }

    public function test_it_does_not_alert_when_the_listing_misses_the_criteria(): void
    {
        $this->savedSearch(User::factory()->create(), ['max_price' => 200_000]);
        $this->liveListing(['price' => 250_000]);

        $this->generate();

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_every_user_with_a_matching_search_is_alerted(): void
    {
        $this->savedSearch(User::factory()->create(), ['region' => 'Leeds']);
        $this->savedSearch(User::factory()->create(), ['min_bedrooms' => 2]);
        $this->savedSearch(User::factory()->create(), ['region' => 'Manchester']);

        $this->liveListing();

        $this->assertSame(2, $this->generate());
        $this->assertDatabaseCount('alerts', 2);
    }

    /**
     * The browse pages only expose live listings, so alerts must not become a
     * way to hear about a draft — or about something already sold.
     */
    #[DataProvider('nonLiveStates')]
    public function test_it_only_alerts_on_live_listings(string $state): void
    {
        $this->savedSearch(User::factory()->create());

        // listed_at is forced recent so that status is the only thing keeping
        // this listing out of the run.
        Listing::factory()->{$state}()->for(Branch::factory()->create())->create([
            'listed_at' => now()->subHour(),
        ]);

        $this->generate();

        $this->assertDatabaseCount('alerts', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLiveStates(): array
    {
        return [
            'draft' => ['draft'],
            'under offer' => ['underOffer'],
            'sold' => ['sold'],
        ];
    }

    /**
     * Support's constraint, at the record level: matching two of someone's
     * searches is still one property, so it is still one alert.
     */
    public function test_a_listing_matching_two_of_a_users_searches_is_one_alert(): void
    {
        $user = User::factory()->create();
        $first = $this->savedSearch($user, ['region' => 'Leeds']);
        $this->savedSearch($user, ['max_price' => 300_000]);

        $this->liveListing();

        $this->assertSame(1, $this->generate());
        $this->assertDatabaseCount('alerts', 1);

        // Attribution is stable rather than arbitrary: the older search wins.
        $this->assertDatabaseHas('alerts', ['saved_search_id' => $first->id]);
    }

    public function test_running_again_creates_nothing_further(): void
    {
        $this->savedSearch(User::factory()->create(), ['region' => 'Leeds']);
        $this->liveListing();

        $this->assertSame(1, $this->generate());
        $this->assertSame(0, $this->generate(), 'A second run must be a no-op.');
        $this->assertDatabaseCount('alerts', 1);
    }

    /**
     * The backfill decision: saving a search asks to hear what comes next. The
     * saved-searches page links to the browse results for what is already live.
     */
    public function test_it_ignores_listings_that_went_live_before_the_search_was_saved(): void
    {
        $this->savedSearch(User::factory()->create(), ['created_at' => now()->subHour()]);
        $this->liveListing(['listed_at' => now()->subDay()]);

        $this->generate();

        $this->assertDatabaseCount('alerts', 0);
    }

    /**
     * The run selects on the absence of an alert, not on "published today", so
     * a day the job didn't run is caught up rather than lost.
     */
    public function test_a_missed_run_is_picked_up_by_the_next_one(): void
    {
        $this->savedSearch(User::factory()->create(), ['region' => 'Leeds']);
        $this->liveListing(['listed_at' => now()->subDays(6)]);

        $this->assertSame(1, $this->generate());
    }

    public function test_it_stops_looking_at_the_edge_of_the_recovery_window(): void
    {
        $this->savedSearch(User::factory()->create(), ['created_at' => now()->subYear()]);
        $this->liveListing([
            'listed_at' => now()->subDays(GenerateAlerts::RECOVERY_WINDOW_DAYS + 1),
        ]);

        $this->generate();

        $this->assertDatabaseCount('alerts', 0);
    }

    /**
     * @return int the number of alerts created
     */
    private function generate(): int
    {
        return app(GenerateAlerts::class)();
    }

    /**
     * The join in GenerateAlerts is ListingCriteria::applyTo() turned inside out
     * and applied to every new listing at once. Inverting a query by hand is
     * exactly the kind of thing that looks right and isn't, and this is the one
     * a user's alerts actually depend on — so assert it against CriteriaOracle,
     * across every combination of set and "any", rather than by eye.
     *
     * Each search belongs to a different user, so the one-alert-per-user rule
     * cannot mask a disagreement.
     */
    public function test_it_alerts_exactly_the_users_whose_criteria_match(): void
    {
        $searches = $this->savedSearchGrid();
        $listings = $this->listingGrid();

        $this->generate();

        $alerted = Alert::query()->get()
            ->map(fn (Alert $alert) => "{$alert->user_id}:{$alert->listing_id}")
            ->flip();

        $matches = 0;

        foreach ($listings as $listing) {
            foreach ($searches as $search) {
                $expected = CriteriaOracle::matches($search->criteria(), $listing);
                $matches += (int) $expected;

                $this->assertSame(
                    $expected,
                    $alerted->has("{$search->user_id}:{$listing->id}"),
                    "Search {$search->id} vs listing {$listing->id}.",
                );
            }
        }

        $this->assertGreaterThan(0, $matches, 'Fixture should produce matches.');
    }

    /**
     * Every combination of "set" and "any" across the four criteria, one per
     * user.
     *
     * @return Collection<int, SavedSearch>
     */
    private function savedSearchGrid(): Collection
    {
        foreach ([null, PropertyType::Flat] as $type) {
            foreach ([null, 300_000] as $maxPrice) {
                foreach ([null, 3] as $minBedrooms) {
                    foreach ([null, 'Leeds'] as $region) {
                        SavedSearch::factory()->for(User::factory())->create([
                            'property_type' => $type,
                            'max_price' => $maxPrice,
                            'min_bedrooms' => $minBedrooms,
                            'region' => $region,
                            // Before every listing goes live, so the created_at
                            // clause isn't what's under test here.
                            'created_at' => now()->subYear(),
                        ]);
                    }
                }
            }
        }

        return SavedSearch::all();
    }

    /**
     * @return Collection<int, Listing>
     */
    private function listingGrid(): Collection
    {
        $branches = collect(['Leeds', 'Manchester'])
            ->map(fn (string $region) => Branch::factory()->create(['region' => $region]));

        foreach ($branches as $branch) {
            foreach ([150_000, 300_000, 650_000] as $price) {
                foreach ([1, 3] as $bedrooms) {
                    foreach ([PropertyType::Flat, PropertyType::Detached] as $type) {
                        Listing::factory()->live()->for($branch)->create([
                            'price' => $price,
                            'bedrooms' => $bedrooms,
                            'property_type' => $type,
                            'listed_at' => now()->subHour(),
                        ]);
                    }
                }
            }
        }

        return Listing::query()->with('branch')->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function savedSearch(User $user, array $attributes = []): SavedSearch
    {
        return SavedSearch::factory()->for($user)->create([
            'created_at' => now()->subMonth(),
            ...$attributes,
        ]);
    }

    /**
     * Pins every field a search discriminates on, so each test varies only what
     * it is about.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function liveListing(array $attributes = [], string $region = 'Leeds'): Listing
    {
        return Listing::factory()
            ->live()
            ->for(Branch::factory()->create(['region' => $region]))
            ->create([
                'property_type' => PropertyType::Terraced,
                'price' => 250_000,
                'bedrooms' => 3,
                'listed_at' => now()->subHours(2),
                ...$attributes,
            ]);
    }
}
