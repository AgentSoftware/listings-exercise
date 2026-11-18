<?php

namespace Database\Factories;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\ListingCriteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * A region-only search by default, with alerts off, matching the database
     * default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'region' => fake()->randomElement(BranchFactory::REGIONS),
            'property_type' => null,
            'max_price' => null,
            'min_bedrooms' => null,
            'name' => null,
            'alert_frequency' => AlertFrequency::Never,
            'last_notified_at' => null,
        ];
    }

    /**
     * Set every criterion at once. Any criterion not given is "any" (null).
     *
     * @param  ListingCriteria|array<string, mixed>  $criteria
     */
    public function forCriteria(ListingCriteria|array $criteria): static
    {
        $criteria = $criteria instanceof ListingCriteria ? $criteria : ListingCriteria::fromInput($criteria);

        return $this->state(fn () => $criteria->toAttributes());
    }

    public function never(): static
    {
        return $this->frequency(AlertFrequency::Never);
    }

    public function instantly(): static
    {
        return $this->frequency(AlertFrequency::Instantly);
    }

    public function daily(): static
    {
        return $this->frequency(AlertFrequency::Daily);
    }

    public function every3Days(): static
    {
        return $this->frequency(AlertFrequency::Every3Days);
    }

    public function every7Days(): static
    {
        return $this->frequency(AlertFrequency::Every7Days);
    }

    public function frequency(AlertFrequency $frequency): static
    {
        return $this->state(fn () => ['alert_frequency' => $frequency]);
    }
}
