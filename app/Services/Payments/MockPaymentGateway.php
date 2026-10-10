<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment;
use Illuminate\Support\Str;

/** DEV ONLY. Never trusts the client; marks paid itself. Refuses to run in production. */
class MockPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function authorize(Payment $payment, array $input): array
    {
        if (app()->isProduction()) {
            throw new ApiException('Payments are not configured.', 503);
        }

        return [
            'status' => PaymentStatus::Paid,
            'reference' => 'MOCK-'.Str::upper(Str::random(10)),
        ];
    }
}
