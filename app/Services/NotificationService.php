<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use App\Services\Push\PushSender;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private PushSender $push) {}

    public function notifyOrderStatusChanged(Order $order): void
    {
        try {
            $order->loadMissing('user.deviceTokens');
            $order->user->notify(new OrderStatusChanged($order));
            $payload = ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status->value];
            foreach ($order->user->deviceTokens as $token) {
                $this->push->send($token, $payload);
            }
        } catch (\Throwable $exception) {
            Log::warning('notification.failed', ['order_id' => $order->id]);
        }
    }
}
