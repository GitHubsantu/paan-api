<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmPushSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushSenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_removes_a_token_when_fcm_reports_it_invalid(): void
    {
        config()->set('services.firebase.credentials', '/test/service-account.json');
        config()->set('services.firebase.project_id', 'paan-test');
        $this->app->instance(FcmAccessTokenProvider::class, new class implements FcmAccessTokenProvider
        {
            public function accessToken(string $credentials): string
            {
                return 'test-access-token';
            }
        });
        Http::preventStrayRequests();
        Http::fake(['https://fcm.googleapis.com/v1/projects/paan-test/messages:send' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404)]);
        $deviceToken = DeviceToken::create(['user_id' => User::factory()->create()->id, 'token' => 'invalid-token', 'platform' => 'android', 'app' => 'customer']);

        app(FcmPushSender::class)->send($deviceToken, ['type' => 'test'], 600);

        $this->assertDatabaseMissing('device_tokens', ['id' => $deviceToken->id]);
        Http::assertSent(fn ($request): bool => $request['message']['data'] === ['type' => 'test'] && ! isset($request['message']['notification']));
    }
}
