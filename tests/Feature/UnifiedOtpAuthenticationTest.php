<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UnifiedOtpAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verifies_an_unknown_phone_and_creates_a_customer_with_a_null_name(): void
    {
        $phone = '9888888887';
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $phone])
            ->assertOk()
            ->assertJsonPath('data.resend_after', 30);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'otp' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.name', null)
            ->assertJsonPath('data.user.phone', $phone)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['phone' => $phone, 'name' => null, 'role' => 'customer']);
    }

    public function test_rejects_admin_phone_from_unified_otp_verification(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '9888888886']);
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $admin->phone, 'purpose' => 'auth'])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => $admin->phone, 'otp' => '123456'])
            ->assertForbidden()
            ->assertJsonPath('message', "This number can't sign in here.")
            ->assertJsonPath('code', 'NOT_ALLOWED');
    }
}
