<?php

namespace App\Services\Otp;

/** Dev placeholder. Replace with an SMS provider implementation later. */
class NullOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        // Intentionally does nothing. OTP values are never logged.
    }
}
