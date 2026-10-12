<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrudManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_manages_categories_and_products_using_multipart_post_updates(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['admin']);

        $category = $this->post('/api/v1/admin/categories', [
            'name' => 'Premium Paans',
            'sort_order' => 3,
            'is_active' => '0',
            'image' => UploadedFile::fake()->image('paan.jpg', 640, 480),
        ])->assertCreated()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.products_count', 0);
        $this->assertNotNull($category->json('data.image_url'));
        $this->assertNotNull($category->json('data.thumbnail_url'));
        $categoryId = $category->json('data.id');
        $product = $this->post('/api/v1/admin/products', ['category_id' => $categoryId, 'name' => 'Royal Gilouri', 'price' => '40.00', 'ingredients_json' => '["Gulkand","Saunf"]', 'sort_order' => 2])
            ->assertCreated()->assertJsonPath('data.price', 40)->assertJsonPath('data.ingredients.0', 'Gulkand')->assertJsonPath('data.sort_order', 2);
        $productId = $product->json('data.id');

        $this->post("/api/v1/admin/products/{$productId}", ['_method' => 'PUT', 'ingredients_json' => '[]', 'remove_image' => '1'])
            ->assertOk()->assertJsonPath('data.ingredients', [])->assertJsonPath('data.image_url', null);
        $this->getJson("/api/v1/admin/products/{$productId}")->assertOk()->assertJsonPath('data.category.id', $categoryId);
        $this->deleteJson("/api/v1/admin/categories/{$categoryId}")->assertConflict()->assertJsonPath('code', 'CATEGORY_HAS_PRODUCTS');
        $this->deleteJson("/api/v1/admin/products/{$productId}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $productId]);
        $this->assertDatabaseHas('admin_activity_logs', ['admin_id' => $admin->id, 'action' => 'product.deleted']);
    }

    public function test_admin_customer_crud_blocking_and_deletion_rules(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['admin']);

        $created = $this->postJson('/api/v1/admin/customers', ['name' => 'Rahul Das', 'phone' => '9876543210', 'email' => 'rahul@example.test'])
            ->assertCreated()->assertJsonPath('data.email', 'rahul@example.test');
        $customer = User::findOrFail($created->json('data.id'));
        $this->createOrder($customer, OrderStatus::Completed, 100);
        $this->createOrder($customer, OrderStatus::AdminCancelled, 50);
        $customer->createToken('device', ['customer']);
        $customer->deviceTokens()->create(['token' => 'device-token', 'platform' => 'android', 'app' => 'customer']);

        $this->getJson("/api/v1/admin/customers/{$customer->id}")->assertOk()->assertJsonPath('data.orders_count', 2)->assertJsonPath('data.total_spent', 100);
        $this->patchJson("/api/v1/admin/customers/{$customer->id}/block", ['is_active' => false, 'reason' => 'Repeated abuse'])
            ->assertOk()->assertJsonPath('data.blocked_reason', 'Repeated abuse');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $customer->phone])->assertForbidden()->assertJsonPath('code', 'CUSTOMER_BLOCKED');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => $customer->phone, 'otp' => '123456'])->assertForbidden()->assertJsonPath('code', 'CUSTOMER_BLOCKED');

        $customer->forceFill(['is_active' => true, 'blocked_reason' => null])->save();
        $this->deleteJson("/api/v1/admin/customers/{$customer->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $customer->id]);
        $this->assertDatabaseCount('device_tokens', 0);
        $this->postJson('/api/v1/admin/customers', ['name' => 'Replacement', 'phone' => $customer->phone])->assertConflict()->assertJsonPath('code', 'CUSTOMER_DELETED');
    }

    public function test_customer_with_active_orders_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->createOrder($customer, OrderStatus::Pending, 40);
        Sanctum::actingAs($admin, ['admin']);

        $this->deleteJson("/api/v1/admin/customers/{$customer->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'CUSTOMER_HAS_ACTIVE_ORDERS');
    }

    private function createOrder(User $customer, OrderStatus $status, int $total): Order
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
