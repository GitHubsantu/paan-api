<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPhaseBEndpointsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_has_every_status_and_excludes_cancelled_sales(): void
    {
        $this->travelTo('2026-10-11 12:00:00');
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->createOrder($customer, OrderStatus::Pending, PaymentStatus::Paid, 100);
        $this->createOrder($customer, OrderStatus::Completed, PaymentStatus::Paid, 50);
        $this->createOrder($customer, OrderStatus::AdminCancelled, PaymentStatus::Paid, 70);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today_orders', 3)
            ->assertJsonPath('data.status_counts.pending', 1)
            ->assertJsonPath('data.status_counts.making', 0)
            ->assertJsonPath('data.status_counts.ready', 0)
            ->assertJsonPath('data.status_counts.completed', 1)
            ->assertJsonPath('data.status_counts.admin_cancelled', 1)
            ->assertJsonPath('data.today_sales', 150)
            ->assertJsonPath('data.total_sales', 150)
            ->assertJsonPath('data.total_customers', 1);
    }

    public function test_products_are_paginated_searchable_and_include_unavailable_items(): void
    {
        $admin = User::factory()->admin()->create();
        $unavailable = Product::factory()->unavailable()->create(['name' => 'Fire Paan', 'price' => 90, 'tag' => null]);
        Product::factory()->create(['name' => 'Shahi Meetha Paan']);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/admin/products?search=Fire&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $unavailable->id)
            ->assertJsonPath('data.0.price', 90)
            ->assertJsonPath('data.0.is_available', false)
            ->assertJsonPath('data.0.tag', null)
            ->assertJsonPath('data.0.category.id', $unavailable->category_id)
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'category' => ['id', 'name'], 'image_url', 'thumbnail_url', 'is_available', 'is_featured', 'tag']], 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_product_availability_update_is_idempotent_and_missing_product_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->unavailable()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/products/{$product->id}/availability", ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_available', true);

        $this->patchJson("/api/v1/admin/products/{$product->id}/availability", ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_available' => true]);
        $this->patchJson('/api/v1/admin/products/999999/availability', ['is_available' => true])->assertNotFound();
    }

    public function test_settings_are_normalized_saved_and_closed_shop_rejects_customer_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = [
            'shop_name' => 'Paan Sutra', 'shop_address' => 'Main Road', 'shop_phone' => '9876543210',
            'opening_time' => '09:00', 'closing_time' => '22:00', 'pickup_message' => 'Collect at the counter.', 'shop_status' => 'open',
        ];
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/v1/admin/settings', $settings)->assertOk()->assertJsonPath('data.opening_time', '09:00')->assertJsonPath('data.closing_time', '22:00')->assertJsonPath('data.shop_status', 'open');
        $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('data.shop_phone', '9876543210');
        $this->putJson('/api/v1/admin/settings', [...$settings, 'shop_phone' => '5123456789'])->assertUnprocessable()->assertJsonValidationErrors('shop_phone');

        $this->putJson('/api/v1/admin/settings', [...$settings, 'shop_status' => 'closed'])->assertOk();
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($customer, ['customer']);

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'mock'])
            ->assertConflict()
            ->assertJsonPath('code', 'SHOP_CLOSED');
    }

    public function test_customers_are_newest_first_searchable_and_paginated_with_order_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $older = User::factory()->create(['name' => 'Rahul Das', 'phone' => '9876543210', 'created_at' => now()->subDay()]);
        $newer = User::factory()->create(['name' => 'Rahul Sharma', 'phone' => '9876543211']);
        $this->createOrder($older, OrderStatus::Pending, PaymentStatus::Pending, 40);
        $this->createOrder($older, OrderStatus::Making, PaymentStatus::Paid, 40);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/admin/customers?search=9876543210&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.0.name', 'Rahul Das')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/admin/customers?per_page=50')->assertOk()->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_customer_tokens_are_forbidden_from_every_phase_b_admin_endpoint(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/products')->assertForbidden();
        $this->patchJson("/api/v1/admin/products/{$product->id}/availability", ['is_available' => true])->assertForbidden();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings', [])->assertForbidden();
        $this->getJson('/api/v1/admin/customers')->assertForbidden();
    }

    private function createOrder(User $customer, OrderStatus $status, PaymentStatus $paymentStatus, int $total): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('#####'),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal' => $total,
            'total' => $total,
            'placed_at' => now(),
        ]);
    }
}
