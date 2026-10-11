<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tokenCan('customer');
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,making,ready,completed,admin_cancelled,active'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
