<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('criteria')]
    public function test_each_criterion_matches_and_null_means_any(array $criteria, bool $expected): void
    {
        $listing = $this->listing(region: 'Leeds', type: PropertyType::Flat, price: 250_000, bedrooms: 2);
        $search = SavedSearch::factory()->forCriteria($criteria)->create();

        $matched = SavedSearch::query()->matching($listing)->whereKey($search->id)->exists();

        $this->assertSame($expected, $matched);
    }

    /**
     * Against a Leeds flat, £250,000, 2 bedrooms.
     *
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function criteria(): array
    {
        return [
            'no criteria: any' => [[], true],
            'same region' => [['region' => 'Leeds'], true],
            'other region' => [['region' => 'Bristol'], false],
            'same type' => [['property_type' => 'flat'], true],
            'other type' => [['property_type' => 'detached'], false],
            'max price above' => [['max_price' => 300_000], true],
            'max price equal' => [['max_price' => 250_000], true],
            'max price below' => [['max_price' => 249_999], false],
            'min beds below' => [['min_bedrooms' => 1], true],
            'min beds equal' => [['min_bedrooms' => 2], true],
            'min beds above' => [['min_bedrooms' => 3], false],
            'all match' => [['region' => 'Leeds', 'property_type' => 'flat', 'max_price' => 250_000, 'min_bedrooms' => 2], true],
            'all but one match' => [['region' => 'Leeds', 'property_type' => 'flat', 'max_price' => 250_000, 'min_bedrooms' => 3], false],
        ];
    }

    /**
     * The forward query (listings page) and the reverse scope (alerts) must
     * agree on every listing and search, so an alert always means "this would
     * have shown up in your search".
     */
    public function test_the_forward_query_and_the_matching_scope_agree(): void
    {
        $listings = collect();
        foreach (['Leeds', 'Bristol'] as $region) {
            foreach ([PropertyType::Flat, PropertyType::Detached] as $type) {
                foreach ([150_000, 300_000] as $price) {
                    foreach ([1, 3] as $bedrooms) {
                        $listings->push($this->listing($region, $type, $price, $bedrooms));
                    }
                }
            }
        }

        $user = User::factory()->create();
        $searches = collect();
        foreach ([null, 'Leeds'] as $region) {
            foreach ([null, 'flat'] as $type) {
                foreach ([null, 200_000] as $maxPrice) {
                    foreach ([null, 2] as $minBedrooms) {
                        $searches->push(SavedSearch::factory()->for($user)->forCriteria([
                            'region' => $region,
                            'property_type' => $type,
                            'max_price' => $maxPrice,
                            'min_bedrooms' => $minBedrooms,
                        ])->create());
                    }
                }
            }
        }

        foreach ($searches as $search) {
            $forward = $search->criteria()->applyTo(Listing::query()->live())->pluck('id')->sort()->values()->all();

            $reverse = $listings
                ->filter(fn (Listing $listing) => SavedSearch::query()->matching($listing)->whereKey($search->id)->exists())
                ->pluck('id')->sort()->values()->all();

            $this->assertSame($forward, $reverse, 'Mismatch for: '.$search->criteria()->describe());
        }
    }

    private function listing(string $region, PropertyType $type, int $price, int $bedrooms): Listing
    {
        $branch = Branch::query()->firstWhere('region', $region) ?? Branch::factory()->create(['region' => $region]);

        return Listing::factory()->live()->for($branch)->create([
            'property_type' => $type,
            'price' => $price,
            'bedrooms' => $bedrooms,
        ]);
    }
}
