<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The queue is sync in tests, so the command's SendPendingAlerts jobs run
 * straight away.
 */
class DigestDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Notification::fake();
    }

    #[DataProvider('cadences')]
    public function test_a_digest_is_sent_only_once_its_interval_has_passed(AlertFrequency $frequency, int $minutesSinceLastEmail, bool $due): void
    {
        $search = SavedSearch::factory()->frequency($frequency)->create([
            'created_at' => now()->subDays(30),
            'last_notified_at' => now()->subMinutes($minutesSinceLastEmail),
        ]);
        Alert::factory()->for($search->user)->for($search)->create();

        $this->artisan('alerts:send-digests')->assertSuccessful();

        $due
            ? Notification::assertSentToTimes($search->user, NewListingsAlert::class, 1)
            : Notification::assertNothingSent();
    }

    /**
     * @return array<string, array{AlertFrequency, int, bool}>
     */
    public static function cadences(): array
    {
        $day = 24 * 60;

        return [
            'daily, exactly a day' => [AlertFrequency::Daily, $day, true],
            'daily, a minute short' => [AlertFrequency::Daily, $day - 1, false],
            'every 3 days, exactly 3 days' => [AlertFrequency::Every3Days, 3 * $day, true],
            'every 3 days, 2 days' => [AlertFrequency::Every3Days, 2 * $day, false],
            'every 7 days, 8 days' => [AlertFrequency::Every7Days, 8 * $day, true],
            'every 7 days, 6 days' => [AlertFrequency::Every7Days, 6 * $day, false],
        ];
    }

    public function test_a_search_that_has_never_emailed_counts_from_when_it_was_saved(): void
    {
        $justSaved = SavedSearch::factory()->daily()->create(['created_at' => now()->subHours(2)]);
        $savedLastWeek = SavedSearch::factory()->daily()->create(['created_at' => now()->subWeek()]);
        Alert::factory()->for($justSaved->user)->for($justSaved)->create();
        Alert::factory()->for($savedLastWeek->user)->for($savedLastWeek)->create();

        $this->artisan('alerts:send-digests');

        Notification::assertSentToTimes($savedLastWeek->user, NewListingsAlert::class, 1);
        Notification::assertNotSentTo($justSaved->user, NewListingsAlert::class);
    }

    public function test_sending_stamps_the_alerts_and_the_search(): void
    {
        $search = SavedSearch::factory()->daily()->create(['created_at' => now()->subDays(2)]);
        $alerts = Alert::factory(2)->for($search->user)->for($search)->create();

        $this->artisan('alerts:send-digests');

        foreach ($alerts as $alert) {
            $this->assertEquals(now(), $alert->fresh()?->notified_at);
        }
        $this->assertEquals(now(), $search->fresh()?->last_notified_at);
    }

    public function test_one_email_bundles_every_due_search_and_leaves_out_the_rest(): void
    {
        $user = User::factory()->create();
        $daily = SavedSearch::factory()->for($user)->daily()->create(['created_at' => now()->subDays(2)]);
        $every3Days = SavedSearch::factory()->for($user)->every3Days()->create(['created_at' => now()->subDays(4)]);
        $every7Days = SavedSearch::factory()->for($user)->every7Days()->create(['created_at' => now()->subDays(4)]);
        $dueAlerts = collect([
            Alert::factory()->for($user)->for($daily)->create(),
            Alert::factory()->for($user)->for($every3Days)->create(),
        ]);
        $notDue = Alert::factory()->for($user)->for($every7Days)->create();

        $this->artisan('alerts:send-digests');

        Notification::assertSentToTimes($user, NewListingsAlert::class, 1);
        Notification::assertSentTo($user, NewListingsAlert::class, fn (NewListingsAlert $notification) => $notification->alerts->modelKeys() === $dueAlerts->pluck('id')->all());
        $this->assertNull($notDue->fresh()?->notified_at);
        $this->assertNull($every7Days->fresh()?->last_notified_at);
    }

    public function test_nothing_is_sent_twice(): void
    {
        $search = SavedSearch::factory()->daily()->create(['created_at' => now()->subDays(2)]);
        Alert::factory()->for($search->user)->for($search)->create();

        $this->artisan('alerts:send-digests');
        $this->artisan('alerts:send-digests');
        $this->travel(2)->days();
        $this->artisan('alerts:send-digests');

        Notification::assertSentToTimes($search->user, NewListingsAlert::class, 1);
    }

    public function test_never_searches_never_email(): void
    {
        $search = SavedSearch::factory()->never()->create(['created_at' => now()->subYear()]);
        Alert::factory()->for($search->user)->for($search)->create();

        $this->artisan('alerts:send-digests');

        Notification::assertNothingSent();
    }

    public function test_pending_alerts_of_a_deleted_search_are_not_emailed_but_stay_visible(): void
    {
        $search = SavedSearch::factory()->daily()->create(['created_at' => now()->subDays(2)]);
        $alert = Alert::factory()->for($search->user)->for($search)->create();

        $search->delete();
        $this->artisan('alerts:send-digests');

        Notification::assertNothingSent();
        $this->assertNull($alert->fresh()?->saved_search_id);
        $this->assertTrue(Alert::query()->visible()->whereKey($alert->id)->exists());
    }

    public function test_alerts_whose_listing_is_no_longer_live_are_not_emailed(): void
    {
        $search = SavedSearch::factory()->daily()->create(['created_at' => now()->subDays(2)]);
        $alert = Alert::factory()->for($search->user)->for($search)->create();

        $alert->listing->update(['status' => ListingStatus::UnderOffer]);
        $this->artisan('alerts:send-digests');

        Notification::assertNothingSent();
        $this->assertNull($alert->fresh()?->notified_at, 'Still pending, in case the listing comes back.');
    }

    public function test_the_digest_command_and_pruning_are_scheduled(): void
    {
        Artisan::call('schedule:list');
        $schedule = Artisan::output();

        $this->assertMatchesRegularExpression('#\*/15 \* \* \* \*\s+php artisan alerts:send-digests#', $schedule);
        $this->assertStringContainsString('php artisan model:prune', $schedule);
    }
}
