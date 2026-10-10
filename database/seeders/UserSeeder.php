<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // DEV ONLY credentials. Change/remove before production.
        User::factory()->admin()->create([
            'name' => 'Safa Admin',
            'phone' => '9999999999',
            'email' => 'admin@paan.test',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Santu',
            'phone' => '8327567740',
            'password' => 'password',
        ]);
    }
}
