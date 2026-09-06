<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_criteria_round_trips_through_the_stored_columns(): void
    {
        $search = SavedSearch::factory()->create([
            'property_type' => PropertyType::Flat,
            'max_price' => 300_000,
            'min_bedrooms' => 2,
            'region' => 'Leeds',
        ]);

        $criteria = $search->fresh()->criteria();

        $this->assertSame(PropertyType::Flat, $criteria->propertyType);
        $this->assertSame(300_000, $criteria->maxPrice);
        $this->assertSame(2, $criteria->minBedrooms);
        $this->assertSame('Leeds', $criteria->region);
    }

    public function test_label_falls_back_to_a_summary_of_the_criteria(): void
    {
        $named = SavedSearch::factory()->create(['name' => 'Somewhere near Mum']);
        $unnamed = SavedSearch::factory()->create(['region' => 'Leeds', 'min_bedrooms' => 3]);

        $this->assertSame('Somewhere near Mum', $named->label());
        $this->assertSame('Leeds · 3+ beds', $unnamed->label());
    }

    public function test_matching_selects_only_the_searches_a_listing_satisfies(): void
    {
        $listing = $this->listing(region: 'Leeds');

        $wants = SavedSearch::factory()->create(['region' => 'Leeds', 'max_price' => 300_000]);
        $tooExpensive = SavedSearch::factory()->create(['max_price' => 200_000]);
        $wrongRegion = SavedSearch::factory()->create(['region' => 'Manchester']);
        $wrongType = SavedSearch::factory()->create(['property_type' => PropertyType::Bungalow]);
        $anything = SavedSearch::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$wants->id, $anything->id],
            SavedSearch::query()->matching($listing)->pluck('id')->all(),
        );

        $this->assertNotContains($tooExpensive->id, SavedSearch::query()->matching($listing)->pluck('id')->all());
        $this->assertNotContains($wrongRegion->id, SavedSearch::query()->matching($listing)->pluck('id')->all());
        $this->assertNotContains($wrongType->id, SavedSearch::query()->matching($listing)->pluck('id')->all());
    }

    /**
     * The backfill policy, expressed as one clause of the scope: saving a search
     * asks to hear what comes next, not to be told about the whole market.
     */
    public function test_matching_ignores_searches_saved_after_the_listing_went_live(): void
    {
        $listing = $this->listing(['listed_at' => now()->subWeek()]);

        $before = SavedSearch::factory()->create(['created_at' => now()->subMonth()]);
        SavedSearch::factory()->create(['created_at' => now()]);

        $this->assertSame([$before->id], SavedSearch::query()->matching($listing)->pluck('id')->all());
    }

    public function test_a_listing_that_never_went_live_matches_nothing(): void
    {
        $listing = $this->listing(['status' => 'draft', 'listed_at' => null]);
        SavedSearch::factory()->create();

        $this->assertSame([], SavedSearch::query()->matching($listing)->pluck('id')->all());
    }

    /**
     * SavedSearch::matching() is ListingCriteria::applyTo() turned inside out —
     * a third expression of the same rule, and the one the alert run depends on.
     * Assert it against the criteria it was built from rather than trusting the
     * inversion to have been done correctly by eye.
     */
    public function test_matching_agrees_with_the_criteria_it_was_built_from(): void
    {
        $searches = $this->savedSearchGrid();
        $matchedAnything = false;

        foreach ($this->listingGrid() as $listing) {
            $bySql = SavedSearch::query()->matching($listing)->orderBy('id')->pluck('id')->all();

            $byCriteria = $searches
                ->filter(fn (SavedSearch $search) => $search->criteria()->matches($listing))
                ->sortBy('id')
                ->pluck('id')
                ->all();

            $this->assertSame($bySql, $byCriteria, "Disagreement on listing {$listing->id}.");

            $matchedAnything = $matchedAnything || $bySql !== [];
        }

        $this->assertTrue($matchedAnything, 'Fixture should produce at least one match.');
    }

    /**
     * Every combination of "set" and "any" across the four criteria.
     *
     * @return Collection<int, SavedSearch>
     */
    private function savedSearchGrid(): Collection
    {
        $user = User::factory()->create();

        foreach ([null, PropertyType::Flat] as $type) {
            foreach ([null, 300_000] as $maxPrice) {
                foreach ([null, 3] as $minBedrooms) {
                    foreach ([null, 'Leeds'] as $region) {
                        SavedSearch::factory()->for($user)->create([
                            'property_type' => $type,
                            'max_price' => $maxPrice,
                            'min_bedrooms' => $minBedrooms,
                            'region' => $region,
                            // Comfortably before every listing below goes live,
                            // so the created_at clause isn't what's under test.
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
                            'listed_at' => now(),
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
    private function listing(array $attributes = [], string $region = 'Leeds'): Listing
    {
        $branch = Branch::factory()->create(['region' => $region]);

        // Every field a saved search discriminates on is pinned, so a test only
        // varies what it is actually about. The factory randomises property_type
        // otherwise, which makes any assertion about type intermittent.
        return Listing::factory()->live()->for($branch)->create([
            'listed_at' => now(),
            'property_type' => PropertyType::Terraced,
            'price' => 250_000,
            'bedrooms' => 3,
            ...$attributes,
        ]);
    }
}
