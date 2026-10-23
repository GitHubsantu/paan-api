<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_transitions_an_order_and_records_status_history(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->createOrder(OrderStatus::Pending);

        $updated = app(OrderStatusService::class)->transition($order, OrderStatus::Making, $admin, 'Started preparing.');

        $this->assertSame(OrderStatus::Making, $updated->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => 'pending',
            'new_status' => 'making',
            'changed_by' => $admin->id,
            'note' => 'Started preparing.',
        ]);
    }

    public function test_rejects_an_invalid_status_transition_with_422(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->createOrder(OrderStatus::Pending);

        try {
            app(OrderStatusService::class)->transition($order, OrderStatus::Completed, $admin);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $this->assertSame(422, $exception->status);
        }
    }

    public function test_cancelling_a_paid_order_marks_its_refund_as_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->createOrder(OrderStatus::Ready);
        $order->payments()->create(['merchant_reference' => 'PAAN-'.$order->id.'-1', 'amount' => '50.00', 'status' => 'success']);

        $cancelled = app(OrderStatusService::class)->cancel($order, $admin, 'Out of stock.');

        $this->assertSame(OrderStatus::AdminCancelled, $cancelled->status);
        $this->assertSame(PaymentStatus::Paid, $cancelled->payment_status);
        $this->assertTrue($cancelled->refund_pending);
    }

    private function createOrder(OrderStatus $status): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'order_number' => 'PAAN-'.fake()->unique()->numerify('######'),
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '50.00',
            'total' => '50.00',
            'placed_at' => now(),
        ]);
    }
}
