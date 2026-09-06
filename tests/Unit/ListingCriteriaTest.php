<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Search\ListingCriteria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingCriteriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_and_missing_fields_are_treated_as_no_filter(): void
    {
        $blank = ListingCriteria::fromArray([
            'property_type' => '',
            'max_price' => '',
            'min_bedrooms' => '',
            'region' => '',
        ]);

        $this->assertTrue($blank->isEmpty(), 'A form of blanks is not a filter.');
        $this->assertTrue(ListingCriteria::fromArray([])->isEmpty());
        $this->assertSame([], $blank->toArray());
    }

    public function test_request_strings_are_cast_to_their_types(): void
    {
        $criteria = ListingCriteria::fromArray([
            'property_type' => 'flat',
            'max_price' => '300000',
            'min_bedrooms' => '2',
            'region' => 'Leeds',
        ]);

        $this->assertSame(PropertyType::Flat, $criteria->propertyType);
        $this->assertSame(300_000, $criteria->maxPrice);
        $this->assertSame(2, $criteria->minBedrooms);
        $this->assertSame('Leeds', $criteria->region);
        $this->assertFalse($criteria->isEmpty());
    }

    public function test_to_array_omits_unset_criteria_so_links_stay_clean(): void
    {
        $criteria = new ListingCriteria(maxPrice: 300_000, region: 'Leeds');

        $this->assertSame(['max_price' => 300_000, 'region' => 'Leeds'], $criteria->toArray());
    }

    public function test_summary_describes_the_criteria(): void
    {
        $criteria = new ListingCriteria(PropertyType::Flat, 300_000, 2, 'Leeds');

        $this->assertSame('Flat · Leeds · 2+ beds · up to £300,000', $criteria->summary());
        $this->assertSame('All listings', (new ListingCriteria)->summary());
    }

    /**
     * applyTo() and matches() are two expressions of one rule, used in different
     * places: the browse page filters in SQL, the nightly alert run checks a
     * listing it already holds. If they ever disagree, a user is alerted about
     * a property the site itself would not have shown them — so assert directly
     * that they select the same listings.
     */
    #[DataProvider('criteriaCases')]
    public function test_the_query_and_the_predicate_select_the_same_listings(ListingCriteria $criteria): void
    {
        $listings = $this->listingGrid();

        $query = Listing::query();
        $criteria->applyTo($query);

        $selectedByQuery = $query->orderBy('id')->pluck('id')->all();
        $selectedByPredicate = $listings
            ->filter(fn (Listing $listing) => $criteria->matches($listing))
            ->sortBy('id')
            ->pluck('id')
            ->all();

        // Guards against the degenerate pass where both sides match nothing.
        $this->assertNotEmpty($selectedByQuery, 'Fixture should match at least one listing.');
        $this->assertSame($selectedByQuery, $selectedByPredicate);
    }

    /**
     * @return array<string, array{ListingCriteria}>
     */
    public static function criteriaCases(): array
    {
        return [
            'no criteria' => [new ListingCriteria],
            'property type' => [new ListingCriteria(propertyType: PropertyType::Flat)],
            'max price' => [new ListingCriteria(maxPrice: 300_000)],
            'min bedrooms' => [new ListingCriteria(minBedrooms: 3)],
            'region' => [new ListingCriteria(region: 'Leeds')],
            'type and region' => [new ListingCriteria(propertyType: PropertyType::Flat, region: 'Leeds')],
            'price and bedrooms' => [new ListingCriteria(maxPrice: 300_000, minBedrooms: 3)],
            'every criterion' => [new ListingCriteria(PropertyType::Flat, 300_000, 3, 'Leeds')],
        ];
    }

    /**
     * Every combination of the values the criteria discriminate on, so the two
     * implementations are compared across boundaries rather than one happy path.
     *
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
                        ]);
                    }
                }
            }
        }

        return Listing::query()->with('branch')->get();
    }
}
