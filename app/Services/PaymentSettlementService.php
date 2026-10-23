<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\AdminNotifications\AdminOrderNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentSettlementService
{
    public function __construct(private AdminOrderNotifier $adminNotifier, private NotificationService $notifications) {}

    public function settle(Payment $payment, string $gatewayStatus, array $rawPayload = []): Payment
    {
        $notifyPaid = false;
        $latePaymentRequiresRefund = false;
        $settled = DB::transaction(function () use ($payment, $gatewayStatus, $rawPayload, &$notifyPaid, &$latePaymentRequiresRefund): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $status = strtolower($gatewayStatus);
            $attributes = ['raw_payload' => $rawPayload];

            if ($status === 'success') {
                $attributes['status'] = 'success';
                $attributes['failure_reason'] = null;
                if ($order->payment_status === PaymentStatus::Failed) {
                    $order->update(['refund_pending' => true]);
                    $latePaymentRequiresRefund = true;
                } elseif ($order->payment_status !== PaymentStatus::Paid) {
                    $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
                    $notifyPaid = true;
                }
            } elseif (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
                $attributes['status'] = $status;
                $attributes['failure_reason'] = match ($status) {
                    'expired' => 'Payment expired',
                    'cancelled' => 'Payment was cancelled',
                    default => 'Payment failed',
                };
                $hasSuccessfulPayment = Payment::query()->where('order_id', $order->id)->where('status', 'success')->exists();
                if (! $hasSuccessfulPayment && $order->payment_status !== PaymentStatus::Paid) {
                    $this->markOrderAsPaymentFailed($order, $attributes['failure_reason']);
                }
            } else {
                $attributes['status'] = 'pending';
            }

            $payment->update($attributes);

            return $payment->fresh();
        });

        if ($latePaymentRequiresRefund) {
            Log::warning('gateway.late_payment_requires_refund', ['order_id' => $settled->order_id, 'payment_id' => $settled->id]);
        }

        if ($notifyPaid) {
            $order = $settled->order()->with(['user.deviceTokens', 'items'])->firstOrFail();
            DB::afterCommit(function () use ($order): void {
                $this->adminNotifier->notifyNewOrder($order);
                $this->notifications->notifyPaymentReceived($order);
            });
        }

        return $settled;
    }

    public function expire(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            if ($order->payment_status !== PaymentStatus::Pending || $payment->status !== 'pending') {
                return $payment;
            }
            $payment->update(['status' => 'expired', 'failure_reason' => 'Payment was not completed in time']);
            $this->markOrderAsPaymentFailed($order, 'Payment was not completed in time');

            return $payment->fresh();
        });
    }

    private function markOrderAsPaymentFailed(Order $order, string $failureReason): void
    {
        $previousStatus = $order->status;
        $order->update([
            'payment_status' => PaymentStatus::Failed,
            'status' => OrderStatus::PaymentFailed,
            'completed_at' => now(),
            'pickup_code' => null,
        ]);
        if ($previousStatus !== OrderStatus::PaymentFailed) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $previousStatus,
                'new_status' => OrderStatus::PaymentFailed,
                'changed_by' => null,
                'note' => $failureReason,
            ]);
        }
    }
}
