<?php

namespace App\Services;

use App\Enums\DownloadMode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Media;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LandingPageService
{
    public function __construct(private ShopSettingsService $settings, private AppReleaseService $releases) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        if (! Schema::hasTable('settings')) {
            return ['shop' => ['shop_name' => config('app.name', 'Paan Sutra'), 'shop_address' => null, 'shop_phone' => null, 'opening_time' => null, 'closing_time' => null, 'pickup_message' => null, 'is_open' => false], 'categories' => collect(), 'products' => collect(), 'mode' => DownloadMode::ComingSoon, 'play_store_url' => null, 'release' => null];
        }

        return Cache::remember('landing.v1', now()->addSeconds(config('landing.cache_ttl')), function (): array {
            $current = $this->releases->current();

            return ['shop' => $this->settings->publicPayload(), 'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']), 'products' => Product::query()->with('category:id,name')->where('is_available', true)->where('is_featured', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('sort_order')->limit(6)->get()->map(fn (Product $product) => ['name' => $product->name, 'description' => $product->description, 'price' => $product->price, 'image' => Media::url($product->image), 'category' => $product->category?->name]), 'mode' => DownloadMode::tryFrom(Setting::where('key', 'download_mode')->value('value') ?: 'coming_soon') ?? DownloadMode::ComingSoon, 'play_store_url' => Setting::where('key', 'play_store_url')->value('value'), 'release' => $current];
        });
    }
}
