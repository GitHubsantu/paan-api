<?php

return [
    // 'mock' for local dev. Add 'razorpay' later by binding a new PaymentGateway.
    'driver' => env('PAYMENT_DRIVER', 'mock'),
];
