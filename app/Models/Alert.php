<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $listing_id
 * @property int|null $saved_search_id
 * @property Carbon|null $notified_at
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
        'notified_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
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
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * The one "is this alert visible?" rule, shared by the alerts page, the
     * unread count and email delivery: only alerts for listings that are live
     * right now. Non-live listings aren't publicly exposed anywhere else
     * either. An alert reappears if its listing goes live again.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('listing_id', Listing::query()->live()->select('id'));
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Not emailed yet.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('notified_at');
    }
}
