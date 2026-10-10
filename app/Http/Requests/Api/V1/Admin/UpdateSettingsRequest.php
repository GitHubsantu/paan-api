<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'shop_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shop_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'shop_phone' => ['sometimes', 'nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'opening_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'closing_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'pickup_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'shop_status' => ['sometimes', 'required', 'in:open,closed'],
        ];
    }
}
