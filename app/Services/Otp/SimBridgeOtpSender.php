<?php

namespace App\Services\Otp;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SimBridgeOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        $baseUrl = rtrim((string) config('services.simbridge.base_url'), '/');
        $apiKey = (string) config('services.simbridge.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new ApiException('OTP delivery is not configured.', 503, [], 'OTP_UNAVAILABLE');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->retry([100, 300])
                ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
                ->post($baseUrl.'/messages', [
                    'to' => '+91'.$phone,
                    'message' => "Safar Paan OTP: {$code}. Valid for ".config('otp.ttl_minutes').' minutes. Do not share it.',
                ]);
        } catch (ConnectionException|RequestException) {
            throw new ApiException('OTP delivery is temporarily unavailable. Please try again.', 503, [], 'OTP_UNAVAILABLE');
        }

        if (! $response->accepted() && ! ($response->ok() && $response->json('idempotent_replay') === true)) {
            throw new ApiException('OTP delivery is temporarily unavailable. Please try again.', 503, [], 'OTP_UNAVAILABLE');
        }
    }
}
