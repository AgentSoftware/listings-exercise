<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One digest covering everything a buyer's saved searches matched in a single
 * run — not one message per property. This is the whole point of generating
 * alerts on a schedule: the alerts themselves stay granular so the alerts page
 * can list properties individually, while delivery collapses to one message.
 *
 * Queued, so a run with many recipients doesn't hold the scheduled command
 * open. On the sync driver it simply sends inline, which is what happens in
 * this exercise — no worker needed.
 */
class NewListingMatches extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * How many properties the digest names before summarising the rest. The
     * full set is always on the alerts page.
     *
     * This also bounds what the sender has to load: a buyer with a very broad
     * search can have thousands of pending alerts, and the digest only ever
     * needs this many of them plus the total.
     */
    public const PREVIEW = 5;

    /**
     * @param  Collection<int, Alert>  $alerts  at most PREVIEW of them, newest first
     * @param  int  $total  how many the buyer actually has pending
     */
    public function __construct(
        private readonly Collection $alerts,
        private readonly int $total,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->total;

        $message = (new MailMessage)
            ->subject($count === 1
                ? 'A new property matches your saved search'
                : "{$count} new properties match your saved searches")
            ->line($count === 1
                ? 'A property has come onto the market matching a search you saved:'
                : "{$count} properties have come onto the market matching searches you saved:");

        foreach ($this->alerts as $alert) {
            $message->line($this->describe($alert));
        }

        if ($count > $this->alerts->count()) {
            $message->line('…and '.($count - $this->alerts->count()).' more.');
        }

        return $message
            ->action('See all your alerts', url('/alerts'))
            ->line('You are getting this because you saved a search. Delete it to stop these.');
    }

    private function describe(Alert $alert): string
    {
        $listing = $alert->listing;

        return sprintf(
            '£%s — %s, %s (%d bed %s)',
            number_format($listing->price),
            $listing->address_line_1,
            $listing->city,
            $listing->bedrooms,
            strtolower($listing->property_type->label()),
        );
    }
}
