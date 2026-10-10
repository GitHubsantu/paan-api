<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private OtpService $otp) {}

    /** @return array{expires_in:int, resend_after:int, debug_otp?:string} */
    public function sendOtp(string $phone, string $purpose): array
    {
        $user = User::where('phone', $phone)->first();
        $debugOtp = null;

        if ($purpose === 'register') {
            if ($user) {
                throw new ApiException('This phone number is already registered. Please log in.', 409);
            }
            $debugOtp = $this->otp->issue($phone);
        } elseif ($user && $user->is_active) {
            // Login: same response whether or not the account exists (no enumeration).
            $debugOtp = $this->otp->issue($phone);
        }

        $response = [
            'expires_in' => config('otp.ttl_minutes') * 60,
            'resend_after' => (int) config('otp.resend_seconds'),
        ];
        if ($debugOtp !== null) {
            $response['debug_otp'] = $debugOtp; // mock mode only
        }

        return $response;
    }

    /** @return array{0: User, 1: string} */
    public function register(string $name, string $phone, string $otp): array
    {
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
        $this->otp->verify($phone, $otp);

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            throw new ApiException('Invalid or expired OTP.', 422, ['otp' => ['Invalid or expired OTP.']]);
        }
        if (! $user->is_active) {
            throw new ApiException('Your account is deactivated.', 403);
        }
        if ($user->isAdmin()) {
            throw new ApiException('This account must use admin login.', 403);
        }

        return [$user, $this->issueToken($user, 'customer')];
    }

    /** @return array{0: User, 1: string} */
    public function attemptAdmin(string $phone, string $password): array
    {
        $user = User::where('phone', $phone)->where('role', 'admin')->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new ApiException('Invalid phone or password.', 401);
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
}
