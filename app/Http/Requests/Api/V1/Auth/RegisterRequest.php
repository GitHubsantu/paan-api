<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:'.Phone::REGEX, Rule::unique('users', 'phone')->withoutTrashed()],
            'otp' => ['required', 'digits:'.config('otp.length')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 10-digit mobile number.',
            'phone.unique' => 'This phone number is already registered. Please log in.',
        ];
    }
}
