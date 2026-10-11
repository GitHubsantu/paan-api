<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_the_authenticated_customers_orders_newest_first(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $olderOrder = $this->createOrder($customer, ['created_at' => now()->subMinute()]);
        $olderOrder->forceFill(['created_at' => now()->subMinute()])->save();
        $newerOrder = $this->createOrder($customer);
        $this->createOrder($otherCustomer);

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerOrder->id)
            ->assertJsonPath('data.1.id', $olderOrder->id)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filters_the_authenticated_customers_orders_by_status(): void
    {
        $customer = User::factory()->create();
        $pendingOrder = $this->createOrder($customer);
        $this->createOrder($customer, ['status' => OrderStatus::Ready]);

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/orders?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingOrder->id);
    }

    public function test_returns_404_when_a_customer_requests_another_customers_order(): void
    {
        $customer = User::factory()->create();
        $otherOrder = $this->createOrder(User::factory()->create());

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson("/api/v1/orders/{$otherOrder->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_returns_order_detail_from_snapshots_when_the_product_is_deleted(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $order = $this->createOrder($customer, ['product_id' => $product->id]);
        $product->delete();

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Snapshot Paan')
            ->assertJsonPath('data.items.0.addons.0.name', 'Snapshot Add-on');
    }

    public function test_returns_pickup_message_and_status_history_for_a_ready_order(): void
    {
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, ['status' => OrderStatus::Ready]);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'old_status' => OrderStatus::Pending,
            'new_status' => OrderStatus::Ready,
            'changed_by' => $customer->id,
            'note' => null,
        ]);
        Setting::create(['key' => 'pickup_message', 'value' => 'Collect from the counter.']);

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson("/api/v1/orders/{$order->id}/status")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.pickup_message', 'Collect from the counter.')
            ->assertJsonPath('data.status_history.0.status', 'ready');
    }

    private function createOrder(User $customer, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('######'),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '50.00',
            'total' => '50.00',
            'placed_at' => now(),
        ], $attributes));

        $item = $order->items()->create([
            'product_id' => $attributes['product_id'] ?? null,
            'product_name_snapshot' => 'Snapshot Paan',
            'unit_price' => '50.00',
            'quantity' => 1,
            'line_total' => '50.00',
        ]);

        $item->addons()->create([
            'addon_id' => null,
            'addon_name_snapshot' => 'Snapshot Add-on',
            'unit_price' => '5.00',
        ]);

        return $order;
    }
}
