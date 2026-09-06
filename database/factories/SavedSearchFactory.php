<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Criteria default to null — "any" — so a test states only the criteria it is
 * actually about, and an unstated one can't quietly affect the outcome.
 *
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => null,
            'property_type' => null,
            'max_price' => null,
            'min_bedrooms' => null,
            'region' => null,
        ];
    }
}
