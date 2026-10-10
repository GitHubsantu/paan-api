<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_admin_dashboard_is_requested_without_a_token(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_returns_403_when_a_customer_token_requests_admin_data(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['customer']);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not authorized to perform this action.');
    }

    public function test_returns_403_when_a_customer_token_attempts_an_admin_write(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['customer']);

        $this->postJson('/api/v1/admin/addons', ['name' => 'Mint', 'price' => 5])
            ->assertForbidden();
    }
}
