<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'], 'name' => ['sometimes', 'required', 'string', 'max:80'], 'description' => ['sometimes', 'nullable', 'string', 'max:500'], 'price' => ['sometimes', 'numeric', 'min:0'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'remove_image' => ['nullable', 'boolean'], 'is_available' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'addon_ids' => ['sometimes', 'array'], 'addon_ids.*' => ['integer', 'exists:addons,id'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:30'], 'prep_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'], 'ingredients' => ['sometimes', 'nullable', 'array', 'max:10'], 'ingredients.*' => ['string', 'max:40'],
            'ingredients_json' => ['nullable', 'json'],
        ];
    }
}
