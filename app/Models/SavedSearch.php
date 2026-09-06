<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Search\ListingCriteria;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A buyer's standing request to hear about listings matching some criteria.
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
     * What to call this search in the UI. Most buyers won't name one, so fall
     * back to describing what it looks for.
     */
    public function label(): string
    {
        return $this->name ?? $this->criteria()->summary();
    }

    /**
     * The searches that want this listing — ListingCriteria::applyTo() inverted,
     * so one indexed query answers "who should hear about this?" rather than
     * every saved search being loaded and checked in PHP.
     *
     * No join is needed for region: the listing is already in hand, so its
     * branch region binds as a parameter.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatching(Builder $query, Listing $listing): void
    {
        // A listing that never went live alerts nobody. GenerateAlerts filters
        // these out first, so this is a guard rather than a code path — but the
        // safe answer for an alerting query is "nobody", not an error.
        if ($listing->listed_at === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->where(fn (Builder $q) => $q->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('region')->orWhere('region', $listing->branch->region))
            // Only listings that went live after the search was saved. A new
            // saved search asks to hear what comes next, not to be told about
            // everything already on the market — the saved-searches page links
            // straight to the browse results for that.
            ->where('created_at', '<=', $listing->listed_at);
    }
}
