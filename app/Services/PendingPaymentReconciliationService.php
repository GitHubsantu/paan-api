<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Gateway\GatewayClient;
use App\Services\Gateway\GatewayUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PendingPaymentReconciliationService
{
    public function __construct(private GatewayClient $gateway, private PaymentSettlementService $settlement) {}

    public function reconcileOrder(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Pending) {
            return;
        }
        $payment = $order->payments()->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(30))->latest()->first();
        if (! $payment?->transaction_id) {
            return;
        }
        $lock = Cache::lock("gateway-payment-reconcile:{$order->id}", 5);
        if (! $lock->get()) {
            return;
        }
        try {
            $transaction = $this->gateway->getTransaction($payment->transaction_id);
            $this->settlement->settle($payment, (string) ($transaction['status'] ?? ''), ['reconciled_transaction' => $transaction]);
        } catch (GatewayUnavailableException $exception) {
            Log::warning('gateway.payment_reconciliation_failed', ['order_id' => $order->id, 'payment_id' => $payment->id, 'exception' => $exception::class]);
        }
    }

    public function reconcilePayment(Payment $payment): void
    {
        $order = $payment->order;
        if ($order !== null) {
            $this->reconcileOrder($order);
        }
    }
}
