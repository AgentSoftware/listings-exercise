<?php

namespace Tests\Feature;

use App\Jobs\SendPendingAlerts;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstantAlertDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $leeds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->leeds = Branch::factory()->create(['region' => 'Leeds']);
    }

    public function test_a_burst_of_matches_queues_one_delayed_job_and_sends_one_email(): void
    {
        // Only the send is faked: matching still runs, so the dispatches are real.
        Queue::fake([SendPendingAlerts::class]);
        Notification::fake();
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->instantly()->forCriteria(['region' => 'Leeds'])->create();

        $listings = Listing::factory(3)->live()->for($this->leeds)->create();

        Queue::assertPushedTimes(SendPendingAlerts::class, 1);
        $job = Queue::pushed(SendPendingAlerts::class)->sole();
        $this->assertSame('notifications', $job->queue);
        $this->assertEquals(now()->addMinutes(10), $job->delay);
        $this->assertSame($user->id, $job->user->id);

        $job->handle();

        Notification::assertSentToTimes($user, NewListingsAlert::class, 1);
        Notification::assertSentTo($user, NewListingsAlert::class, fn (NewListingsAlert $notification) => $notification->queue === 'notifications'
            && $notification->alerts->pluck('listing_id')->sort()->values()->all() === $listings->pluck('id')->sort()->values()->all());

        $this->assertSame(0, Alert::query()->pending()->count());
        $this->assertEquals(now(), $search->fresh()?->last_notified_at);
    }

    public function test_digest_matches_do_not_queue_an_instant_send(): void
    {
        Queue::fake([SendPendingAlerts::class]);
        SavedSearch::factory()->daily()->forCriteria(['region' => 'Leeds'])->create();

        Listing::factory()->live()->for($this->leeds)->create();

        $this->assertSame(1, Alert::count());
        Queue::assertNotPushed(SendPendingAlerts::class);
    }

    public function test_the_instant_job_also_sends_the_users_due_digest_alerts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $daily = SavedSearch::factory()->for($user)->daily()->forCriteria(['region' => 'Bristol'])
            ->create(['created_at' => now()->subDays(2)]);
        $dueDigestAlert = Alert::factory()->for($user)->for($daily)->create();
        SavedSearch::factory()->for($user)->instantly()->forCriteria(['region' => 'Leeds'])->create();

        // The queue is sync here, so the delayed job runs straight away.
        $listing = Listing::factory()->live()->for($this->leeds)->create();

        Notification::assertSentToTimes($user, NewListingsAlert::class, 1);
        Notification::assertSentTo($user, NewListingsAlert::class, fn (NewListingsAlert $notification) => $notification->alerts->pluck('listing_id')->sort()->values()->all()
            === collect([$dueDigestAlert->listing_id, $listing->id])->sort()->values()->all());
    }

    public function test_the_unique_lock_outlives_the_debounce_delay(): void
    {
        $job = new SendPendingAlerts(User::factory()->create());

        $this->assertSame((string) $job->user->id, $job->uniqueId());
        $this->assertGreaterThan(10 * 60, $job->uniqueFor());
    }

    /**
     * A job blocked by an overlapping send for the same user is released and
     * retried, and never fails by running out of attempts.
     */
    public function test_an_overlapping_send_is_released_not_failed(): void
    {
        $job = new SendPendingAlerts(User::factory()->create());

        $middleware = $job->middleware()[0];
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame(30, $middleware->releaseAfter);
        $this->assertSame(300, $middleware->expiresAfter);
        $this->assertGreaterThan(now(), $job->retryUntil());
    }

    public function test_the_email_groups_listings_under_each_search_summary(): void
    {
        $user = User::factory()->create(['name' => 'Sam']);
        $leedsFlats = SavedSearch::factory()->for($user)->forCriteria(['region' => 'Leeds', 'property_type' => 'flat'])->create();
        $bristol = SavedSearch::factory()->for($user)->forCriteria(['region' => 'Bristol', 'max_price' => 400_000])->create();
        Alert::factory()->for($user)->for($leedsFlats)->create();
        Alert::factory()->for($user)->for($bristol)->create();
        Alert::factory()->for($user)->for($leedsFlats)->create();

        $alerts = $user->alerts()->with(['listing.branch', 'savedSearch'])->orderBy('id')->get();
        $mail = (new NewListingsAlert($alerts))->toMail($user);

        $this->assertSame('3 new listings match your saved searches', $mail->subject);
        $this->assertSame('**Flats in Leeds**', $mail->introLines[0]);
        $this->assertStringContainsString($alerts[0]->listing->address_line_1, $mail->introLines[1]);
        $this->assertStringContainsString($alerts[2]->listing->address_line_1, $mail->introLines[2]);
        $this->assertSame('**Properties in Bristol · up to £400,000**', $mail->introLines[3]);
        $this->assertStringContainsString($alerts[1]->listing->address_line_1, $mail->introLines[4]);
    }

    public function test_a_single_listing_uses_the_same_email(): void
    {
        $user = User::factory()->create();
        Alert::factory()->for($user)->create();

        $mail = (new NewListingsAlert($user->alerts()->with(['listing.branch', 'savedSearch'])->get()))->toMail($user);

        $this->assertSame('A new listing matches your saved search', $mail->subject);
    }
}
