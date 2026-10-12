<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AppReleaseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

#[Signature('app:release {apk-path} {version_name} {version_code} {--notes=} {--publish} {--current}')]
#[Description('Register an Android APK copied to the server by hand.')]
class RegisterAppRelease extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('apk-path');
        if (! is_file($path)) {
            $this->error('APK path does not exist.');

            return self::FAILURE;
        }
        $admin = User::query()->where('role', 'admin')->first();
        if (! $admin) {
            $this->error('Create an administrator first.');

            return self::FAILURE;
        }
        $release = app(AppReleaseService::class)->create(['version_name' => $this->argument('version_name'), 'version_code' => (int) $this->argument('version_code'), 'release_notes' => $this->option('notes'), 'is_published' => $this->option('publish')], new UploadedFile($path, basename($path), null, null, true), $admin);
        if ($this->option('current')) {
            app(AppReleaseService::class)->makeCurrent($release);
        }
        $this->info("Release {$release->version_name} registered.");

        return self::SUCCESS;
    }
}
