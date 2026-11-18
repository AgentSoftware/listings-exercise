<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One email for one or many new listings: an instant send usually has one, a
 * digest several. It takes the alerts rather than bare listings, because each
 * alert carries both its listing and the search it matched, which the email
 * groups by.
 */
class NewListingsAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  Collection<int, Alert>  $alerts  with listing.branch and savedSearch loaded
     */
    public function __construct(public Collection $alerts)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $count = $this->alerts->count();

        $mail = (new MailMessage)
            ->subject($count === 1
                ? 'A new listing matches your saved search'
                : "{$count} new listings match your saved searches")
            ->greeting("Hi {$notifiable->name},");

        foreach ($this->alerts->groupBy('saved_search_id') as $group) {
            $search = $group->first()?->savedSearch;

            if ($search !== null) {
                $mail->line("**{$search->criteria()->describe()}**");
            }

            foreach ($group as $alert) {
                $listing = $alert->listing;

                $mail->line(sprintf(
                    '[%s, %s](%s): £%s · %d bed %s',
                    $listing->address_line_1,
                    $listing->city,
                    route('listings.show', $listing),
                    number_format($listing->price),
                    $listing->bedrooms,
                    strtolower($listing->property_type->label()),
                ));
            }
        }

        return $mail->action('View all your alerts', route('alerts.index'));
    }
}
