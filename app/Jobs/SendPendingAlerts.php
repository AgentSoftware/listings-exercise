<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingsAlert;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that sends alert emails, for both instant and digest
 * searches: it bundles a user's deliverable alerts (Alert::deliverable()) into
 * one NewListingsAlert.
 *
 * Debounce: matching dispatches this with a delay (config
 * alerts.instant_debounce_minutes) whenever an instant search matches. The job
 * is unique per user, so further alerts arriving inside that window find a
 * job already queued and are picked up by it: a burst of new listings becomes
 * one email instead of several. The digest sweep (alerts:send-digests)
 * dispatches the same job, and the unique lock drops it if an instant one is
 * already waiting. That job then sends the due digest alerts too.
 *
 * It's ShouldBeUniqueUntilProcessing rather than plain ShouldBeUnique, so the
 * lock is freed once the job starts. An alert that arrives while an email is
 * being sent then queues a fresh, delayed job, instead of waiting for the
 * next digest sweep.
 */
class SendPendingAlerts implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Real failures (not overlap releases) give up after this many exceptions.
     */
    public int $maxExceptions = 3;

    public function __construct(public User $user)
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    /**
     * Longer than the debounce delay, so the lock outlives the wait.
     */
    public function uniqueFor(): int
    {
        return (Config::integer('alerts.instant_debounce_minutes') + 5) * 60;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // The unique lock is freed once a job starts, so a second job for the
        // same user can start while the first is still sending. Without this,
        // both would read the same alerts and email them twice.
        //
        // A blocked job is released and tried again shortly after, rather than
        // dropped (dontRelease()), so an instant alert isn't held back until
        // the next digest sweep. retryUntil() stops these releases from using
        // up attempts and failing the job. expireAfter() frees the lock if a
        // worker dies mid-send.
        return [
            (new WithoutOverlapping($this->uniqueId()))->releaseAfter(30)->expireAfter(300),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(): void
    {
        $alerts = $this->user->alerts()
            ->deliverable()
            ->with(['listing.branch', 'savedSearch'])
            ->orderBy('id')
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        // Stamp first, then send. A retry of this job can never email the same
        // alerts twice, so delivery is at most once. The notification is itself
        // queued with retries, so a mail failure is retried on that side.
        DB::transaction(function () use ($alerts): void {
            $now = now();

            Alert::query()->whereKey($alerts->modelKeys())->update(['notified_at' => $now]);

            SavedSearch::query()
                ->whereKey($alerts->pluck('saved_search_id')->unique()->values())
                ->update(['last_notified_at' => $now]);
        });

        $this->user->notify(new NewListingsAlert($alerts));
    }
}
