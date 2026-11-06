<?php

namespace App\Providers;

use App\Models\AdminRole;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Payments\PaymentDriverManager;
use App\Policies\AdminRolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(AdminRole::class, AdminRolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (?User $user, string $ability, array $arguments): ?bool {
            if (! $user || ! $user->admin_access_enabled) {
                return null;
            }

            $actions = [
                'viewAny' => 'view',
                'view' => 'view',
                'create' => 'create',
                'update' => 'update',
                'delete' => 'delete',
                'deleteAny' => 'delete',
                'restore' => 'update',
                'forceDelete' => 'delete',
                'reorder' => 'update',
            ];

            if (! isset($actions[$ability])) {
                return null;
            }

            $subject = $arguments[0] ?? null;
            $modelClass = is_object($subject) ? $subject::class : $subject;

            if (! is_string($modelClass)) {
                return null;
            }

            $resource = config('admin-permissions.model_resources')[$modelClass] ?? null;

            if (! $resource) {
                return null;
            }

            if (in_array($resource, ['admin_users', 'admin_roles'], true)) {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return $user->hasAdminPermission("{$resource}.{$actions[$ability]}");
        });

        ResetPassword::createUrlUsing(
            fn (User $user, string $token): string => route('customer.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]),
        );
    }
}
