<?php

namespace App\Services;

use App\Enums\ShopStatus;
use App\Models\Setting;

class ShopSettingsService
{
    public const KEYS = [
        'shop_name', 'shop_address', 'shop_phone', 'opening_time',
        'closing_time', 'pickup_message', 'shop_status',
    ];

    /** @return array<string, ?string> */
    public function all(): array
    {
        $stored = Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();

        return collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => $stored[$k] ?? null])->all();
    }

    public function status(): ShopStatus
    {
        $value = Setting::where('key', 'shop_status')->value('value');

        return ShopStatus::tryFrom((string) $value) ?? ShopStatus::Closed;
    }

    public function isOpen(): bool
    {
        return $this->status() === ShopStatus::Open;
    }

    /** @param array<string, string|null> $settings */
    public function update(array $settings): array
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->all();
    }

    /** @return array<string, mixed> */
    public function publicPayload(): array
    {
        $s = $this->all();
        $open = ($s['shop_status'] ?? null) === ShopStatus::Open->value;

        return [
            'shop_name' => $s['shop_name'],
            'shop_address' => $s['shop_address'],
            'shop_phone' => $s['shop_phone'],
            'opening_time' => $s['opening_time'],
            'closing_time' => $s['closing_time'],
            'pickup_message' => $s['pickup_message'],
            'is_open' => $open,
            'status' => $open ? 'open' : 'closed',
            'status_message' => $open ? null : 'Shop is currently closed.',
        ];
    }
}
