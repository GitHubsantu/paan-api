<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendFcmPush;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminNotifications\FcmAdminOrderNotifier;
use App\Services\NotificationService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationDispatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_orders_queue_data_only_pushes_for_active_admin_tokens(): void
    {
        $order = $this->createOrder(User::factory()->create());
        $activeAdminToken = DeviceToken::create(['user_id' => User::factory()->admin()->create()->id, 'token' => 'admin-token', 'platform' => 'android', 'app' => 'admin']);
        DeviceToken::create(['user_id' => User::factory()->admin()->inactive()->create()->id, 'token' => 'inactive-admin-token', 'platform' => 'android', 'app' => 'admin']);
        DeviceToken::create(['user_id' => User::factory()->create()->id, 'token' => 'customer-token', 'platform' => 'android', 'app' => 'customer']);
        Queue::fake([SendFcmPush::class]);

        app(FcmAdminOrderNotifier::class)->notifyNewOrder($order);

        Queue::assertPushed(SendFcmPush::class, fn (SendFcmPush $job): bool => $job->deviceTokenId === $activeAdminToken->id
            && $job->ttl === 600
            && $job->collapseKey === null
            && $job->data['type'] === 'new_order'
            && array_filter($job->data, fn (mixed $value): bool => ! is_string($value)) === []);
        Queue::assertPushedTimes(SendFcmPush::class, 1);
    }

    public function test_order_status_pushes_only_to_the_orders_customer_and_a_no_op_queues_nothing(): void
    {
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, OrderStatus::Making);
        $customerToken = DeviceToken::create(['user_id' => $customer->id, 'token' => 'customer-token', 'platform' => 'android', 'app' => 'customer']);
        DeviceToken::create(['user_id' => User::factory()->create()->id, 'token' => 'other-customer-token', 'platform' => 'android', 'app' => 'customer']);
        Queue::fake([SendFcmPush::class]);

        app(NotificationService::class)->notifyOrderStatusChanged($order);

        Queue::assertPushed(SendFcmPush::class, fn (SendFcmPush $job): bool => $job->deviceTokenId === $customerToken->id
            && $job->ttl === 3600
            && $job->collapseKey === "order_{$order->id}"
            && $job->data['type'] === 'order_status'
            && array_filter($job->data, fn (mixed $value): bool => ! is_string($value)) === []);
        Queue::fake([SendFcmPush::class]);

        app(OrderStatusService::class)->transition($order, OrderStatus::Making, User::factory()->admin()->create());

        Queue::assertNothingPushed();
    }

    private function createOrder(User $customer, OrderStatus $status = OrderStatus::Pending): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-10012',
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '160.00',
            'total' => '160.00',
            'token' => 47,
            'placed_at' => now(),
        ]);
        $order->items()->create(['product_name_snapshot' => 'Shahi Meetha Paan', 'unit_price' => '80.00', 'quantity' => 2, 'line_total' => '160.00']);

        return $order;
    }
}
