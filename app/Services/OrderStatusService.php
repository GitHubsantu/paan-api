<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    public function __construct(private PaymentService $payments) {}

    public function transition(Order $order, OrderStatus $to, User $admin, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $admin, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! $from->canTransitionTo($to)) {
                throw new ApiException("Order cannot transition from {$from->value} to {$to->value}.", 409);
            }

            $order->update([
                'status' => $to,
                'completed_at' => $to === OrderStatus::Completed ? now() : $order->completed_at,
            ]);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $from,
                'new_status' => $to,
                'changed_by' => $admin->id,
                'note' => $note,
            ]);
            Log::info('order.status_changed', ['order_id' => $order->id, 'admin_id' => $admin->id]);

            return $order->fresh(['items.addons', 'payment', 'statusHistories']);
        });
    }

    public function cancel(Order $order, User $admin, string $reason): Order
    {
        return DB::transaction(function () use ($order, $admin, $reason) {
            $order = Order::query()->with('payment')->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! in_array($from, [OrderStatus::Pending, OrderStatus::Making, OrderStatus::Ready], true)) {
                throw new ApiException("Order cannot be cancelled from {$from->value}.", 409);
            }

            $order->update([
                'status' => OrderStatus::AdminCancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $from,
                'new_status' => OrderStatus::AdminCancelled,
                'changed_by' => $admin->id,
                'note' => $reason,
            ]);
            if ($order->payment_status === PaymentStatus::Paid) {
                $this->payments->refundForOrder($order);
            }
            Log::info('order.cancelled', ['order_id' => $order->id, 'admin_id' => $admin->id]);

            return $order->fresh(['items.addons', 'payment', 'statusHistories']);
        });
    }
}
