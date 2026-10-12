<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FcmPushSender implements PushSender
{
    public function __construct(private FcmAccessTokenProvider $accessTokenProvider) {}

    public function send(DeviceToken $token, array $payload, int $ttl, ?string $collapseKey = null): void
    {
        $credentials = config('services.firebase.credentials');
        $projectId = config('services.firebase.project_id');

        if (! is_string($credentials) || $credentials === '' || ! is_string($projectId) || $projectId === '') {
            throw new \RuntimeException('Firebase credentials and project ID must be configured.');
        }

        $accessToken = $this->accessTokenProvider->accessToken($credentials);

        $android = ['priority' => 'HIGH', 'ttl' => $ttl.'s'];
        if ($collapseKey !== null) {
            $android['collapse_key'] = $collapseKey;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => ['token' => $token->token, 'data' => $payload, 'android' => $android],
                ]);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('Unable to reach FCM.', previous: $exception);
        }

        if ($response->successful()) {
            return;
        }

        if (in_array($response->json('error.status'), ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
            $token->delete();

            return;
        }

        $response->throw();
    }
}
