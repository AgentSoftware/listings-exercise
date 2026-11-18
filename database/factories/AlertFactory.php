<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * Unread and not yet emailed, by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // A factory alert stands for a match that has already happened, so
            // its default listing is created without model events. Otherwise
            // the listing going live would run matching and could insert the
            // same (user, listing) alert first.
            'listing_id' => fn () => Listing::withoutEvents(fn () => Listing::factory()->live()->create()),
            'saved_search_id' => fn (array $attributes) => SavedSearch::factory()->state(['user_id' => $attributes['user_id']]),
            'notified_at' => null,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function notified(): static
    {
        return $this->state(fn () => ['notified_at' => now()]);
    }
}
