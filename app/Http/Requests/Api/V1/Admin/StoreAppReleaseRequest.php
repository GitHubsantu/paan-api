<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppReleaseRequest extends FormRequest
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
        return ['version_name' => ['required', 'string', 'max:30'], 'version_code' => ['required', 'integer', 'min:1', 'unique:app_releases,version_code,NULL,id,platform,android'], 'release_notes' => ['nullable', 'string', 'max:2000'], 'min_android' => ['nullable', 'string', 'max:10'], 'is_published' => ['nullable', 'boolean'], 'apk' => ['required', 'file']];
    }
}
