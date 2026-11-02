<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Payments\PaymentDriverManager;
use Illuminate\Auth\Notifications\ResetPassword;
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
        Order::observe(OrderObserver::class);

        ResetPassword::createUrlUsing(
            fn (User $user, string $token): string => route('customer.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]),
        );
    }
}
