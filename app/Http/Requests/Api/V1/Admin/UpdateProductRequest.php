<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
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
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'], 'name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'price' => ['sometimes', 'numeric', 'min:0'], 'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'is_available' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'addon_ids' => ['sometimes', 'array'], 'addon_ids.*' => ['integer', 'exists:addons,id'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:30'], 'prep_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'], 'ingredients' => ['sometimes', 'nullable', 'array', 'max:10'], 'ingredients.*' => ['string', 'max:40'],
        ];
    }
}
