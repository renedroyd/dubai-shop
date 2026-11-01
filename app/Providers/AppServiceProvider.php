<?php

namespace App\Providers;

use App\Payments\PaymentDriverManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentDriverManager::class,
            fn () => new PaymentDriverManager(
                config('payments.drivers', []),
                config('payments.labels', []),
            ),
        );
    }

    public function boot(): void
    {
        //
    }
}
