<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    /**
     * Start/verify a payment server-side.
     *
     * @return array{status: PaymentStatus, reference: ?string}
     */
    public function authorize(Payment $payment, array $input): array;
}
