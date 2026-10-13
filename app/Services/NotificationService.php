<?php

namespace App\Services;

use App\Jobs\SendFcmPush;
use App\Models\CustomerNotification;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyOrderStatusChanged(Order $order): void
    {
        $order->loadMissing('user.deviceTokens');
        if ($order->user === null) {
            return;
        }
        [$title, $body] = match ($order->status->value) {
            'making' => ['Your paan is being prepared', "Order {$order->order_number} is being hand-rolled."],
            'ready' => ['Your order is ready for pickup', "Token #{$order->token} - show it at the counter."],
            'completed' => ['Order collected', 'Thank you! Enjoy your paan.'],
            default => ['Your order was cancelled by the shop', "Please contact the shop about order {$order->order_number}."],
        };
        $notification = CustomerNotification::create([
            'user_id' => $order->user->id,
            'type' => 'order_status',
            'title' => $title,
            'body' => $body,
            'order_id' => $order->id,
            'status' => $order->status->value,
            'notifiable_type' => $order->user::class,
            'notifiable_id' => $order->user->id,
            'data' => ['type' => 'order_status', 'order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status->value, 'title' => $title, 'body' => $body],
        ]);
        $data = ['type' => 'order_status', 'order_id' => (string) $order->id, 'order_number' => (string) $order->order_number, 'status' => $order->status->value, 'token' => (string) $order->token, 'title' => $title, 'body' => $body, 'notification_id' => (string) $notification->id];

        $order->user->deviceTokens->where('app', 'customer')->each(function ($deviceToken) use ($data, $order): void {
            try {
                SendFcmPush::dispatch($deviceToken->id, $data, 3600, "order_{$order->id}");
            } catch (\Throwable $exception) {
                Log::warning('fcm.push_dispatch_failed', ['order_id' => $order->id, 'device_token_id' => $deviceToken->id, 'exception' => $exception::class]);
            }
        });
    }
}
