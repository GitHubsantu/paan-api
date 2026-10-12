<?php

namespace App\Services\AdminNotifications;

use App\Jobs\SendFcmPush;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FcmAdminOrderNotifier implements AdminOrderNotifier
{
    public function notifyNewOrder(Order $order): void
    {
        $order->loadMissing('items');
        $customerName = trim((string) ($order->customer_name ?: $order->user?->name)) ?: 'Customer';
        $summary = $order->items->map(fn ($item): string => "{$item->quantity} x {$item->product_name_snapshot}")->implode(', ');
        $summary = mb_strimwidth($summary, 0, 200, '');
        $itemCount = (string) $order->items->sum('quantity');
        $total = (string) (float) $order->total;
        $data = [
            'type' => 'new_order',
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'token' => (string) $order->token,
            'items_count' => $itemCount,
            'total' => $total,
            'customer_name' => $customerName,
            'summary' => $summary,
            'title' => "New order {$order->order_number}",
            'body' => "{$itemCount} items - Rs {$total} - {$customerName}",
        ];

        User::query()->where('role', 'admin')->where('is_active', true)->with(['deviceTokens' => fn ($query) => $query->where('app', 'admin')])
            ->get()->pluck('deviceTokens')->flatten()->each(function ($deviceToken) use ($data, $order): void {
                try {
                    SendFcmPush::dispatch($deviceToken->id, $data, 600);
                } catch (\Throwable $exception) {
                    Log::warning('fcm.push_dispatch_failed', ['order_id' => $order->id, 'device_token_id' => $deviceToken->id, 'exception' => $exception::class]);
                }
            });
    }
}
