<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendFcmPush;
use App\Models\CustomerNotification;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerNotificationCentreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_paginated_notifications_newest_first_with_an_unread_count_across_pages(): void
    {
        $customer = User::factory()->create();
        $older = $this->notification($customer, 'Older');
        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $this->notification($customer, 'Read')->update(['read_at' => now()]);
        $newer = $this->notification($customer, 'Newer');
        $newer->forceFill(['created_at' => now()->addMinute()])->save();
        $this->notification(User::factory()->create(), 'Other');
        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/notifications?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonPath('meta.per_page', 2);

        $this->getJson('/api/v1/notifications?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_unread_count_read_read_all_and_delete_are_scoped_and_idempotent(): void
    {
        $customer = User::factory()->create();
        $first = $this->notification($customer, 'First');
        $this->notification($customer, 'Second');
        $other = $this->notification(User::factory()->create(), 'Other');
        Sanctum::actingAs($customer, ['customer']);

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);
        $this->postJson("/api/v1/notifications/{$first->id}/read")->assertOk()->assertJsonPath('data.unread_count', 1);
        $readAt = $first->fresh()->read_at->toIso8601String();
        $this->postJson("/api/v1/notifications/{$first->id}/read")->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->postJson("/api/v1/notifications/{$other->id}/read")->assertNotFound()->assertJsonPath('message', 'Notification not found.');
        $this->postJson('/api/v1/notifications/8b7ae83e-7281-4e7e-a80a-a789627f0a1d/read')->assertNotFound()->assertJsonPath('message', 'Notification not found.');
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.unread_count', 0);
        $this->deleteJson("/api/v1/notifications/{$first->id}")->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->deleteJson("/api/v1/notifications/{$other->id}")->assertNotFound()->assertJsonPath('message', 'Notification not found.');
    }

    public function test_rejects_admin_and_unauthenticated_tokens(): void
    {
        foreach ([
            ['GET', '/api/v1/notifications'],
            ['GET', '/api/v1/notifications/unread-count'],
            ['POST', '/api/v1/notifications/8b7ae83e-7281-4e7e-a80a-a789627f0a1d/read'],
            ['POST', '/api/v1/notifications/read-all'],
            ['DELETE', '/api/v1/notifications/8b7ae83e-7281-4e7e-a80a-a789627f0a1d'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        foreach ([
            ['GET', '/api/v1/notifications'],
            ['GET', '/api/v1/notifications/unread-count'],
            ['POST', '/api/v1/notifications/8b7ae83e-7281-4e7e-a80a-a789627f0a1d/read'],
            ['POST', '/api/v1/notifications/read-all'],
            ['DELETE', '/api/v1/notifications/8b7ae83e-7281-4e7e-a80a-a789627f0a1d'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden();
        }
    }

    public function test_each_order_status_creates_one_safe_database_notification_and_includes_its_id_in_fcm_data(): void
    {
        $customer = User::factory()->create(['phone' => '9876543210']);
        $order = $this->order($customer);
        DeviceToken::create(['user_id' => $customer->id, 'token' => 'customer-token', 'platform' => 'android', 'app' => 'customer']);
        Queue::fake([SendFcmPush::class]);

        foreach ([OrderStatus::Making, OrderStatus::Ready, OrderStatus::Completed, OrderStatus::AdminCancelled] as $status) {
            $order->update(['status' => $status]);
            app(NotificationService::class)->notifyOrderStatusChanged($order->fresh());
        }

        $notifications = CustomerNotification::query()->where('user_id', $customer->id)->get();
        $this->assertCount(4, $notifications);
        $this->assertSame(['admin_cancelled', 'completed', 'making', 'ready'], $notifications->pluck('status')->sort()->values()->all());
        $notifications->each(function (CustomerNotification $notification) use ($customer): void {
            $this->assertStringNotContainsString($customer->phone, $notification->title);
            $this->assertStringNotContainsString($customer->phone, $notification->body);
        });
        Queue::assertPushed(SendFcmPush::class, fn (SendFcmPush $job): bool => isset($job->data['notification_id']) && is_string($job->data['notification_id']));
        Queue::assertPushedTimes(SendFcmPush::class, 4);
    }

    public function test_a_customer_without_a_device_token_still_gets_a_notification_row(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, OrderStatus::Making);
        Queue::fake([SendFcmPush::class]);

        app(NotificationService::class)->notifyOrderStatusChanged($order);

        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'order_id' => $order->id, 'status' => 'making']);
        Queue::assertNothingPushed();
    }

    public function test_pruning_keeps_notifications_newer_than_ninety_days(): void
    {
        $customer = User::factory()->create();
        $old = $this->notification($customer, 'Old');
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        $new = $this->notification($customer, 'New');

        $this->artisan('notifications:prune --days=90')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($new);
    }

    private function notification(User $customer, string $title): CustomerNotification
    {
        return CustomerNotification::create([
            'user_id' => $customer->id,
            'type' => 'order_status',
            'title' => $title,
            'body' => 'Order PAAN-10012 update.',
            'status' => 'making',
            'notifiable_type' => User::class,
            'notifiable_id' => $customer->id,
            'data' => [],
        ]);
    }

    private function order(User $customer, OrderStatus $status = OrderStatus::Pending): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'PAAN-10012',
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '160.00',
            'total' => '160.00',
            'token' => 47,
            'placed_at' => now(),
        ]);
    }
}
