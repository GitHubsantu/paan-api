<?php

namespace App\Services;

use App\Jobs\SendFcmPush;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyOrderStatusChanged(Order $order): void
    {
        $order->loadMissing('user.deviceTokens');
        $order->user->notify(new OrderStatusChanged($order));
        [$title, $body] = match ($order->status->value) {
            'making' => ['Your paan is being prepared', "Order {$order->order_number} is being hand-rolled."],
            'ready' => ['Your order is ready for pickup', "Token #{$order->token} - show it at the counter."],
            'completed' => ['Order collected', 'Thank you! Enjoy your paan.'],
            default => ['Your order was cancelled by the shop', "Please contact the shop about order {$order->order_number}."],
        };
        $data = ['type' => 'order_status', 'order_id' => (string) $order->id, 'order_number' => (string) $order->order_number, 'status' => $order->status->value, 'token' => (string) $order->token, 'title' => $title, 'body' => $body];

        $order->user->deviceTokens->where('app', 'customer')->each(function ($deviceToken) use ($data, $order): void {
            try {
                SendFcmPush::dispatch($deviceToken->id, $data, 3600, "order_{$order->id}");
            } catch (\Throwable $exception) {
                Log::warning('fcm.push_dispatch_failed', ['order_id' => $order->id, 'device_token_id' => $deviceToken->id, 'exception' => $exception::class]);
            }
        });
    }
}
