<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('ingredients_json') && is_string($this->input('ingredients_json'))) {
            $this->merge(['ingredients' => json_decode($this->input('ingredients_json'), true)]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->tokenCan('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'], 'name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500'], 'price' => ['required', 'numeric', 'min:0'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'remove_image' => ['nullable', 'boolean'], 'is_available' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'addon_ids' => ['nullable', 'array'], 'addon_ids.*' => ['integer', 'exists:addons,id'],
            'tag' => ['nullable', 'string', 'max:30'], 'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:60'], 'ingredients' => ['nullable', 'array', 'max:10'], 'ingredients.*' => ['string', 'max:40'],
            'ingredients_json' => ['nullable', 'json'],
        ];
    }
}
