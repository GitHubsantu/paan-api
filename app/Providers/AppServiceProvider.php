<?php

namespace App\Providers;

use App\Services\Otp\NullOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentGateway;
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
        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.driver')) {
            'mock' => new MockPaymentGateway,
            default => throw new \RuntimeException('Unsupported payment driver.'),
        });
    }

    public function boot(): void
    {
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(3)->by('otp-m|'.$r->input('phone').'|'.$r->ip()),
            Limit::perHour(10)->by('otp-h|'.$r->input('phone')),
        ]);

        // OTP verify / admin login attempts
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->input('phone').'|'.$r->ip()));

        RateLimiter::for('orders', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
    }
}
