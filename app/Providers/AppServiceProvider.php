<?php

namespace App\Providers;

use App\Services\Push\ArrayPushGateway;
use App\Services\Push\ExpoPushGateway;
use App\Services\Push\LogPushGateway;
use App\Services\Push\PushGateway;
use App\Services\Sms\AndroidPhoneSmsGateway;
use App\Services\Sms\ArraySmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, fn (): SmsGateway => match (config('services.sms.driver')) {
            'semaphore' => new SemaphoreSmsGateway,
            'android' => new AndroidPhoneSmsGateway,
            'array' => new ArraySmsGateway,
            default => new LogSmsGateway,
        });

        $this->app->singleton(PushGateway::class, fn (): PushGateway => match (config('services.push.driver')) {
            'expo' => new ExpoPushGateway(config('services.expo.url'), config('services.expo.access_token')),
            'array' => new ArrayPushGateway,
            default => new LogPushGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Limit how often login codes can be requested and tried.
     *
     * A text message costs money, so one phone number gets one code a minute
     * and five an hour, and one device gets twenty an hour.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login-code', function (Request $request): array {
            $phone = PhoneNumber::normalize((string) $request->input('phone')) ?? 'invalid';

            return [
                Limit::perMinute(1)->by('code-minute:'.$phone),
                Limit::perHour(5)->by('code-hour:'.$phone),
                Limit::perHour(20)->by('code-ip:'.$request->ip()),
            ];
        });

        // An email costs nothing, but the free mail account has a daily limit, so the same limits hold.
        RateLimiter::for('email-code', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(1)->by('email-code-minute:'.$email),
                Limit::perHour(5)->by('email-code-hour:'.$email),
                Limit::perHour(20)->by('email-code-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('login-token', function (Request $request): Limit {
            return Limit::perMinute(10)->by('token-ip:'.$request->ip());
        });
    }
}
