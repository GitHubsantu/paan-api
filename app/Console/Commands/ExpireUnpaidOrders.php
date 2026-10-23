<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('payments:expire-unpaid-orders')]
#[Description('Mark abandoned unpaid orders as failed')]
class ExpireUnpaidOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = 0;
        Order::query()->where('payment_status', PaymentStatus::Pending)->where('created_at', '<=', now()->subMinutes(30))->select('id')->chunkById(100, function ($orders) use (&$expired): void {
            foreach ($orders as $order) {
                $changed = DB::transaction(function () use ($order): bool {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                    $hasRecentPending = $lockedOrder->payments()->where('status', 'pending')->where('created_at', '>', now()->subMinutes(15))->exists();
                    if ($lockedOrder->payment_status !== PaymentStatus::Pending || $hasRecentPending) {
                        return false;
                    }
                    $lockedOrder->update(['payment_status' => PaymentStatus::Failed]);

                    return true;
                });
                $expired += $changed ? 1 : 0;
            }
        });
        $this->info("Expired {$expired} unpaid orders.");

        return self::SUCCESS;
    }
}
