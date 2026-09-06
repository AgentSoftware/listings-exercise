<?php

namespace App\Http\Requests;

use App\Search\ListingCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The criteria rules are shared with the browse filters, so a search can
     * only ever be saved for something the site can actually search for.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...ListingCriteria::rules(),
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // A search with no criteria matches every new listing, which is
                // the one thing Support asked us not to build. The field list
                // comes from the criteria rules so it cannot drift.
                $hasCriterion = collect(array_keys(ListingCriteria::rules()))
                    ->contains(fn (string $field) => filled($this->input($field)));

                if (! $hasCriterion) {
                    $validator->errors()->add(
                        'criteria',
                        'Choose at least one filter — an empty search would alert you about every new listing.',
                    );
                }
            },
        ];
    }

    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromArray($this->validated());
    }
}
