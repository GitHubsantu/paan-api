<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Gateway\GatewayClient;
use App\Services\Gateway\GatewayUnavailableException;
use App\Services\PaymentSettlementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private GatewayClient $gateway, private PaymentSettlementService $settlement) {}

    public function pay(Request $request, int $order): JsonResponse
    {
        $order = Order::query()->findOrFail($order);
        abort_unless($order->user_id === $request->user()->id, 403);
        if ($order->status === OrderStatus::AdminCancelled) {
            return ApiResponse::error('This order can no longer be paid.', 422, [], [], 'ORDER_NOT_PAYABLE');
        }
        if ($order->payment_status === PaymentStatus::Paid) {
            return $this->alreadyPaid($order);
        }
        if ($order->payment_status === PaymentStatus::Failed) {
            return $this->failedPayment($order);
        }
        $recentPending = $order->payments()->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(15))->latest()->first();
        if ($recentPending && $recentPending->transaction_id) {
            try {
                $data = $this->gateway->getTransaction($recentPending->transaction_id);
                $recentPending = $this->settlement->settle($recentPending, (string) ($data['status'] ?? ''), $data);
            } catch (GatewayUnavailableException $exception) {
                Log::warning('gateway.payment_status_lookup_failed', ['payment_id' => $recentPending->id, 'exception' => $exception::class]);

                return ApiResponse::error('Payment service is unavailable. Please try again.', 502, [], [], 'PAYMENT_SERVICE_UNAVAILABLE');
            }
            $order->refresh();
            if ($order->payment_status === PaymentStatus::Paid) {
                return $this->alreadyPaid($order);
            }
            if ($order->payment_status === PaymentStatus::Failed) {
                return $this->failedPayment($order);
            }
            if ($recentPending->status === 'pending' && $recentPending->checkout_url) {
                return $this->checkoutResponse($order, $recentPending->checkout_url);
            }
        }
        $payment = DB::transaction(function () use ($order): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $attempt = $lockedOrder->payments()->lockForUpdate()->count() + 1;

            return $lockedOrder->payments()->create([
                'merchant_reference' => "PAAN-{$lockedOrder->id}-{$attempt}",
                'amount' => $lockedOrder->total,
                'status' => 'pending',
            ]);
        });
        try {
            $created = $this->gateway->createTransaction([
                'domain' => (string) config('services.gateway.domain'),
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => 'INR',
                'customer_name' => $request->user()->name ?: 'Customer',
                'customer_email' => $request->user()->email ?: 'noemail@paan.app',
                'merchant_reference' => $payment->merchant_reference,
                'return_url' => (string) config('services.gateway.return_url'),
            ]);
        } catch (GatewayUnavailableException $exception) {
            $payment->update(['status' => 'failed', 'failure_reason' => 'Payment service unavailable']);
            Log::warning('gateway.transaction_create_failed', ['payment_id' => $payment->id, 'exception' => $exception::class]);

            return ApiResponse::error('Payment service is unavailable. Please try again.', 502, [], [], 'PAYMENT_SERVICE_UNAVAILABLE');
        }
        $payment->update([
            'transaction_id' => $created['transaction_id'],
            'checkout_url' => $created['checkout_url'],
            'payment_started_at' => now(),
            'raw_payload' => $created['raw'],
        ]);

        return $this->checkoutResponse($order, $created['checkout_url']);
    }

    private function alreadyPaid(Order $order): JsonResponse
    {
        return ApiResponse::success(['order_id' => (string) $order->id, 'payment_status' => PaymentStatus::Paid->value, 'checkout_url' => null]);
    }

    private function checkoutResponse(Order $order, string $checkoutUrl): JsonResponse
    {
        return ApiResponse::success(['order_id' => (string) $order->id, 'payment_status' => PaymentStatus::Pending->value, 'checkout_url' => $checkoutUrl]);
    }

    private function failedPayment(Order $order): JsonResponse
    {
        return ApiResponse::error('This payment attempt has failed and cannot be retried.', 422, [], [], 'PAYMENT_FAILED');
    }
}
