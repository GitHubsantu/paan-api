<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

class MakePaanAdmin extends Command
{
    protected $signature = 'paan:make-admin {phone} {name} {--password=}';

    protected $description = 'Create or update an active PAAN administrator.';

    public function handle(): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        $password = $this->option('password') ?: $this->secret('Password (minimum 10 characters)');

        if (! preg_match(Phone::REGEX, $phone)) {
            $this->error('Phone must be a 10-digit Indian mobile number.');

            return self::FAILURE;
        }

        if (! is_string($password) || mb_strlen($password) < 10) {
            $this->error('Password must contain at least 10 characters.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['phone' => $phone]);
        $user->name = $this->argument('name');
        $user->password = $password;
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->save();

        $this->info("Admin {$phone} is ready.");

        return self::SUCCESS;
    }
}
