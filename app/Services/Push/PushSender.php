<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

interface PushSender
{
    /** @param array<string, string> $payload */
    public function send(DeviceToken $token, array $payload, int $ttl, ?string $collapseKey = null): void;
}
