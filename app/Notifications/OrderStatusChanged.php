<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'message' => match ($this->order->status->value) {
                'making' => "Your order {$this->order->order_number} is being prepared.",
                'ready' => "Your order {$this->order->order_number} is ready for pickup.",
                'completed' => "Your order {$this->order->order_number} has been completed.",
                default => "Your order {$this->order->order_number} was cancelled by the shop.",
            },
            'reason' => $this->order->cancel_reason,
        ];
    }
}
