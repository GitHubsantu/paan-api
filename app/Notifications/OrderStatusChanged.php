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
        [$title, $body] = match ($this->order->status->value) {
            'making' => ['Your paan is being prepared', "Order {$this->order->order_number} is being hand-rolled."],
            'ready' => ['Your order is ready for pickup', "Token #{$this->order->token} - show it at the counter."],
            'completed' => ['Order collected', 'Thank you! Enjoy your paan.'],
            default => ['Your order was cancelled by the shop', "Please contact the shop about order {$this->order->order_number}."],
        };

        return [
            'type' => 'order_status',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'title' => $title,
            'body' => $body,
        ];
    }
}
