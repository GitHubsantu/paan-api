<?php

namespace App\Services\Push;

use Google\Client as GoogleClient;

class GoogleFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    public function accessToken(string $credentials): string
    {
        $client = new GoogleClient;
        $client->setAuthConfig($credentials);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'] ?? null;

        if (! is_string($accessToken)) {
            throw new \RuntimeException('Unable to obtain an FCM access token.');
        }

        return $accessToken;
    }
}
