<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrPickupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ready_transition_creates_a_random_pickup_code_and_other_statuses_do_not_have_one(): void
    {
        $admin = User::factory()->admin()->create();
        $making = $this->order(User::factory()->create(), OrderStatus::Making);
        $ready = app(OrderStatusService::class)->transition($making, OrderStatus::Ready, $admin);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $ready->pickup_code);
        foreach ([OrderStatus::Pending, OrderStatus::Making, OrderStatus::Completed, OrderStatus::AdminCancelled] as $status) {
            $this->assertNull($this->order(User::factory()->create(), $status)->pickup_code);
        }
    }

    public function test_admin_detail_exposes_the_exact_ready_qr_payload_and_lazily_generates_legacy_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $ready = $this->order(User::factory()->create(), OrderStatus::Ready, ['pickup_code' => str_repeat('a', 48)]);
        $legacy = $this->order(User::factory()->create(), OrderStatus::Ready);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/orders/{$ready->id}")
            ->assertOk()
            ->assertJsonPath('data.pickup_qr.payload', "PAANPICKUP1|{$ready->order_number}|{$ready->pickup_code}")
            ->assertJsonPath('data.completed_via', null);
        $this->getJson("/api/v1/admin/orders/{$legacy->id}")->assertOk();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $legacy->fresh()->pickup_code);
        $this->getJson('/api/v1/admin/orders')->assertOk()->assertJsonMissing(['pickup_qr' => ['payload' => true]]);
    }

    public function test_customer_qr_pickup_completes_the_order_without_exposing_the_code(): void
    {
        $customer = User::factory()->create();
        $ready = $this->readyOrder($customer);
        Sanctum::actingAs($customer, ['customer']);

        $response = $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => $ready->pickup_code])
            ->assertOk()
            ->assertJsonPath('message', 'Order picked up.')
            ->assertJsonPath('data.status', 'completed');

        $this->assertStringNotContainsString($ready->pickup_code, $response->getContent());
        $this->assertDatabaseHas('orders', ['id' => $ready->id, 'status' => 'completed', 'completed_via' => 'qr', 'pickup_code' => null]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $ready->id, 'new_status' => 'completed', 'changed_by' => $customer->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'order_id' => $ready->id, 'status' => 'completed']);
        $this->assertDatabaseHas('admin_activity_logs', ['admin_id' => $customer->id, 'customer_id' => $customer->id, 'action' => 'order.pickup_qr']);
    }

    public function test_pickup_rejects_another_customer_wrong_or_empty_code_and_orders_that_are_not_ready_or_complete(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $ready = $this->readyOrder($customer);
        Sanctum::actingAs($other, ['customer']);
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => $ready->pickup_code])->assertNotFound()->assertJsonPath('message', 'Order not found.');

        Sanctum::actingAs($customer, ['customer']);
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => 'wrong'])->assertUnprocessable()->assertJsonPath('code', 'PICKUP_CODE_INVALID');
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => ''])->assertUnprocessable()->assertJsonValidationErrors('code');
        $pending = $this->order($customer, OrderStatus::Pending);
        $this->postJson("/api/v1/orders/{$pending->id}/pickup", ['code' => 'wrong'])->assertConflict()->assertJsonPath('code', 'ORDER_NOT_READY');
        $completed = $this->order($customer, OrderStatus::Completed);
        $this->postJson("/api/v1/orders/{$completed->id}/pickup", ['code' => 'wrong'])->assertConflict()->assertJsonPath('code', 'ALREADY_PICKED_UP');
    }

    public function test_codes_are_single_use_and_wrong_attempts_are_rate_limited_then_cleared_by_success(): void
    {
        $customer = User::factory()->create();
        $ready = $this->readyOrder($customer);
        Sanctum::actingAs($customer, ['customer']);
        $key = "pickup:{$customer->id}:{$ready->id}";
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => 'wrong'])->assertUnprocessable();
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => $ready->pickup_code])->assertOk();
        $this->assertSame(0, RateLimiter::attempts($key));
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => $ready->pickup_code])->assertConflict()->assertJsonPath('code', 'ALREADY_PICKED_UP');

        $locked = $this->readyOrder($customer);
        foreach (range(1, 5) as $attempt) {
            $this->postJson("/api/v1/orders/{$locked->id}/pickup", ['code' => 'wrong'])->assertUnprocessable()->assertJsonPath('code', 'PICKUP_CODE_INVALID');
        }
        $this->postJson("/api/v1/orders/{$locked->id}/pickup", ['code' => 'wrong'])->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

        $cancelled = $this->readyOrder($customer);
        app(OrderStatusService::class)->cancel($cancelled, User::factory()->admin()->create(), 'Counter closed.');
        $this->postJson("/api/v1/orders/{$cancelled->id}/pickup", ['code' => 'anything'])->assertConflict()->assertJsonPath('code', 'ORDER_NOT_READY');
        $this->assertDatabaseHas('orders', ['id' => $cancelled->id, 'pickup_code' => null]);
    }

    public function test_manual_admin_completion_still_works_and_records_admin_as_the_completion_method(): void
    {
        $admin = User::factory()->admin()->create();
        $ready = $this->readyOrder(User::factory()->create());
        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/orders/{$ready->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $ready->id, 'completed_via' => 'admin', 'pickup_code' => null]);
        Sanctum::actingAs($admin, ['admin']);
        $this->postJson("/api/v1/orders/{$ready->id}/pickup", ['code' => 'anything'])->assertForbidden();
    }

    private function readyOrder(User $customer): Order
    {
        $making = $this->order($customer, OrderStatus::Making);

        return app(OrderStatusService::class)->transition($making, OrderStatus::Ready, User::factory()->admin()->create());
    }

    private function order(User $customer, OrderStatus $status, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('######'),
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '50.00',
            'total' => '50.00',
            'placed_at' => now(),
        ], $attributes));
    }
}
