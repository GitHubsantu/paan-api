<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
        return ['phone' => ['required', 'regex:'.Phone::REGEX], 'otp' => ['required', 'digits:'.config('otp.length')]];
    }
}
