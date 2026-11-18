<?php

namespace App\Http\Requests;

use App\Support\ListingCriteria;
use Illuminate\Foundation\Http\FormRequest;

class ListingIndexRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromInput($this->validated());
    }
}
