<?php

namespace App\Http\Resources;

use App\Enums\AlertFrequency;
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
        $criteria = $this->criteria();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $criteria->describe(),
            'alert_frequency' => [
                'value' => $this->alert_frequency->value,
                'label' => $this->alert_frequency->label(),
            ],
            // Never is a bookmark: it's re-run by hand and never alerts.
            'alerts_enabled' => $this->alert_frequency !== AlertFrequency::Never,
            'results_url' => route('listings.index', $criteria->toQuery(), false),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
