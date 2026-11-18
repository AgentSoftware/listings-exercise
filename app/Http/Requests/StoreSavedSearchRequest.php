<?php

namespace App\Http\Requests;

use App\Enums\AlertFrequency;
use App\Support\ListingCriteria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...ListingCriteria::rules(),
            'name' => ['nullable', 'string', 'max:100'],
            'alert_frequency' => ['required', new Enum(AlertFrequency::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Read the raw data: validated() would re-run validation from
                // inside this hook. The field rules have already passed.
                $criteria = ListingCriteria::fromInput($validator->getData());

                // An empty search would alert on every new listing.
                if ($criteria->isEmpty()) {
                    $validator->errors()->add('criteria', 'Choose at least one filter before saving a search.');

                    return;
                }

                // Checked here rather than with a unique index: the criteria
                // columns are nullable, and NULLs never collide in a unique index.
                if ($this->user()?->savedSearches()->withCriteria($criteria)->exists()) {
                    $validator->errors()->add('criteria', 'You have already saved this search.');
                }
            },
        ];
    }

    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromInput($this->validated());
    }
}
