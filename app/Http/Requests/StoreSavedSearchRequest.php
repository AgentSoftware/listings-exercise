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
                // Nothing to say until the individual fields are valid — and
                // building criteria from an unrecognised property type throws.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // A search with no criteria matches every new listing, which is
                // the one thing Support asked us not to build.
                //
                // Ask the value object rather than testing the fields for
                // presence: "0 or more bedrooms" is a value but not a filter,
                // and filled() cannot tell those apart.
                if (ListingCriteria::fromArray($validator->validated())->isEmpty()) {
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
