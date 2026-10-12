<?php

namespace App\Console\Commands;

use App\Enums\DownloadMode;
use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:download-mode {mode} {--url=}')]
#[Description('Set APK, Play Store, or coming soon download mode.')]
class SetDownloadMode extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = DownloadMode::tryFrom((string) $this->argument('mode'));
        $url = $this->option('url');
        if (! $mode || ($mode === DownloadMode::PlayStore && (! is_string($url) || ! str_starts_with($url, 'https://')))) {
            $this->error('Use apk, play_store (--url=https://...), or coming_soon.');

            return self::FAILURE;
        }
        Setting::updateOrCreate(['key' => 'download_mode'], ['value' => $mode->value]);
        Setting::updateOrCreate(['key' => 'play_store_url'], ['value' => $mode === DownloadMode::PlayStore ? $url : null]);
        $this->info("Download mode set to {$mode->value}.");

        return self::SUCCESS;
    }
}
