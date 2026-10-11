<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'token' => $this->token,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method,
            'payment_channel' => $this->payment_channel,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'comment' => $this->comment,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name_snapshot,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => (float) $item->line_total,
                'image_url' => Media::thumbnailUrl($item->product_image_snapshot),
                'options' => $item->options ?? ['sweetness' => 'regular', 'supari' => 'fried'],
                'addons' => $item->addons->map(fn ($a) => [
                    'id' => $a->addon_id,
                    'name' => $a->addon_name_snapshot,
                    'price' => (float) $a->unit_price,
                ])->values(),
            ])->values()),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->sortBy('created_at')->values()->map(fn ($history) => [
                'status' => $history->new_status->value,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
                'changed_by' => $history->changed_by,
                'admin' => $history->relationLoaded('changedBy') && $history->changedBy ? [
                    'id' => $history->changedBy->id,
                    'name' => $history->changedBy->name,
                ] : null,
            ])->all()),
            'customer' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'phone' => $this->user->phone]),
        ];
    }
}
