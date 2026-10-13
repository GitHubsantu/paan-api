<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type ?? $this->data['type'] ?? null,
            'title' => $this->title ?? $this->data['title'] ?? null,
            'body' => $this->body ?? $this->data['body'] ?? $this->data['message'] ?? null,
            'order_id' => $this->order_id === null ? ($this->data['order_id'] ?? null) : (int) $this->order_id,
            'status' => $this->status ?? $this->data['status'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
