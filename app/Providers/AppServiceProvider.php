<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        // Memaksa semua link asset, form action, dan route menggunakan HTTPS di server Railway
        if (config('app.env') === 'production' || env('APP_URL') && str_contains(env('APP_URL'), 'https')) {
            URL::forceScheme('https');
        }
    }
}