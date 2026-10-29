<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'serial' => ['nullable', 'string', 'max:200'],
            'purchased_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config('soloinventory.photo_max_kb'),
                'dimensions:max_width=8000,max_height=8000',
            ],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter an item name.',
            'quantity.required' => 'Enter a quantity.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity must not exceed 999999.',
            'section_id.required' => 'Select a section.',
            'category_id.required' => 'Select a category.',
            'section_id.exists' => 'The section no longer exists.',
            'category_id.exists' => 'The category no longer exists.',
            'photo.image' => 'Choose a JPEG, PNG or WebP photo.',
            'photo.mimes' => 'Choose a JPEG, PNG or WebP photo.',
            'photo.max' => 'The photo must not exceed 12 MB.',
            'photo.dimensions' => 'The photo must not exceed 8000 × 8000 pixels.',
        ];
    }
}
