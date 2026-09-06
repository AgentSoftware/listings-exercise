<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Search\ListingCriteria;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's standing request to hear about listings matching some criteria.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property PropertyType|null $property_type
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property string|null $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, Alert> $alerts
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'property_type',
        'max_price',
        'min_bedrooms',
        'region',
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
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * The stored columns as the value object the rest of the application
     * matches on.
     */
    public function criteria(): ListingCriteria
    {
        return new ListingCriteria(
            $this->property_type,
            $this->max_price,
            $this->min_bedrooms,
            $this->region,
        );
    }

    /**
     * What to call this search in the UI. Most users won't name one, so fall
     * back to describing what it looks for.
     */
    public function label(): string
    {
        return $this->name ?? $this->criteria()->summary();
    }
}
