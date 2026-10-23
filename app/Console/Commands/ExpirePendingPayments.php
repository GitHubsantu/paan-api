<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Gateway\GatewayClient;
use App\Services\Gateway\GatewayUnavailableException;
use App\Services\PaymentSettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-pending-payments')]
#[Description('Expire abandoned gateway checkout sessions after verifying their status')]
class ExpirePendingPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GatewayClient $gateway, PaymentSettlementService $settlement): int
    {
        $cutoff = now()->subMinutes((int) config('payment.payment_expiry_minutes'));
        $processed = 0;
        Order::query()
            ->where('payment_status', PaymentStatus::Pending)
            ->where('payment_method', 'gateway')
            ->select('id')
            ->chunkById(100, function ($orders) use ($cutoff, $gateway, $settlement, &$processed): void {
                foreach ($orders as $order) {
                    $payment = $order->payments()->where('status', 'pending')->latest('id')->first();
                    if (! $payment?->transaction_id) {
                        continue;
                    }
                    $startedAt = $payment->payment_started_at ?? $payment->created_at;
                    if ($startedAt === null || $startedAt->isAfter($cutoff)) {
                        continue;
                    }
                    try {
                        $transaction = $gateway->getTransaction($payment->transaction_id);
                        if (strtolower((string) ($transaction['status'] ?? '')) === 'success') {
                            $settlement->settle($payment, 'success', ['expiry_reconciliation' => $transaction]);
                        } else {
                            $settlement->expire($payment);
                        }
                        $processed++;
                    } catch (GatewayUnavailableException $exception) {
                        report($exception);
                        $this->warn("Skipped order {$order->id}: gateway unavailable.");
                    }
                }
            });
        $this->info("Processed {$processed} abandoned payment attempts.");

        return self::SUCCESS;
    }
}
