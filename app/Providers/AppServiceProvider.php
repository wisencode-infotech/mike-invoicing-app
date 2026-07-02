<?php

namespace App\Providers;

use App\Services\Sms\Contracts\SmsProviderContract;
use App\Services\Sms\Providers\TwilioSmsProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Adding a new SMS provider means adding a config/sms.php block and
        // a provider class here — SmsService and its callers never change.
        $this->app->bind(SmsProviderContract::class, function () {
            return match (config('sms.default')) {
                'twilio' => new TwilioSmsProvider,
                default => new TwilioSmsProvider,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keyed by the raw bearer token (hashed, never logged/stored in
        // plain form) rather than the authenticated user, so throttling
        // still applies to requests with an invalid/missing token — see
        // EnsureApiTokenIsValid, which runs after this middleware.
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();
            $key = $token ? hash('sha256', $token) : $request->ip();

            return Limit::perMinute((int) config('api.rate_limit_per_minute'))->by($key);
        });
    }
}
