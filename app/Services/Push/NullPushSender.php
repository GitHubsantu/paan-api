<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

class NullPushSender implements PushSender
{
    public function send(DeviceToken $token, array $payload): void {}
}
