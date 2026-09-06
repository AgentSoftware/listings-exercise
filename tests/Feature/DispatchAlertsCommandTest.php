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
     * The reason this feature is scheduled rather than event-driven: a user
     * whose search matched five of today's listings hears from us once.
     */
    public function test_a_user_gets_one_digest_however_many_listings_matched(): void
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

    /**
     * The digest names a few properties and counts the rest, and the sender only
     * ever loads that few — a user with a very broad search after an outage
     * must not pull thousands of alerts into memory to be emailed about five.
     */
    public function test_a_large_digest_names_a_few_properties_and_counts_the_rest(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['region' => 'Leeds']);
        $this->liveListings(Branch::factory()->create(['region' => 'Leeds']), 8);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            NewListingMatches::class,
            function (NewListingMatches $digest, array $channels, User $notifiable) {
                $lines = collect($digest->toMail($notifiable)->introLines);

                return $digest->toMail($notifiable)->subject === '8 new properties match your saved searches'
                    && $lines->filter(fn (string $line) => str_starts_with($line, '£'))->count() === NewListingMatches::PREVIEW
                    && $lines->contains(fn (string $line) => str_contains($line, 'and 3 more'));
            },
        );

        // Every match is still recorded and marked delivered, even though only
        // five were named.
        $this->assertDatabaseCount('alerts', 8);
        $this->assertSame(0, $user->alerts()->whereNull('notified_at')->count());
    }

    public function test_each_user_is_told_only_about_their_own_matches(): void
    {
        Notification::fake();

        $leedsUser = User::factory()->create();
        $manchesterUser = User::factory()->create();
        $this->savedSearch($leedsUser, ['region' => 'Leeds']);
        $this->savedSearch($manchesterUser, ['region' => 'Manchester']);

        $this->liveListings(Branch::factory()->create(['region' => 'Leeds']), 2);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        Notification::assertSentToTimes($leedsUser, NewListingMatches::class, 1);
        Notification::assertNotSentTo($manchesterUser, NewListingMatches::class);
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
