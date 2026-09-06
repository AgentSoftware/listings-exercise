<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            // The criteria as a query string, so the page can link straight to
            // the browse results for this search.
            'criteria' => $this->criteria()->toArray(),
            'alerts_count' => $this->whenCounted('alerts'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
