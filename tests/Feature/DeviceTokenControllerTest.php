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

    public function test_moves_a_globally_unique_token_to_the_authenticated_customer(): void
    {
        $customer = User::factory()->create();
        $previousOwner = User::factory()->create();
        DeviceToken::create(['user_id' => $previousOwner->id, 'token' => 'device-token-123', 'platform' => 'android', 'app' => 'customer']);
        Sanctum::actingAs($customer, ['customer']);

        $this->postJson('/api/v1/device-tokens', ['token' => 'device-token-123', 'platform' => 'android', 'app' => 'customer'])
            ->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $customer->id, 'token' => 'device-token-123', 'platform' => 'android', 'app' => 'customer']);
    }

    public function test_rejects_an_app_that_does_not_match_the_authenticated_users_role(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['customer']);

        $this->postJson('/api/v1/device-tokens', ['token' => 'device-token-123', 'platform' => 'android', 'app' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('app');
    }

    public function test_deleting_another_customers_token_is_an_idempotent_success_without_removing_it(): void
    {
        $customer = User::factory()->create();
        $otherToken = DeviceToken::create(['user_id' => User::factory()->create()->id, 'token' => 'other-device-token', 'platform' => 'android']);
        Sanctum::actingAs($customer, ['customer']);

        $this->deleteJson("/api/v1/device-tokens/{$otherToken->token}")->assertOk();
        $this->assertDatabaseHas('device_tokens', ['id' => $otherToken->id]);
    }

    public function test_deactivating_a_user_removes_their_device_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        DeviceToken::create(['user_id' => $admin->id, 'token' => 'admin-token', 'platform' => 'android', 'app' => 'admin']);

        $admin->forceFill(['is_active' => false])->save();

        $this->assertDatabaseMissing('device_tokens', ['user_id' => $admin->id]);
    }
}
