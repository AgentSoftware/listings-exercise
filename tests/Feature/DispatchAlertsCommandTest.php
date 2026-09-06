<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingMatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DispatchAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reason this feature is scheduled rather than event-driven: a buyer
     * whose search matched five of today's listings hears from us once.
     */
    public function test_a_buyer_gets_one_digest_however_many_listings_matched(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['region' => 'Leeds']);

        $branch = Branch::factory()->create(['region' => 'Leeds']);
        $this->liveListings($branch, 5);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        $this->assertDatabaseCount('alerts', 5);
        Notification::assertSentToTimes($user, NewListingMatches::class, 1);
    }

    public function test_the_digest_covers_every_match_in_the_run(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['region' => 'Leeds']);
        $this->liveListings(Branch::factory()->create(['region' => 'Leeds']), 3);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            NewListingMatches::class,
            fn (NewListingMatches $digest, array $channels, User $notifiable) => $digest
                ->toMail($notifiable)
                ->subject === '3 new properties match your saved searches',
        );
    }

    public function test_each_buyer_is_told_only_about_their_own_matches(): void
    {
        Notification::fake();

        $leedsBuyer = User::factory()->create();
        $manchesterBuyer = User::factory()->create();
        $this->savedSearch($leedsBuyer, ['region' => 'Leeds']);
        $this->savedSearch($manchesterBuyer, ['region' => 'Manchester']);

        $this->liveListings(Branch::factory()->create(['region' => 'Leeds']), 2);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        Notification::assertSentToTimes($leedsBuyer, NewListingMatches::class, 1);
        Notification::assertNotSentTo($manchesterBuyer, NewListingMatches::class);
    }

    public function test_a_quiet_day_sends_nothing(): void
    {
        Notification::fake();

        $this->savedSearch(User::factory()->create(), ['region' => 'Leeds']);

        $this->artisan('alerts:dispatch')
            ->expectsOutputToContain('No new matches.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * Re-running is how the schedule catches up after a missed day, so it must
     * not mean telling everyone twice.
     */
    public function test_re_running_does_not_alert_anyone_again(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['region' => 'Leeds']);
        $this->liveListings(Branch::factory()->create(['region' => 'Leeds']), 2);

        $this->artisan('alerts:dispatch')->assertSuccessful();
        $this->artisan('alerts:dispatch')->assertSuccessful();

        $this->assertDatabaseCount('alerts', 2);
        Notification::assertSentToTimes($user, NewListingMatches::class, 1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function savedSearch(User $user, array $attributes = []): SavedSearch
    {
        return SavedSearch::factory()->for($user)->create([
            'created_at' => now()->subMonth(),
            ...$attributes,
        ]);
    }

    private function liveListings(Branch $branch, int $count): void
    {
        Listing::factory($count)->live()->for($branch)->create([
            'property_type' => PropertyType::Terraced,
            'price' => 250_000,
            'bedrooms' => 3,
            'listed_at' => now()->subHours(2),
        ]);
    }
}
