<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDownloadSettingsRequest extends FormRequest
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
        return ['download_mode' => ['required', Rule::in(['apk', 'play_store', 'coming_soon'])], 'play_store_url' => ['nullable', 'url', 'max:2048', Rule::requiredIf($this->input('download_mode') === 'play_store'), 'regex:/^https:\/\//i']];
    }
}
