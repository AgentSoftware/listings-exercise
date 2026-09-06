<?php

namespace App\Search;

use App\Enums\PropertyType;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Enum;

/**
 * A set of listing search criteria — the shape a user fills in on the filter
 * form and the shape a saved search persists.
 *
 * Three places have to agree on what "matches" means: the browse filters query
 * with applyTo(), the saved-search form validates with rules(), and the nightly
 * alert run joins on the same comparisons. This class is that agreement.
 * A null property means "any", consistently, everywhere.
 */
readonly class ListingCriteria
{
    /**
     * Upper bound for a price filter. Both saved_searches.max_price and
     * listings.price are unsignedInteger, so an unbounded value is an
     * out-of-range insert on MySQL and Postgres and a silently out-of-domain
     * row on SQLite. This is a domain bound comfortably inside the column,
     * matching how min_bedrooms is capped at 20 rather than at 255.
     */
    public const MAX_PRICE = 100_000_000;

    public function __construct(
        public ?PropertyType $propertyType = null,
        public ?int $maxPrice = null,
        public ?int $minBedrooms = null,
        public ?string $region = null,
    ) {}

    /**
     * Build from request-shaped input, where everything arrives as a string and
     * a blank field means "no filter" rather than "an empty value".
     *
     * Callers are expected to have validated first — an unrecognised property
     * type here is a programming error, not user input.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $type = self::blankToNull($attributes['property_type'] ?? null);
        $maxPrice = self::blankToNull($attributes['max_price'] ?? null);
        $minBedrooms = self::blankToNull($attributes['min_bedrooms'] ?? null);

        return new self(
            propertyType: $type === null ? null : PropertyType::from($type),
            maxPrice: $maxPrice === null ? null : (int) $maxPrice,
            // "0 or more bedrooms" is every listing, so it is not a filter — and
            // treating it as one would let a search past the "at least one
            // criterion" guard and alert on everything. The form offers 0.
            //
            // Compared as a number, not as the string "0": only plain "0" gets
            // this far today because the integer rule rejects "00" and "0.0",
            // but that is validation's business, not this method's.
            minBedrooms: $minBedrooms === null || (int) $minBedrooms === 0 ? null : (int) $minBedrooms,
            region: self::blankToNull($attributes['region'] ?? null),
        );
    }

    /**
     * Validation rules for the criteria fields, shared by the filter form and
     * the saved-search form so the two cannot drift apart.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Narrow a listings query to the listings these criteria match.
     *
     * @param  Builder<Listing>  $query
     */
    public function applyTo(Builder $query): void
    {
        if ($this->propertyType !== null) {
            $query->where('property_type', $this->propertyType);
        }

        if ($this->maxPrice !== null) {
            $query->where('price', '<=', $this->maxPrice);
        }

        if ($this->minBedrooms !== null) {
            $query->where('bedrooms', '>=', $this->minBedrooms);
        }

        if ($this->region !== null) {
            $query->whereHas('branch', fn (Builder $branch) => $branch->where('region', $this->region));
        }
    }

    /**
     * Criteria with nothing set match every listing. Worth naming, because that
     * is a search we refuse to save rather than one we alert on nightly.
     */
    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /**
     * The query-string form — omits anything unset, so a link built from this
     * carries only the filters that are actually in play.
     *
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return array_filter([
            'property_type' => $this->propertyType?->value,
            'max_price' => $this->maxPrice,
            'min_bedrooms' => $this->minBedrooms,
            'region' => $this->region,
        ], fn (string|int|null $value) => $value !== null);
    }

    /**
     * A short human label, for a saved search the user never named and for the
     * alert digest.
     */
    public function summary(): string
    {
        $parts = array_filter([
            $this->propertyType?->label(),
            $this->region,
            $this->minBedrooms === null ? null : "{$this->minBedrooms}+ beds",
            $this->maxPrice === null ? null : 'up to £'.number_format($this->maxPrice),
        ]);

        return $parts === [] ? 'All listings' : implode(' · ', $parts);
    }

    /**
     * Treats an omitted field and a submitted-but-empty one the same way: the
     * filter form posts blanks for untouched fields.
     */
    private static function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) ($value instanceof PropertyType ? $value->value : $value));

        return $value === '' ? null : $value;
    }
}
