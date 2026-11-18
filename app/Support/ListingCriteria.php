<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Enum;

/**
 * The listing search rules, in one place.
 *
 * The listings page filters with it (forward match: criteria → listings), a
 * saved search stores it as columns (SavedSearch::criteria()), and the
 * summary text shown in the app and in alert emails comes only from describe().
 * The reverse match (listing → saved searches) is SavedSearch::scopeMatching(),
 * which must agree with applyTo(); a parity test guards that.
 */
final readonly class ListingCriteria
{
    /**
     * The query-string keys, which are also the saved_searches column names.
     */
    public const KEYS = ['property_type', 'max_price', 'min_bedrooms', 'region'];

    public function __construct(
        public ?PropertyType $propertyType = null,
        public ?int $maxPrice = null,
        public ?int $minBedrooms = null,
        public ?string $region = null,
    ) {}

    /**
     * Validation rules shared by every request that accepts search criteria.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Build from validated input (or model attributes). Blank values mean "any";
     * keys other than the criteria are ignored.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $blank = fn (string $key): bool => ! isset($input[$key]) || $input[$key] === '';

        $type = $input['property_type'] ?? null;

        return new self(
            propertyType: $blank('property_type') ? null : ($type instanceof PropertyType ? $type : PropertyType::from((string) $type)),
            maxPrice: $blank('max_price') ? null : (int) $input['max_price'],
            minBedrooms: $blank('min_bedrooms') ? null : (int) $input['min_bedrooms'],
            region: $blank('region') ? null : (string) $input['region'],
        );
    }

    /**
     * Forward match: narrow a listings query to these criteria.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->when($this->propertyType, fn (Builder $q, PropertyType $type) => $q->where('property_type', $type))
            ->when($this->maxPrice !== null, fn (Builder $q) => $q->where('price', '<=', $this->maxPrice))
            ->when($this->minBedrooms !== null, fn (Builder $q) => $q->where('bedrooms', '>=', $this->minBedrooms))
            ->when($this->region !== null, fn (Builder $q) => $q->whereHas(
                'branch',
                fn (Builder $branch) => $branch->where('region', $this->region),
            ));
    }

    public function isEmpty(): bool
    {
        return $this->toQuery() === [];
    }

    /**
     * The set criteria as query-string parameters, so a saved search can link
     * back to the listings page (`/?…`).
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'property_type' => $this->propertyType?->value,
            'max_price' => $this->maxPrice,
            'min_bedrooms' => $this->minBedrooms,
            'region' => $this->region,
        ], fn ($value) => $value !== null);
    }

    /**
     * Every criterion column, including the unset (null) ones, so the result
     * can be written to or compared against a saved_searches row.
     *
     * @return array{property_type: ?PropertyType, max_price: ?int, min_bedrooms: ?int, region: ?string}
     */
    public function toAttributes(): array
    {
        return [
            'property_type' => $this->propertyType,
            'max_price' => $this->maxPrice,
            'min_bedrooms' => $this->minBedrooms,
            'region' => $this->region,
        ];
    }

    /**
     * The one human-readable summary, e.g. "Flats in Manchester · 2+ beds · up to £200,000".
     * Used by SavedSearchResource, the save modal and the alert email.
     */
    public function describe(): string
    {
        $parts = [
            ($this->propertyType?->pluralLabel() ?? 'Properties')
                .($this->region !== null ? " in {$this->region}" : ''),
        ];

        if ($this->minBedrooms !== null) {
            $parts[] = "{$this->minBedrooms}+ beds";
        }

        if ($this->maxPrice !== null) {
            $parts[] = 'up to £'.number_format($this->maxPrice);
        }

        return implode(' · ', $parts);
    }

    /**
     * The name a saved search gets when the user leaves it blank.
     */
    public function defaultName(): string
    {
        return $this->region ?? 'All areas';
    }
}
