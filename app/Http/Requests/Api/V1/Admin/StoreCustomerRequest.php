<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
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
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'phone' => ['required', 'regex:'.Phone::REGEX, Rule::unique('users', 'phone')->where(fn ($query) => $query->where('role', 'customer'))->withoutTrashed()],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('phone') && User::withTrashed()->where('phone', $this->input('phone'))->where('role', 'admin')->exists()) {
                $validator->errors()->add('phone', "This phone number can't be used.");
            }
        }];
    }
}
