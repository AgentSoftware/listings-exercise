<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
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
}
