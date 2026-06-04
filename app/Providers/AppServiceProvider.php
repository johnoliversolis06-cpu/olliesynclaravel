<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 🔥 FORCE HTTPS on Render (fixes mixed content)
        URL::forceScheme('https');

        // Vite optimization (keep this)
        Vite::prefetch(concurrency: 3);
    }
}