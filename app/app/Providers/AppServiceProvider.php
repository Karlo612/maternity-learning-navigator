<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('classifications', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('explanations', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
