<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateway\GatewayClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gateway.base_url', 'https://gateway.test');
        config()->set('services.gateway.api_key', 'key');
        config()->set('services.gateway.api_secret', 'secret');
        config()->set('services.gateway.domain', 'paan-api.piratemods.com');
        config()->set('services.gateway.return_url', 'https://paan-api.piratemods.com/payment/return');
    }

    public function test_gateway_signing_uses_sorted_json_for_post_and_get(): void
    {
        $gateway = app(GatewayClient::class);
        $post = $gateway->buildHeaders('POST', 'api/gateway/v1/transactions', ['return_url' => 'https://a.test/a/b', 'amount' => '45.00'], '1700000000', 'abc');
        $get = $gateway->buildHeaders('GET', '/api/gateway/v1/transactions/t_1', ['domain' => 'paan-api.piratemods.com'], '1700000000', 'abc');

        $this->assertSame(hash_hmac('sha256', "POST\n/api/gateway/v1/transactions\n1700000000\nabc\n{\"amount\":\"45.00\",\"return_url\":\"https://a.test/a/b\"}", 'secret'), $post['X-Signature']);
        $this->assertSame(hash_hmac('sha256', "GET\n/api/gateway/v1/transactions/t_1\n1700000000\nabc\n{\"domain\":\"paan-api.piratemods.com\"}", 'secret'), $get['X-Signature']);
    }

    public function test_customer_starts_checkout_using_database_amount(): void
    {
        $customer = User::factory()->create(['email' => null]);
        $order = $this->orderFor($customer);
        Sanctum::actingAs($customer, ['customer']);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response(['data' => ['transaction_id' => 'txn_1', 'checkout_url' => 'https://checkout.test/1']], 201));

        $this->postJson("/api/v1/orders/{$order->id}/pay", ['amount' => '0.01'])->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.test/1');
        Http::assertSent(fn (Request $request) => $request->data()['amount'] === '45.00');
    }

    public function test_raw_signed_webhook_settles_once(): void
    {
        $order = $this->orderFor(User::factory()->create());
        $payment = Payment::create(['order_id' => $order->id, 'transaction_id' => 'txn_1', 'merchant_reference' => 'PAAN-1-1', 'amount' => '45.00', 'status' => 'pending']);
        $raw = '{"transaction_id":"txn_1"}';
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'success']], 200)]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, 'secret')];

        $this->call('POST', '/api/v1/webhooks/gateway', [], [], [], $headers, $raw)->assertOk();
        $this->call('POST', '/api/v1/webhooks/gateway', [], [], [], $headers, $raw)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame('success', $payment->fresh()->status);
    }

    public function test_public_webhook_route_rejects_invalid_and_malformed_requests_without_authentication(): void
    {
        $this->postJson('/api/v1/webhooks/gateway', [])->assertUnauthorized();
        $raw = '{}';
        $this->call('POST', '/api/v1/webhooks/gateway', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, 'secret')], $raw)->assertBadRequest();
        $this->get('/payment/return')->assertOk()->assertSee('Chutur Paan');
    }

    public function test_status_reconciles_a_recent_pending_payment_once_per_five_seconds(): void
    {
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        Payment::create(['order_id' => $order->id, 'transaction_id' => 'txn_poll', 'merchant_reference' => 'PAAN-'.$order->id.'-1', 'amount' => '45.00', 'status' => 'pending']);
        Sanctum::actingAs($customer, ['customer']);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'success']], 200)]);

        $this->getJson("/api/v1/orders/{$order->id}/status")->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->getJson("/api/v1/orders/{$order->id}/status")->assertOk()->assertJsonPath('data.payment_status', 'paid');
        Http::assertSentCount(1);
    }

    public function test_rapid_pending_status_polls_are_rate_limited_to_one_gateway_lookup(): void
    {
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        Payment::create(['order_id' => $order->id, 'transaction_id' => 'txn_pending', 'merchant_reference' => 'PAAN-'.$order->id.'-1', 'amount' => '45.00', 'status' => 'pending']);
        Sanctum::actingAs($customer, ['customer']);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'processing']], 200)]);

        $this->getJson("/api/v1/orders/{$order->id}/status")->assertOk()->assertJsonPath('data.payment_status', 'pending');
        $this->getJson("/api/v1/orders/{$order->id}/status")->assertOk()->assertJsonPath('data.payment_status', 'pending');
        Http::assertSentCount(1);
    }

    public function test_another_customer_cannot_read_or_pay_an_order(): void
    {
        $order = $this->orderFor(User::factory()->create());
        Sanctum::actingAs(User::factory()->create(), ['customer']);

        $this->postJson("/api/v1/orders/{$order->id}/pay")->assertForbidden();
    }

    public function test_failed_gateway_order_is_retained_but_cannot_be_paid_again(): void
    {
        $customer = User::factory()->create();
        $order = $this->orderFor($customer);
        $order->update(['payment_status' => PaymentStatus::Failed]);
        Sanctum::actingAs($customer, ['customer']);

        $this->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PAYMENT_FAILED');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'failed']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_verified_failed_webhook_makes_the_order_terminal(): void
    {
        $order = $this->orderFor(User::factory()->create());
        Payment::create(['order_id' => $order->id, 'transaction_id' => 'txn_failed', 'merchant_reference' => 'PAAN-'.$order->id.'-1', 'amount' => '45.00', 'status' => 'pending']);
        $raw = '{"transaction_id":"txn_failed"}';
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'failed']], 200)]);

        $this->call('POST', '/api/v1/webhooks/gateway', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, 'secret')], $raw)->assertOk();
        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'new_status' => 'payment_failed']);
    }

    public function test_expiry_command_fails_an_abandoned_gateway_payment(): void
    {
        $order = $this->gatewayOrder();
        $this->gatewayPayment($order);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'processing']], 200)]);

        $this->artisan('orders:expire-pending-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);
        $this->assertSame('Payment was not completed in time', $order->payments()->first()->fresh()->failure_reason);
    }

    public function test_expiry_command_leaves_a_recent_checkout_and_paid_order_untouched(): void
    {
        $recentOrder = $this->gatewayOrder();
        $this->gatewayPayment($recentOrder, now()->subMinutes(14));
        $paidOrder = $this->gatewayOrder();
        $paidOrder->update(['payment_status' => PaymentStatus::Paid]);
        Http::preventStrayRequests();

        $this->artisan('orders:expire-pending-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $recentOrder->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Paid, $paidOrder->fresh()->payment_status);
    }

    public function test_expiry_command_settles_a_paid_gateway_transaction_and_is_idempotent(): void
    {
        $order = $this->gatewayOrder();
        $this->gatewayPayment($order);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'success']], 200)]);

        $this->artisan('orders:expire-pending-payments')->assertSuccessful();
        $this->artisan('orders:expire-pending-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        Http::assertSentCount(1);
    }

    public function test_expiry_command_skips_payment_when_gateway_is_unreachable(): void
    {
        $order = $this->gatewayOrder();
        $this->gatewayPayment($order);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::failedConnection()]);

        $this->artisan('orders:expire-pending-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_late_paid_webhook_flags_an_expired_order_for_refund(): void
    {
        $order = $this->gatewayOrder();
        $order->update(['payment_status' => PaymentStatus::Failed, 'status' => OrderStatus::PaymentFailed, 'completed_at' => now()]);
        Payment::create(['order_id' => $order->id, 'transaction_id' => 'txn_late', 'merchant_reference' => 'PAAN-'.$order->id.'-1', 'amount' => '45.00', 'status' => 'expired', 'failure_reason' => 'Payment was not completed in time']);
        $raw = '{"transaction_id":"txn_late"}';
        Http::preventStrayRequests();
        Http::fake(['gateway.test/*' => Http::response(['data' => ['status' => 'success']], 200)]);

        $this->call('POST', '/api/v1/webhooks/gateway', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $raw, 'secret')], $raw)->assertOk();

        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertTrue($order->fresh()->refund_pending);
    }

    private function orderFor(User $customer): Order
    {
        return Order::create(['user_id' => $customer->id, 'order_number' => 'PAAN-'.fake()->unique()->numerify('#####'), 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending, 'subtotal' => '45.00', 'total' => '45.00', 'placed_at' => now()]);
    }

    private function gatewayOrder(): Order
    {
        $order = $this->orderFor(User::factory()->create());
        $order->update(['payment_method' => 'gateway']);

        return $order;
    }

    private function gatewayPayment(Order $order, ?\DateTimeInterface $startedAt = null): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'transaction_id' => 'txn_'.fake()->unique()->uuid(),
            'merchant_reference' => 'PAAN-'.$order->id.'-'.fake()->unique()->randomDigitNotNull(),
            'amount' => '45.00',
            'status' => 'pending',
            'payment_started_at' => $startedAt ?? now()->subMinutes(16),
        ]);
    }
}
