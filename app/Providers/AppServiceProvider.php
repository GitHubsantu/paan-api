<?php

namespace App\Providers;

use App\Services\AdminNotifications\AdminOrderNotifier;
use App\Services\AdminNotifications\FcmAdminOrderNotifier;
use App\Services\Otp\NullOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmPushSender;
use App\Services\Push\GoogleFcmAccessTokenProvider;
use App\Services\Push\PushSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap for a real SMS sender later.
        $this->app->bind(OtpSender::class, NullOtpSender::class);
        $this->app->bind(AdminOrderNotifier::class, FcmAdminOrderNotifier::class);
        $this->app->bind(PushSender::class, FcmPushSender::class);
        $this->app->bind(FcmAccessTokenProvider::class, GoogleFcmAccessTokenProvider::class);
        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.driver')) {
            'mock' => new MockPaymentGateway,
            default => throw new \RuntimeException('Unsupported payment driver.'),
        });
    }

    public function boot(): void
    {
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinutes(10, 5)->by('otp|'.$r->input('phone').'|'.$r->ip()));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinutes(10, 10)->by('otp-verify|'.$r->input('phone').'|'.$r->ip()));

        // OTP verify / admin login attempts
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->input('phone').'|'.$r->ip()));

        RateLimiter::for('orders', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('pickup', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('admin-writes', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('device-tokens', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('notifications', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    }
}
