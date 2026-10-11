<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_login_returns_an_admin_only_token_and_compact_user(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Shop Admin',
            'phone' => '9876543210',
            'password' => 'secure-password',
        ]);

        $this->postJson('/api/v1/admin/login', ['phone' => $admin->phone, 'password' => 'secure-password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.user.name', 'Shop Admin')
            ->assertJsonPath('data.user.phone', '9876543210')
            ->assertJsonMissingPath('data.user.email');
    }

    public function test_admin_login_uses_a_non_revealing_validation_error_for_bad_credentials(): void
    {
        User::factory()->admin()->create(['phone' => '9876543210', 'password' => 'secure-password']);

        $this->postJson('/api/v1/admin/login', ['phone' => '9876543210', 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Incorrect phone number or password.');
    }

    public function test_admin_order_response_is_a_direct_staff_shape_and_a_repeat_status_is_safe(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => null, 'phone' => '9876543210']);
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-10001',
            'token' => 1,
            'token_date' => today(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'mock',
            'payment_channel' => 'upi',
            'subtotal' => 40,
            'total' => 40,
            'placed_at' => now(),
        ]);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.name', null)
            ->assertJsonPath('data.payment_channel', 'upi');

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_admin_can_toggle_product_availability_and_register_an_admin_device(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->unavailable()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/products/{$product->id}/availability", ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category_id', $product->category_id)
            ->assertJsonPath('data.is_available', true);

        $this->postJson('/api/v1/device-tokens', ['token' => 'admin-device-token', 'platform' => 'android', 'app' => 'admin'])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $admin->id, 'app' => 'admin']);
    }

    public function test_make_admin_command_creates_an_active_admin_with_a_hashed_password(): void
    {
        Artisan::call('paan:make-admin 9876543210 "Shop Admin" --password=secure-password');

        $this->assertDatabaseHas('users', [
            'phone' => '9876543210',
            'name' => 'Shop Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->assertTrue(password_verify('secure-password', User::where('phone', '9876543210')->value('password')));
    }
}
