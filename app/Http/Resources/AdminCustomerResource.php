<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'is_active' => $this->is_active, 'orders_count' => $this->orders_count, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
