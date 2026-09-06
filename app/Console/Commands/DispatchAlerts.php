<?php

namespace App\Console\Commands;

use App\Alerts\GenerateAlerts;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewListingMatches;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

class DispatchAlerts extends Command
{
    protected $signature = 'alerts:dispatch';

    protected $description = 'Alert users about new listings matching their saved searches';

    /**
     * Users held in memory at once. The run itself is one statement, so this
     * is the only thing that grows with the size of a catch-up run.
     */
    private const USERS_PER_CHUNK = 100;

    /**
     * Held for the length of a run. Reading the un-notified alerts and marking
     * them notified are separate statements, so two overlapping runs would both
     * pick up the same alerts and send the same digest twice — the one failure
     * this whole feature exists to avoid. The README encourages running this by
     * hand, which is exactly when it could collide with the schedule.
     *
     * The lock lives here rather than on the schedule entry, because
     * withoutOverlapping() only guards scheduled invocations against each other
     * and would do nothing about a manual run.
     */
    private const LOCK = 'alerts:dispatch';

    /**
     * How long that lock survives if it is never released.
     *
     * Laravel's locks cannot be extended once taken, so this has to be sized
     * past the worst-case run rather than refreshed as it goes. If it expires
     * while sendDigests() is still walking chunks, the next run — scheduled or
     * manual — picks up the alerts this one has not marked yet and sends them a
     * second time, which is the duplicate the lock exists to prevent.
     *
     * Erring high costs the other way: a hard-killed process blocks the next
     * run for this long. A run that gets anywhere near an hour is a signal the
     * catch-up window is too big to clear in one pass, not a reason to raise it.
     */
    private const LOCK_SECONDS = 3600;

    public function handle(GenerateAlerts $generateAlerts): int
    {
        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another alerts:dispatch run is in progress; doing nothing.');

            return self::SUCCESS;
        }

        try {
            return $this->dispatchAlerts($generateAlerts);
        } finally {
            $lock->release();
        }
    }

    private function dispatchAlerts(GenerateAlerts $generateAlerts): int
    {
        $created = $generateAlerts();

        [$digests, $covered] = $this->sendDigests();

        if ($created === 0 && $digests === 0) {
            $this->info('No new matches.');

            return self::SUCCESS;
        }

        // "queued", not "sent": NewListingMatches is a queued notification, so
        // on the default database queue these are delivered by a worker.
        $this->info(sprintf(
            '%d alert(s) created; %d digest(s) queued covering %d alert(s).',
            $created,
            $digests,
            $covered,
        ));

        return self::SUCCESS;
    }

    /**
     * One message per user covering everything they haven't been told about —
     * sending per alert would undo the reason this runs on a schedule.
     *
     * Driven by notified_at rather than by whatever this run happened to create,
     * so alerts left behind by a run that died part way through go out here
     * instead of never.
     *
     * @return array{int, int} digests queued, alerts covered
     */
    private function sendDigests(): array
    {
        $digests = 0;
        $covered = 0;

        User::query()
            ->whereHas('alerts', fn (Builder $alerts) => $alerts->whereNull('notified_at'))
            // Only what a digest actually prints, plus the true total. Without
            // the limit this loads every pending alert a user has, so one very
            // broad search after an outage could pull thousands of models per
            // chunk into memory.
            ->withCount(['alerts' => fn (Builder $alerts) => $alerts
                ->whereNull('notified_at')])
            ->with(['alerts' => fn (Relation $alerts) => $alerts
                ->whereNull('notified_at')
                ->with('listing.branch')
                ->latest('id')
                ->limit(NewListingMatches::PREVIEW)])
            ->chunkById(self::USERS_PER_CHUNK, function (Collection $users) use (&$digests, &$covered): void {
                $users = $users->filter(fn (User $user) => $user->alerts->isNotEmpty());

                if ($users->isEmpty()) {
                    return;
                }

                // Marked before sending, in one statement for the whole chunk
                // rather than one per user — which is the difference between a
                // handful of writes and one per digest.
                //
                // That makes delivery at-most-once: if the run dies here, those
                // users miss a digest rather than getting two. The alerts are
                // on their alerts page either way, so a lost email costs less
                // than a duplicate one — which is the whole point of the
                // feature. Lower USERS_PER_CHUNK to narrow that window.
                Alert::query()
                    ->whereIn('user_id', $users->modelKeys())
                    ->whereNull('notified_at')
                    ->update(['notified_at' => now()]);

                foreach ($users as $user) {
                    $pending = (int) $user->alerts_count;

                    $user->notify(new NewListingMatches($user->alerts, $pending));

                    $digests++;
                    $covered += $pending;
                }
            });

        return [$digests, $covered];
    }
}
