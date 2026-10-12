<?php

namespace App\Services\Otp;

use App\Exceptions\ApiException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;

class OtpService
{
    public function __construct(private OtpSender $sender) {}

    public function isMock(): bool
    {
        return (bool) config('otp.mock') && ! app()->isProduction();
    }

    public function isMockForPhone(string $phone): bool
    {
        $mockPhones = array_filter(
            array_map('trim', explode(',', (string) config('otp.mock_phones'))),
        );

        return $this->isMock() && in_array($phone, $mockPhones, true);
    }

    /** Returns the plaintext code only for an allowlisted mock phone. */
    public function issue(string $phone): ?string
    {
        $last = OtpCode::where('phone', $phone)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(config('otp.resend_seconds')))) {
            throw new ApiException('Please wait before requesting another OTP.', 429);
        }

        OtpCode::where('phone', $phone)->whereNull('consumed_at')->delete();

        $isMockPhone = $this->isMockForPhone($phone);
        $code = $isMockPhone ? (string) config('otp.mock_code') : $this->generate();

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes(config('otp.ttl_minutes')),
        ]);

        $this->sender->send($phone, $code);

        return $isMockPhone ? $code : null;
    }

    public function verify(string $phone, string $code): void
    {
        // Outcome is decided inside the transaction, exception thrown after commit
        // so the attempt counter increment is never rolled back.
        $result = DB::transaction(function () use ($phone, $code) {
            $otp = OtpCode::where('phone', $phone)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->expires_at->isPast()) {
                return 'invalid';
            }
            if ($otp->attempts >= config('otp.max_attempts')) {
                return 'locked';
            }

            $otp->increment('attempts');

            if (! hash_equals($otp->code_hash, $this->hash($phone, $code))) {
                return 'invalid';
            }

            $otp->update(['consumed_at' => now()]);

            return 'ok';
        });

        if ($result === 'locked') {
            throw new ApiException('Too many incorrect attempts. Please request a new OTP.', 429);
        }
        if ($result !== 'ok') {
            throw new ApiException('The OTP is incorrect or has expired.', 422, ['otp' => ['The OTP is incorrect or has expired.']], 'OTP_INVALID');
        }
    }

    private function generate(): string
    {
        $len = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, config('app.key'));
    }
}
