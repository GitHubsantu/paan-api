<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendFcmPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /** @param array<string, string> $data */
    public function __construct(
        public int $deviceTokenId,
        public array $data,
        public int $ttl,
        public ?string $collapseKey = null,
    ) {}

    public function handle(PushSender $pushSender): void
    {
        $deviceToken = DeviceToken::find($this->deviceTokenId);
        if (! $deviceToken) {
            return;
        }

        $pushSender->send($deviceToken, $this->data, $this->ttl, $this->collapseKey);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('fcm.push_failed', ['device_token_id' => $this->deviceTokenId, 'exception' => $exception::class]);
    }
}
