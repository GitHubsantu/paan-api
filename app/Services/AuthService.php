<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private OtpService $otp) {}

    /** @return array{resend_after:int} */
    public function sendOtp(string $phone, string $purpose): array
    {
        $this->assertCustomerCanAuthenticate($phone);
        $this->otp->issue($phone);

        return ['resend_after' => (int) config('otp.resend_seconds')];
    }

    /** @return array{0: User, 1: string} */
    public function register(string $name, string $phone, string $otp): array
    {
        $this->assertCustomerCanAuthenticate($phone);
        $this->otp->verify($phone, $otp);

        $user = new User(['name' => $name, 'phone' => $phone]);
        $user->phone_verified_at = now(); // role defaults to customer; never from client
        $user->save();
        $user->refresh();

        return [$user, $this->issueToken($user, 'customer')];
    }

    /** @return array{0: User, 1: string} */
    public function loginWithOtp(string $phone, string $otp): array
    {
        $this->assertCustomerCanAuthenticate($phone);
        $this->otp->verify($phone, $otp);

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            throw new ApiException('Invalid or expired OTP.', 422, ['otp' => ['Invalid or expired OTP.']]);
        }
        if (! $user->is_active && $user->blocked_reason !== null) {
            throw new ApiException('This account is blocked. Please contact the shop.', 403, [], 'CUSTOMER_BLOCKED');
        }
        if (! $user->is_active) {
            throw new ApiException('Your account is deactivated.', 403);
        }
        if ($user->isAdmin()) {
            throw new ApiException('This account must use admin login.', 403);
        }

        return [$user, $this->issueToken($user, 'customer')];
    }

    /** @return array{0: User, 1: string, 2: bool} */
    public function verifyCustomerOtp(string $phone, string $otp): array
    {
        $this->assertCustomerCanAuthenticate($phone);
        $this->otp->verify($phone, $otp);
        $user = User::where('phone', $phone)->first();

        if ($user && ($user->isAdmin() || ! $user->is_active)) {
            throw new ApiException("This number can't sign in here.", 403, [], 'NOT_ALLOWED');
        }

        $isNewUser = ! $user;
        $user ??= User::create(['name' => null, 'phone' => $phone]);

        return [$user, $this->issueToken($user, 'customer'), $isNewUser];
    }

    /** @return array{0: User, 1: string} */
    public function attemptAdmin(string $phone, string $password): array
    {
        $user = User::where('phone', $phone)->where('role', 'admin')->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new ApiException('Incorrect phone number or password.', 422);
        }
        if (! $user->is_active) {
            throw new ApiException('Your account is deactivated.', 403);
        }

        return [$user, $this->issueToken($user, 'admin')];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function issueToken(User $user, string $ability): string
    {
        return $user->createToken('android', [$ability])->plainTextToken;
    }

    private function assertCustomerCanAuthenticate(string $phone): void
    {
        $user = User::withTrashed()->where('phone', $phone)->first();
        if ($user?->trashed()) {
            throw new ApiException('This account was removed. Please contact the shop.', 409, [], 'CUSTOMER_DELETED');
        }
        if ($user && ! $user->isAdmin() && ! $user->is_active && $user->blocked_reason !== null) {
            throw new ApiException('This account is blocked. Please contact the shop.', 403, [], 'CUSTOMER_BLOCKED');
        }
    }
}
