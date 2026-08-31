<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_type' => $this->property_type,
            'max_price' => $this->max_price,
            'min_bedrooms' => $this->min_bedrooms,
            'region' => $this->region,
            'created_at' => $this->created_at,
        ];
    }
}
