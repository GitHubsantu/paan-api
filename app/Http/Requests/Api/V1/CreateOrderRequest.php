<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tokenCan('customer');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.addon_ids' => ['nullable', 'array', 'max:10'],
            'items.*.addon_ids.*' => ['integer', 'min:1', 'distinct'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.options.sweetness' => ['nullable', 'in:regular,mild,low_gulkand,extra_gulkand'],
            'items.*.options.supari' => ['nullable', 'in:fried,kacha'],
            'comment' => ['nullable', 'string', 'max:200'],
            'payment_method' => ['nullable', 'in:mock'],
            'payment_channel' => ['nullable', 'in:upi,card'],
        ];
    }
}
