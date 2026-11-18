<?php

namespace App\Console\Commands;

use App\Jobs\SendPendingAlerts;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled sweep: queue SendPendingAlerts for every user with deliverable
 * alerts (due digests, plus any instant alert whose job was missed). The
 * per-user unique lock makes it safe to run while instant jobs are waiting.
 */
#[Signature('alerts:send-digests')]
#[Description('Queue alert emails for users with due, pending alerts')]
class SendAlertDigests extends Command
{
    public function handle(): int
    {
        $count = 0;

        User::query()
            ->whereIn('id', Alert::query()->deliverable()->select('user_id'))
            ->lazyById(500)
            ->each(function (User $user) use (&$count): void {
                SendPendingAlerts::dispatch($user);
                $count++;
            });

        $this->info("Queued alert emails for {$count} user(s).");

        return self::SUCCESS;
    }
}
