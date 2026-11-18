<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * The regions branches cover. Public so other factories (e.g. saved
     * searches) draw from the same list and can actually match these branches.
     */
    public const REGIONS = [
        'Manchester', 'Leeds', 'Liverpool', 'Sheffield',
        'Newcastle', 'Nottingham', 'Bristol', 'Birmingham',
    ];

    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(self::REGIONS);

        return [
            'name' => $city.' '.fake()->randomElement(['Central', 'North', 'South', 'Quays', 'Village']),
            'region' => $city,
        ];
    }
}
