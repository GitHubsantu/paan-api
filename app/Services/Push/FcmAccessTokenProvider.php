<?php

namespace App\Services\Push;

interface FcmAccessTokenProvider
{
    public function accessToken(string $credentials): string;
}
