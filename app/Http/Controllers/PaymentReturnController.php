<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Gateway\GatewayClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentReturnController extends Controller
{
    public function __construct(private GatewayClient $gateway) {}

    public function __invoke(Request $request): View
    {
        $transactionId = $request->query('transaction_id');
        $merchantReference = $request->query('merchant_reference');
        $status = strtolower((string) $request->query('status'));
        $valid = $this->gateway->verifyReturnSignature($transactionId, $request->query('status'), $merchantReference, $request->query('signature'));
        $payment = Payment::query()->when($transactionId, fn ($query) => $query->where('transaction_id', $transactionId), fn ($query) => $query->where('merchant_reference', $merchantReference))->first();
        $completed = $valid && $status === 'success';
        $message = $completed ? 'Payment received' : (in_array($status, ['failed', 'cancelled', 'expired'], true) ? 'Payment not completed' : 'Confirming your payment…');

        return view('payment.return', ['message' => $message, 'orderId' => $payment?->order_id, 'ok' => $completed ? 1 : 0, 'scheme' => trim((string) config('services.gateway.deeplink_scheme'))]);
    }
}
