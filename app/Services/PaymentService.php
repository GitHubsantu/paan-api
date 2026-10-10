<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private PaymentGateway $gateway) {}

    public function createForOrder(Order $order, array $input = []): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $this->gateway->name(),
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        $result = $this->gateway->authorize($payment, $input);

        $payment->update([
            'status' => $result['status'],
            'payment_reference' => $result['reference'],
            'paid_at' => $result['status'] === PaymentStatus::Paid ? now() : null,
        ]);

        $order->update(['payment_status' => $result['status']]);

        Log::info('payment.status_changed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'status' => $result['status']->value,
        ]);

        return $payment;
    }

    public function refundForOrder(Order $order): void
    {
        $payment = $order->payment;

        if (! $payment || $payment->status !== PaymentStatus::Paid || $payment->provider !== 'mock') {
            return;
        }

        $payment->update(['status' => PaymentStatus::Refunded]);
        $order->update(['payment_status' => PaymentStatus::Refunded]);

        Log::info('payment.refunded', ['order_id' => $order->id, 'payment_id' => $payment->id]);
    }
}
