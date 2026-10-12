<?php

return [
    'length' => 6,
    'ttl_minutes' => 5,
    'resend_seconds' => 30,
    'max_attempts' => 5,
    // Mock codes are limited to listed development phones and are ignored in production.
    'mock' => env('OTP_MOCK', false),
    'mock_code' => env('OTP_MOCK_CODE', '123456'),
    'mock_phones' => env('OTP_MOCK_PHONES', ''),
];
