<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PendingPaymentReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile-pending')]
#[Description('Reconcile pending gateway payments with the payment gateway')]
class ReconcilePendingPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PendingPaymentReconciliationService $reconciliation): int
    {
        $count = 0;
        Payment::query()->with('order')->where('status', 'pending')->whereNotNull('transaction_id')->where('created_at', '>=', now()->subMinutes(30))->orderBy('id')->chunkById(100, function ($payments) use ($reconciliation, &$count): void {
            foreach ($payments as $payment) {
                $reconciliation->reconcilePayment($payment);
                $count++;
            }
        });
        $this->info("Reconciled {$count} pending payments.");

        return self::SUCCESS;
    }
}
