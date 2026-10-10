<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_only_the_authenticated_customers_name_and_email(): void
    {
        $customer = User::factory()->create(['name' => 'Before', 'email' => null]);
        Sanctum::actingAs($customer, ['customer']);

        $this->putJson('/api/v1/profile', ['name' => 'After', 'email' => 'after@example.test', 'phone' => '9999999999', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'After')
            ->assertJsonPath('data.email', 'after@example.test');

        $customer->refresh();
        $this->assertSame('After', $customer->name);
        $this->assertSame('after@example.test', $customer->email);
        $this->assertNotSame('9999999999', $customer->phone);
        $this->assertFalse($customer->isAdmin());
    }

    public function test_returns_401_when_profile_is_requested_without_a_token(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
