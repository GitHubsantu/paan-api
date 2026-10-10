<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_and_marks_only_the_authenticated_customers_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $customer->notify(new OrderStatusChanged($this->createOrder($customer)));
        $otherCustomer->notify(new OrderStatusChanged($this->createOrder($otherCustomer)));
        $notification = $customer->notifications()->firstOrFail();

        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $notification->id);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function createOrder(User $customer): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('######'),
            'status' => OrderStatus::Making,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '50.00',
            'total' => '50.00',
            'placed_at' => now(),
        ]);
    }
}
