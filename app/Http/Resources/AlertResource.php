<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_unread' => $this->read_at === null,
            'created_at' => $this->created_at->toIso8601String(),
            'listing' => new ListingResource($this->listing),
            // Null when the matched search has since been deleted; the alert
            // itself is kept.
            'search_name' => $this->savedSearch?->name,
            'search_summary' => $this->savedSearch?->criteria()->describe(),
        ];
    }
}
