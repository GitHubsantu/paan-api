<?php

namespace Tests\Feature;

use App\Services\Otp\SimBridgeOtpSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimBridgeOtpSenderTest extends TestCase
{
    public function test_sends_an_otp_with_the_simbridge_contract(): void
    {
        config([
            'services.simbridge.base_url' => 'https://simbridge.test/api/v1',
            'services.simbridge.api_key' => 'test-key',
        ]);
        Http::preventStrayRequests();
        Http::fake(['simbridge.test/api/v1/messages' => Http::response([
            'success' => true,
            'message_id' => '0123456789abcdef0123456789abcdef',
            'status' => 'SUBMITTING',
        ], 202)]);

        app(SimBridgeOtpSender::class)->send('9888888887', '123456');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://simbridge.test/api/v1/messages'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->hasHeader('Idempotency-Key')
                && $request->data() === [
                    'to' => '+919888888887',
                    'message' => 'Safar Paan OTP: 123456. Valid for 5 minutes. Do not share it.',
                ];
        });
    }

    public function test_rejects_an_unavailable_simbridge_service(): void
    {
        config([
            'services.simbridge.base_url' => 'https://simbridge.test/api/v1',
            'services.simbridge.api_key' => 'test-key',
        ]);
        Http::preventStrayRequests();
        Http::fake(['simbridge.test/api/v1/messages' => Http::response([], 503)]);

        $this->expectExceptionMessage('OTP delivery is temporarily unavailable. Please try again.');

        app(SimBridgeOtpSender::class)->send('9888888887', '123456');
    }

    public function test_accepts_an_idempotent_replay_from_simbridge(): void
    {
        config([
            'services.simbridge.base_url' => 'https://simbridge.test/api/v1',
            'services.simbridge.api_key' => 'test-key',
        ]);
        Http::preventStrayRequests();
        Http::fake(['simbridge.test/api/v1/messages' => Http::response([
            'success' => true,
            'idempotent_replay' => true,
        ])]);

        app(SimBridgeOtpSender::class)->send('9888888887', '123456');

        Http::assertSentCount(1);
    }
}
