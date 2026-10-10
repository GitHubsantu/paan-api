<?php

return [
    'length' => 6,
    'ttl_minutes' => 5,
    'resend_seconds' => 30,
    'max_attempts' => 5,
    // Mock is ignored automatically in production.
    'mock' => env('OTP_MOCK', false),
    'mock_code' => env('OTP_MOCK_CODE', '123456'),
];
