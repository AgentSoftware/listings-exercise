<?php

namespace App\Models;

use App\Enums\AlertFrequency;
use App\Enums\PropertyType;
use App\Support\ListingCriteria;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string|null $region
 * @property PropertyType|null $property_type
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property AlertFrequency $alert_frequency
 * @property Carbon|null $last_notified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'region',
        'property_type',
        'max_price',
        'min_bedrooms',
        'alert_frequency',
        'last_notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'alert_frequency' => AlertFrequency::class,
            'last_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromInput($this->only(ListingCriteria::KEYS));
    }

    /**
     * Searches with exactly these criteria, where an unset criterion only
     * matches an unset column. Used for the duplicate check and to find the
     * saved search behind the listings page's current URL.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeWithCriteria(Builder $query, ListingCriteria $criteria): void
    {
        foreach ($criteria->toAttributes() as $column => $value) {
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }
    }
}
