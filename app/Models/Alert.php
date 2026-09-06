<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record that a listing matched one of a user's saved searches.
 *
 * This is deliberately not the same thing as a notification. There is one alert
 * per listing, so the alerts page can list properties individually; delivery is
 * batched into a single nightly digest per user by DispatchAlerts.
 *
 * @property int $id
 * @property int $user_id
 * @property int $listing_id
 * @property int|null $saved_search_id
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Listing $listing
 * @property-read SavedSearch|null $savedSearch
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'listing_id',
        'saved_search_id',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Null once the search that produced this alert has been deleted.
     *
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
