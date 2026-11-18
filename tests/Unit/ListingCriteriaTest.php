<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use App\Support\ListingCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ListingCriteriaTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('summaries')]
    public function test_describe_builds_one_readable_summary(array $input, string $expected): void
    {
        $this->assertSame($expected, ListingCriteria::fromInput($input)->describe());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function summaries(): array
    {
        return [
            'everything' => [
                ['property_type' => 'flat', 'region' => 'Manchester', 'min_bedrooms' => '2', 'max_price' => '200000'],
                'Flats in Manchester · 2+ beds · up to £200,000',
            ],
            'region only' => [['region' => 'Leeds'], 'Properties in Leeds'],
            'type only' => [['property_type' => 'detached'], 'Detached homes'],
            'price only' => [['max_price' => 350000], 'Properties · up to £350,000'],
            'type and beds' => [['property_type' => 'bungalow', 'min_bedrooms' => 3], 'Bungalows · 3+ beds'],
            'region and price' => [['region' => 'Bristol', 'max_price' => 1250000], 'Properties in Bristol · up to £1,250,000'],
        ];
    }

    public function test_blank_values_mean_any(): void
    {
        $criteria = ListingCriteria::fromInput([
            'property_type' => '',
            'max_price' => null,
            'region' => '',
            'per_page' => '50',
        ]);

        $this->assertTrue($criteria->isEmpty());
        $this->assertSame([], $criteria->toQuery());
    }

    public function test_zero_is_a_criterion_not_a_blank(): void
    {
        $criteria = ListingCriteria::fromInput(['min_bedrooms' => '0']);

        $this->assertFalse($criteria->isEmpty());
        $this->assertSame(['min_bedrooms' => 0], $criteria->toQuery());
    }

    public function test_to_query_returns_only_the_set_criteria(): void
    {
        $criteria = new ListingCriteria(propertyType: PropertyType::Terraced, region: 'Leeds');

        $this->assertSame(['property_type' => 'terraced', 'region' => 'Leeds'], $criteria->toQuery());
        $this->assertSame(
            ['property_type' => PropertyType::Terraced, 'max_price' => null, 'min_bedrooms' => null, 'region' => 'Leeds'],
            $criteria->toAttributes(),
        );
    }

    public function test_default_name_is_the_region_or_all_areas(): void
    {
        $this->assertSame('Leeds', (new ListingCriteria(region: 'Leeds'))->defaultName());
        $this->assertSame('All areas', (new ListingCriteria(maxPrice: 100000))->defaultName());
    }
}
