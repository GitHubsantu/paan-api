<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upserts_the_authenticated_customers_android_device_token(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, ['customer']);

        $this->postJson('/api/v1/device-tokens', ['token' => 'device-token-123', 'platform' => 'android'])
            ->assertCreated();
        $this->postJson('/api/v1/device-tokens', ['token' => 'device-token-123', 'platform' => 'android'])
            ->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $customer->id, 'token' => 'device-token-123', 'platform' => 'android']);
    }

    public function test_returns_404_when_deleting_another_customers_device_token(): void
    {
        $customer = User::factory()->create();
        $otherToken = DeviceToken::create(['user_id' => User::factory()->create()->id, 'token' => 'other-device-token', 'platform' => 'android']);
        Sanctum::actingAs($customer, ['customer']);

        $this->deleteJson("/api/v1/device-tokens/{$otherToken->token}")->assertNotFound();
    }
}
