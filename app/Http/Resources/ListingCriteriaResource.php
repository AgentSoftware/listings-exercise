<?php

namespace App\Http\Resources;

use App\Support\ListingCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The submitted search (the current URL), as the listings page's save button
 * and modal need it. The front end never rebuilds any of this itself.
 *
 * @mixin ListingCriteria
 */
class ListingCriteriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_empty' => $this->isEmpty(),
            'summary' => $this->describe(),
            'default_name' => $this->defaultName(),
            'query' => (object) $this->toQuery(),
        ];
    }
}
