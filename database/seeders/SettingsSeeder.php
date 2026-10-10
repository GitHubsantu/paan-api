<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'shop_name' => 'Paan Sutra',
            'shop_address' => '',
            'shop_phone' => '',
            'opening_time' => '10:00',
            'closing_time' => '22:00',
            'pickup_message' => 'Collect your order from the counter when it is ready.',
            'shop_status' => 'open',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
