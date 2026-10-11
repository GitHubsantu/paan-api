<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AndroidOrderContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_order_with_options_pickup_token_and_idempotency(): void
    {
        Setting::create(['key' => 'shop_status', 'value' => 'open']);
        $product = Product::factory()->create(['price' => '40.00']);
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, ['customer']);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2, 'options' => ['sweetness' => 'mild', 'supari' => 'kacha']]], 'payment_method' => 'mock', 'payment_channel' => 'upi', 'comment' => 'Less sweet please'];

        $first = $this->withHeader('Idempotency-Key', 'android-order-1')->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.token', 1)
            ->assertJsonPath('data.payment_channel', 'upi')
            ->assertJsonPath('data.items.0.options.sweetness', 'mild')
            ->assertJsonPath('data.total', 80);

        $this->withHeader('Idempotency-Key', 'android-order-1')->postJson('/api/v1/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('orders', 1);
    }
}
