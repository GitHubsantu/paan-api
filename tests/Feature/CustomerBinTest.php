<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerBinTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_lists_only_deleted_customers_newest_first(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->create(['name' => 'Active Customer']);
        $older = User::factory()->create(['name' => 'Older Deleted', 'phone' => '9111111111']);
        $newer = User::factory()->create(['name' => 'Newer Deleted', 'phone' => '9222222222']);
        $this->createOrder($older, 40);
        $older->delete();
        $newer->delete();
        $older->forceFill(['deleted_at' => now()->subHour()])->saveQuietly();
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/admin/customers/deleted?search=Deleted&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.orders_count', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertDatabaseHas('users', ['id' => $active->id, 'deleted_at' => null]);
    }

    public function test_admin_restores_a_deleted_customer_as_active_without_restoring_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_active' => false, 'blocked_reason' => 'Old reason']);
        $customer->createToken('customer', ['customer']);
        $customer->tokens()->delete();
        $customer->delete();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson("/api/v1/admin/customers/{$customer->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.blocked_reason', null);

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null, 'is_active' => true, 'blocked_reason' => null]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_restore_a_customer_when_another_customer_owns_the_phone(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
        });
        $admin = User::factory()->admin()->create();
        $deletedCustomer = User::factory()->create(['phone' => '9333333333']);
        $deletedCustomer->delete();
        User::factory()->create(['phone' => '9333333333']);
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson("/api/v1/admin/customers/{$deletedCustomer->id}/restore")
            ->assertConflict()
            ->assertJsonPath('code', 'PHONE_TAKEN')
            ->assertJsonPath('message', 'This phone number is now used by another customer.');
    }

    public function test_force_deletion_anonymises_orders_removes_personal_data_and_preserves_dashboard_totals(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Rahul Das', 'phone' => '9555555555']);
        $order = $this->createOrder($customer, 125, OrderStatus::Completed);
        $customer->createToken('customer', ['customer']);
        $customer->deviceTokens()->create(['token' => 'customer-device-token', 'platform' => 'android', 'app' => 'customer']);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\OrderStatusChanged',
            'notifiable_type' => User::class,
            'notifiable_id' => $customer->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer->delete();
        Sanctum::actingAs($admin, ['admin']);

        $before = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->deleteJson("/api/v1/admin/customers/{$customer->id}/force")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id, 'tokenable_type' => User::class]);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $customer->id, 'notifiable_type' => User::class]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null, 'customer_name' => 'Deleted customer', 'customer_phone' => null]);
        $this->assertDatabaseHas('admin_activity_logs', ['admin_id' => $admin->id, 'customer_id' => $customer->id, 'action' => 'customer.force_deleted', 'context' => '[]']);

        $after = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');
        $this->assertSame($before['today_sales'], $after['today_sales']);
        $this->assertSame($before['total_sales'], $after['total_sales']);
        $this->assertSame($before['total_customers'], $after['total_customers']);

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.customer', ['id' => null, 'name' => 'Deleted customer', 'phone' => null]);
    }

    public function test_non_bin_customers_cannot_be_restored_or_permanently_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson("/api/v1/admin/customers/{$customer->id}/restore")->assertNotFound()->assertJsonPath('code', 'NOT_IN_BIN');
        $this->deleteJson("/api/v1/admin/customers/{$customer->id}/force")->assertNotFound()->assertJsonPath('code', 'NOT_IN_BIN');
    }

    public function test_admin_order_endpoints_continue_to_work_for_a_permanently_deleted_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, 75, OrderStatus::Pending);
        $customer->delete();
        Sanctum::actingAs($admin, ['admin']);

        $this->deleteJson("/api/v1/admin/customers/{$customer->id}/force")->assertOk();

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.customer', ['id' => null, 'name' => 'Deleted customer', 'phone' => null]);
        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.customer', ['id' => null, 'name' => 'Deleted customer', 'phone' => null]);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'making'])
            ->assertOk()
            ->assertJsonPath('data.customer', ['id' => null, 'name' => 'Deleted customer', 'phone' => null]);
        $this->postJson("/api/v1/admin/orders/{$order->id}/cancel", ['reason' => 'No longer available'])
            ->assertOk()
            ->assertJsonPath('data.customer', ['id' => null, 'name' => 'Deleted customer', 'phone' => null]);
    }

    public function test_customer_tokens_are_forbidden_from_customer_bin_endpoints(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/admin/customers/deleted')->assertForbidden();
        $this->postJson('/api/v1/admin/customers/999999/restore')->assertForbidden();
        $this->deleteJson('/api/v1/admin/customers/999999/force')->assertForbidden();
    }

    private function createOrder(User $customer, int $total, OrderStatus $status = OrderStatus::Completed): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('#####'),
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => $total,
            'total' => $total,
            'placed_at' => now(),
        ]);
    }
}
