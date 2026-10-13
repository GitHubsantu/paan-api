<?php

namespace App\Console\Commands;

use App\Models\CustomerNotification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=90 : Delete notifications older than this many days}';

    protected $description = 'Delete expired customer notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('The days option must be at least 1.');

            return self::INVALID;
        }

        $deleted = 0;
        CustomerNotification::query()->where('created_at', '<', now()->subDays($days))->chunkById(1000, function ($notifications) use (&$deleted): void {
            $deleted += CustomerNotification::query()->whereKey($notifications->pluck('id'))->delete();
        });

        $this->info("Pruned {$deleted} notification(s).");

        return self::SUCCESS;
    }
}
