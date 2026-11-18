<?php

namespace Tests\Feature;

use App\Enums\AlertFrequency;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Guards the demo scenario documented in DatabaseSeeder and NOTES.md. If the
 * demo numbers change, this is the test to update.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_each_saved_search_has_its_documented_alerts(): void
    {
        // [current, old], where old means older than the 30-day read window.
        $expected = [
            AlertFrequency::Instantly->value => [1, 0],
            AlertFrequency::Daily->value => [3, 2],
            AlertFrequency::Every3Days->value => [7, 2],
            AlertFrequency::Every7Days->value => [10, 1],
            AlertFrequency::Never->value => [0, 0],
        ];

        $searches = $this->demoUser()->savedSearches()->get();

        $this->assertCount(5, $searches);
        $this->assertCount(5, $searches->pluck('region')->unique());

        foreach ($searches as $search) {
            $alerts = Alert::query()->whereBelongsTo($search)->get();
            [$current, $old] = $expected[$search->alert_frequency->value];

            $this->assertSame($current, $alerts->where('created_at', '>', now()->subDays(30))->count(), $search->alert_frequency->label());
            $this->assertSame($old, $alerts->where('created_at', '<=', now()->subDays(30))->count(), $search->alert_frequency->label());
        }

        // Every alert is for a live listing, so all of them show on the alerts page.
        $this->assertSame(26, Alert::query()->visible()->count());
        $this->assertSame(16, Alert::query()->visible()->unread()->count());
    }

    public function test_exactly_four_alerts_are_prunable(): void
    {
        $prunable = (new Alert)->prunable()->with('savedSearch')->get();

        $this->assertCount(4, $prunable);
        $this->assertEqualsCanonicalizing(
            [AlertFrequency::Daily, AlertFrequency::Daily, AlertFrequency::Every3Days, AlertFrequency::Every3Days],
            $prunable->pluck('savedSearch.alert_frequency')->all(),
        );
    }

    public function test_daily_and_every_3_days_are_due_but_every_7_days_is_not(): void
    {
        $due = SavedSearch::query()
            ->whereNot('alert_frequency', AlertFrequency::Instantly)
            ->due()
            ->pluck('alert_frequency')
            ->all();

        $this->assertEqualsCanonicalizing([AlertFrequency::Daily, AlertFrequency::Every3Days], $due);
    }

    public function test_the_digest_command_sends_one_email_with_the_due_pending_alerts(): void
    {
        Notification::fake();

        $this->artisan('alerts:send-digests')->assertSuccessful();

        $user = $this->demoUser();

        Notification::assertSentToTimes($user, NewListingsAlert::class, 1);
        Notification::assertSentTo($user, NewListingsAlert::class, function (NewListingsAlert $notification) {
            $frequencies = $notification->alerts->map(fn (Alert $alert) => $alert->savedSearch?->alert_frequency);

            return $notification->alerts->count() === 5
                && $frequencies->filter(fn ($f) => $f === AlertFrequency::Daily)->count() === 2
                && $frequencies->filter(fn ($f) => $f === AlertFrequency::Every3Days)->count() === 3;
        });
    }

    private function demoUser(): User
    {
        return User::query()->where('email', 'demo@street.example')->firstOrFail();
    }
}
