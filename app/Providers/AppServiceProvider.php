<?php

namespace App\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $allowedIPs = array_map('trim', explode(',', config('app.debug_allowed_ips')));

        $allowedIPs = array_filter($allowedIPs);

        if (empty($allowedIPs)) {
            return;
        }

        if (in_array(Request::ip(), $allowedIPs)) {
            Debugbar::enable();
        } else {
            Debugbar::disable();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });

        $this->configureRateLimiting();
    }

    /**
     * Rate limiters for authentication endpoints.
     *
     * Keyed by IP only. Keying by email as well would let an attacker
     * rotate the email field to get a fresh bucket per attempt.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('customer-login', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('password-request', fn ($request) => Limit::perMinute(3)->by($request->ip()));
    }
}
