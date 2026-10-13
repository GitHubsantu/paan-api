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
use Illuminate\Support\Facades\RateLimiter;

class OrderStatusService
{
    public function __construct(private PaymentService $payments, private NotificationService $notifications) {}

    public function transition(Order $order, OrderStatus $to, User $admin, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $admin, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            return $this->transitionLocked($order, $to, $admin, $note, $to === OrderStatus::Completed ? 'admin' : null);
        });
    }

    public function pickup(int $orderId, User $customer, string $code): Order
    {
        return DB::transaction(function () use ($orderId, $customer, $code) {
            $order = Order::query()->where('user_id', $customer->id)->lockForUpdate()->find($orderId);
            if (! $order) {
                throw new ApiException('Order not found.', 404);
            }
            if ($order->status === OrderStatus::Completed) {
                throw new ApiException('This order was already picked up.', 409, [], 'ALREADY_PICKED_UP');
            }
            if ($order->status !== OrderStatus::Ready) {
                throw new ApiException('This order is not ready for pickup yet.', 409, [], 'ORDER_NOT_READY');
            }

            $key = "pickup:{$customer->id}:{$order->id}";
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw new ApiException('Too many attempts. Please ask the counter for help.', 429, [], 'TOO_MANY_ATTEMPTS');
            }
            if ($order->pickup_code === null || ! hash_equals($order->pickup_code, $code)) {
                RateLimiter::hit($key, 600);

                throw new ApiException('This QR code is not valid for your order.', 422, [], 'PICKUP_CODE_INVALID');
            }

            RateLimiter::clear($key);

            return $this->transitionLocked($order, OrderStatus::Completed, $customer, null, 'qr');
        });
    }

    public function ensurePickupCode(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === OrderStatus::Ready && $order->pickup_code === null) {
                $order->update(['pickup_code' => $this->newPickupCode()]);
            }

            return $order;
        });
    }

    public function cancel(Order $order, User $admin, string $reason): Order
    {
        return DB::transaction(function () use ($order, $admin, $reason) {
            $order = Order::query()->with('payment')->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! in_array($from, [OrderStatus::Pending, OrderStatus::Making, OrderStatus::Ready], true)) {
                throw new ApiException("Order cannot be cancelled from {$from->value}.");
            }

            $order->update([
                'status' => OrderStatus::AdminCancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'pickup_code' => null,
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

            $order = $this->loadOrder($order);
            DB::afterCommit(fn () => $this->notifications->notifyOrderStatusChanged($order));

            return $order;
        });
    }

    private function loadOrder(Order $order): Order
    {
        return $order->fresh([
            'user:id,name,phone',
            'items.addons',
            'payment',
            'statusHistories.changedBy:id,name',
        ]);
    }

    private function transitionLocked(Order $order, OrderStatus $to, User $changedBy, ?string $note, ?string $completedVia): Order
    {
        $from = $order->status;
        if ($from === $to) {
            return $this->loadOrder($order);
        }
        if (! $from->canTransitionTo($to)) {
            throw new ApiException("This order can't move from {$from->value} to {$to->value}.");
        }

        $attributes = [
            'status' => $to,
            'completed_at' => $to === OrderStatus::Completed ? now() : $order->completed_at,
        ];
        if ($to === OrderStatus::Ready) {
            $attributes['pickup_code'] = $this->newPickupCode();
        }
        if ($to === OrderStatus::Completed) {
            $attributes['pickup_code'] = null;
            $attributes['completed_via'] = $completedVia;
        }
        if ($to === OrderStatus::AdminCancelled) {
            $attributes['pickup_code'] = null;
        }
        $order->update($attributes);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'old_status' => $from,
            'new_status' => $to,
            'changed_by' => $changedBy->id,
            'note' => $note,
        ]);
        Log::info('order.status_changed', ['order_id' => $order->id, 'admin_id' => $changedBy->id]);

        $order = $this->loadOrder($order);
        DB::afterCommit(fn () => $this->notifications->notifyOrderStatusChanged($order));

        return $order;
    }

    private function newPickupCode(): string
    {
        return bin2hex(random_bytes(24));
    }
}
