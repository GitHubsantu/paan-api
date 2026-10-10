<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_auth_me_is_requested_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_rejects_admin_accounts_from_the_customer_otp_login_flow_with_403(): void
    {
        $admin = User::factory()->admin()->create();
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $admin->phone, 'purpose' => 'login'])->assertOk();

        $this->postJson('/api/v1/auth/login', ['phone' => $admin->phone, 'otp' => config('otp.mock_code')])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account must use admin login.');
    }

    public function test_allows_admin_password_login_and_returns_an_admin_token(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '9999999999', 'password' => 'password']);

        $this->postJson('/api/v1/admin/login', ['phone' => $admin->phone, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
