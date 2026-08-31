<?php


namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSavedSearchAlerts extends Command
{
    protected $signature = 'alerts:send';

    protected $description = 'Check saved searches for newly-live matching listings and notify users.';

    public function handle(): int
    {
        $newAlerts = collect();

        SavedSearch::query()->chunk(100, function ($savedSearches) use ($newAlerts) {
            foreach ($savedSearches as $savedSearch) {
                $filters = [
                    'property_type' => $savedSearch->property_type?->value,
                    'max_price' => $savedSearch->max_price,
                    'min_bedrooms' => $savedSearch->min_bedrooms,
                    'region' => $savedSearch->region,
                ];

                $matches = Listing::query()->live()->filter($filters)->get();

                foreach ($matches as $listing) {
                    $alert = Alert::query()->firstOrCreate([
                        'saved_search_id' => $savedSearch->id,
                        'listing_id' => $listing->id,
                    ], [
                        'user_id' => $savedSearch->user_id,
                    ]);

                    // Only rows created just now are "new" — a match that
                    // already had an Alert from a previous run is silently
                    // skipped, which is what prevents re-alerting/spam.
                    if ($alert->wasRecentlyCreated) {
                        $newAlerts->push($alert);
                    }
                }
            }
        });

        $newAlerts->groupBy('user_id')->each(
            fn($alerts, $userId) => $this->notify((int)$userId, $alerts->pluck('listing_id')->all())
        );

        $this->info("{$newAlerts->count()} new alert(s) created for {$newAlerts->pluck('user_id')->unique()->count()} user(s).");

        return self::SUCCESS;
    }

    /**
     * Sends one user's digest of newly matched listings.
     *
     * In production this would dispatch a queued job (e.g. onto an SQS
     * queue) that renders and sends a digest email/Notification, rather
     * than notifying synchronously inside the command — so mail-provider
     * latency or a large batch of alerts can't hold up the whole run.
     * Logged here instead, since the exercise doesn't require real email
     * or queue infrastructure.
     */
    private function notify(int $userId, array $listingIds): void
    {
        Log::info('Saved search alert digest', [
            'user_id' => $userId,
            'listing_ids' => $listingIds,
        ]);
    }
}
