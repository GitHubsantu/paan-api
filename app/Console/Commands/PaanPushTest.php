<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Push\PushSender;
use Illuminate\Console\Command;

class PaanPushTest extends Command
{
    protected $signature = 'paan:push-test {phone} {role : customer or admin}';

    protected $description = 'Send a data-only FCM test push to a user.';

    /**
     * Execute the console command.
     */
    public function handle(PushSender $pushSender): int
    {
        $role = $this->argument('role');
        if (! in_array($role, ['customer', 'admin'], true)) {
            $this->error('Role must be customer or admin.');

            return self::INVALID;
        }

        $user = User::query()->where('phone', $this->argument('phone'))->where('role', $role)->where('is_active', true)->first();
        if (! $user) {
            $this->error('Active user not found.');

            return self::FAILURE;
        }

        $tokens = $user->deviceTokens()->where('app', $role)->get();
        $used = 0;
        $removed = 0;
        foreach ($tokens as $deviceToken) {
            $used++;
            $pushSender->send($deviceToken, [
                'type' => 'test',
                'title' => 'PAAN test push',
                'body' => 'Push notifications are configured.',
            ], 600);
            if (! $deviceToken->exists) {
                $removed++;
            }
        }

        $this->info("Used {$used} token(s); removed {$removed} invalid token(s).");

        return self::SUCCESS;
    }
}
