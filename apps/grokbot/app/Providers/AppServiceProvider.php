<?php

namespace App\Providers;

use App\Contracts\TinyVmDriver;
use App\Infrastructure\TinyVm\FakeTinyVmDriver;
use App\Infrastructure\TinyVm\FirecrackerGatewayDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TinyVmDriver::class, fn ($app): TinyVmDriver => config('tinyvm.driver') === 'firecracker'
            ? $app->make(FirecrackerGatewayDriver::class)
            : $app->make(FakeTinyVmDriver::class));
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('bot-messages', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id));
    }
}
