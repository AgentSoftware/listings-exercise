<?php

namespace Database\Seeders;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\ListingCriteria;
use Database\Factories\BranchFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo user that the auth stub resolves every API request as.
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@street.example',
        ]);

        // Listings are seeded with model events off. Otherwise every live
        // listing would fire ListingWentLive and queue a matching job, hundreds
        // of them on every seed. The demo alerts below are created directly
        // instead, so the seed never depends on the alert pipeline running.
        Listing::withoutEvents(function () {
            // One branch per region, so the demo searches below each have a
            // region with listings in it.
            $branches = Branch::factory(count(BranchFactory::REGIONS))
                ->sequence(...array_map(fn (string $region) => ['region' => $region], BranchFactory::REGIONS))
                ->create();

            foreach ($branches as $branch) {
                Listing::factory(rand(18, 30))->live()->for($branch)->create();
                Listing::factory(rand(3, 6))->underOffer()->for($branch)->create();
                Listing::factory(rand(4, 8))->sold()->for($branch)->create();
                Listing::factory(rand(2, 5))->for($branch)->create(); // drafts
            }
        });

        $this->seedAlertScenario($user);
    }

    /**
     * A fixed demo scenario for the demo user, so the pages and commands show
     * the same numbers after every seed (DatabaseSeederTest guards them).
     *
     * Each search has its own region, so no listing matches two of them and
     * "most frequent search wins" never moves an alert between searches.
     *
     * Current alerts, per search (read = emailed and read, notified = emailed
     * but unread, pending = not emailed yet, unread):
     *   Instantly     1 = 1 notified                    (not in the digest demo)
     *   Daily         3 = 1 read + 2 pending            (due: emailed 2 days ago)
     *   Every 3 days  7 = 3 read + 1 notified + 3 pending (due: emailed 4 days ago)
     *   Every 7 days 10 = 4 read + 2 notified + 4 pending (not due: emailed 2 days ago)
     *   Never         0                                 (bookmark only)
     *
     * So alerts:send-digests sends one email with 5 listings: Daily's 2 pending
     * and Every 3 days' 3 pending.
     *
     * Old alerts, all emailed (model:prune removes exactly 4):
     *   Daily         2 unread, 100 days old  → pruned (over 90 days)
     *   Every 3 days  2 read, 40 days old     → pruned (read, over 30 days)
     *   Every 7 days  1 unread, 45 days old   → kept (unread alerts last 90 days)
     */
    private function seedAlertScenario(User $user): void
    {
        $scenario = [
            [
                'frequency' => AlertFrequency::Instantly,
                'criteria' => ['region' => 'Manchester', 'property_type' => PropertyType::Flat->value],
                'last_notified_days_ago' => 0,
                'read' => 0, 'notified' => 1, 'pending' => 0,
                'old' => null,
            ],
            [
                'frequency' => AlertFrequency::Daily,
                'criteria' => ['region' => 'Leeds', 'min_bedrooms' => 2],
                'last_notified_days_ago' => 2,
                'read' => 1, 'notified' => 0, 'pending' => 2,
                'old' => ['count' => 2, 'days_ago' => 100, 'read' => false],
            ],
            [
                'frequency' => AlertFrequency::Every3Days,
                'criteria' => ['region' => 'Liverpool', 'max_price' => 600_000],
                'last_notified_days_ago' => 4,
                'read' => 3, 'notified' => 1, 'pending' => 3,
                'old' => ['count' => 2, 'days_ago' => 40, 'read' => true],
            ],
            [
                'frequency' => AlertFrequency::Every7Days,
                'criteria' => ['region' => 'Sheffield'],
                'last_notified_days_ago' => 2,
                'read' => 4, 'notified' => 2, 'pending' => 4,
                'old' => ['count' => 1, 'days_ago' => 45, 'read' => false],
            ],
            [
                'frequency' => AlertFrequency::Never,
                'criteria' => ['region' => 'Bristol', 'max_price' => 250_000],
                'last_notified_days_ago' => null,
                'read' => 0, 'notified' => 0, 'pending' => 0,
                'old' => null,
            ],
        ];

        foreach ($scenario as $spec) {
            $criteria = ListingCriteria::fromInput($spec['criteria']);
            // Never searches don't email, so they keep a null last_notified_at.
            $lastNotified = now()->subDays($spec['last_notified_days_ago'] ?? 0)->subHour();

            $search = SavedSearch::factory()
                ->for($user)
                ->forCriteria($criteria)
                ->frequency($spec['frequency'])
                ->create([
                    'name' => $criteria->defaultName(),
                    'last_notified_at' => $spec['last_notified_days_ago'] === null ? null : $lastNotified,
                    // Older than the oldest demo alert.
                    'created_at' => now()->subDays(120),
                ]);

            $old = $spec['old'];
            $listings = $this->liveListingsMatching(
                $criteria,
                $spec['read'] + $spec['notified'] + $spec['pending'] + ($old['count'] ?? 0),
            );

            // Emailed alerts were created just before the last email; pending
            // ones arrived after it.
            $alert = fn (Carbon $createdAt, ?Carbon $notifiedAt, ?Carbon $readAt) => $this->createAlert(
                $search, $listings->shift(), $createdAt, $notifiedAt, $readAt,
            );

            for ($i = 0; $i < $spec['read']; $i++) {
                $alert($lastNotified->copy()->subHours(2 + $i), $lastNotified, $lastNotified->copy()->addMinutes(30));
            }

            for ($i = 0; $i < $spec['notified']; $i++) {
                $alert($lastNotified->copy()->subMinutes(15 + $i), $lastNotified, null);
            }

            for ($i = 0; $i < $spec['pending']; $i++) {
                $alert(now()->subMinutes(30 + $i), null, null);
            }

            for ($i = 0; $i < ($old['count'] ?? 0); $i++) {
                $createdAt = now()->subDays($old['days_ago'])->subMinutes($i);
                $alert($createdAt, $createdAt->copy()->addHour(), $old['read'] ? $createdAt->copy()->addHours(2) : null);
            }
        }
    }

    /**
     * Live listings picked with the search's own criteria, so the demo follows
     * the real matching rules. Tops up with factory listings if a region
     * happens to have too few matches.
     *
     * @return Collection<int, Listing>
     */
    private function liveListingsMatching(ListingCriteria $criteria, int $count): Collection
    {
        $listings = $criteria->applyTo(Listing::query()->live())->orderBy('id')->limit($count)->get();

        $missing = $count - $listings->count();

        if ($missing > 0) {
            $listings = $listings->concat(Listing::withoutEvents(fn () => Listing::factory($missing)
                ->live()
                ->for(Branch::query()->where('region', $criteria->region)->firstOrFail())
                ->state(array_filter([
                    'property_type' => $criteria->propertyType,
                    'price' => $criteria->maxPrice,
                    'bedrooms' => $criteria->minBedrooms,
                ], fn ($value) => $value !== null))
                ->create()));
        }

        return $listings->toBase();
    }

    private function createAlert(SavedSearch $search, Listing $listing, Carbon $createdAt, ?Carbon $notifiedAt, ?Carbon $readAt): void
    {
        // The listing went live when its alert was created.
        Listing::withoutEvents(fn () => $listing->update(['listed_at' => $createdAt]));

        Alert::factory()->create([
            'user_id' => $search->user_id,
            'listing_id' => $listing->id,
            'saved_search_id' => $search->id,
            'created_at' => $createdAt,
            'updated_at' => $readAt ?? $notifiedAt ?? $createdAt,
            'notified_at' => $notifiedAt,
            'read_at' => $readAt,
        ]);
    }
}
