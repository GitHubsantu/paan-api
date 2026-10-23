<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Gateway\GatewayClient;
use App\Services\Gateway\GatewayUnavailableException;
use App\Services\PaymentSettlementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayWebhookController extends Controller
{
    public function __construct(private GatewayClient $gateway, private PaymentSettlementService $settlement) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signatureIsValid = $this->gateway->verifyWebhookSignature($rawBody, $request->header('X-Signature'));
        $payload = json_decode($rawBody, true);
        Log::info('gateway webhook received', [
            'transaction_id' => is_array($payload) ? $payload['transaction_id'] ?? null : null,
            'signature_valid' => $signatureIsValid,
        ]);
        if (! $signatureIsValid) {
            Log::info('gateway webhook result', ['http_status' => 401]);

            return ApiResponse::error('Invalid webhook signature.', 401);
        }
        if (! is_array($payload) || ! is_string($payload['transaction_id'] ?? null) || $payload['transaction_id'] === '') {
            Log::info('gateway webhook result', ['http_status' => 400]);

            return ApiResponse::error('Invalid webhook payload.', 400);
        }
        $payment = Payment::query()->where('transaction_id', $payload['transaction_id'])->first();
        if (! $payment) {
            Log::info('gateway webhook result', ['transaction_id' => $payload['transaction_id'], 'http_status' => 400]);

            return ApiResponse::error('Unknown transaction.', 400);
        }
        try {
            $transaction = $this->gateway->getTransaction($payment->transaction_id);
        } catch (GatewayUnavailableException $exception) {
            throw $exception;
        }
        $this->settlement->settle($payment, (string) ($transaction['status'] ?? ''), ['webhook' => $payload, 'transaction' => $transaction]);
        Log::info('gateway webhook result', ['transaction_id' => $payment->transaction_id, 'http_status' => 200]);

        return ApiResponse::success(null, 'Webhook processed.');
    }
}
