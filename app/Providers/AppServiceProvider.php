<?php

namespace App\Providers;

use App\Models\AdminRole;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryLocationBalance;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Models\StorefrontBanner;
use App\Models\StorefrontPage;
use App\Models\StorefrontMenuItem;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Policies\WarehousePolicy;
use App\Policies\WarehouseLocationPolicy;
use App\Observers\OrderObserver;
use App\Payments\PaymentDriverManager;
use App\Policies\AdminRolePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\BrandPolicy;
use App\Policies\ProductPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\InventoryMovementPolicy;
use App\Policies\InventoryLocationBalancePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\ShippingZonePolicy;
use App\Policies\NotificationTemplatePolicy;
use App\Policies\NotificationLogPolicy;
use App\Policies\StorefrontBannerPolicy;
use App\Policies\StorefrontPagePolicy;
use App\Policies\StorefrontMenuItemPolicy;
use App\Policies\StorefrontSettingPolicy;
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
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
        Gate::policy(InventoryLocationBalance::class, InventoryLocationBalancePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(ShippingZone::class, ShippingZonePolicy::class);
        Gate::policy(NotificationTemplate::class, NotificationTemplatePolicy::class);
        Gate::policy(NotificationLog::class, NotificationLogPolicy::class);
        Gate::policy(StorefrontBanner::class, StorefrontBannerPolicy::class);
        Gate::policy(StorefrontPage::class, StorefrontPagePolicy::class);
        Gate::policy(StorefrontMenuItem::class, StorefrontMenuItemPolicy::class);
        Gate::policy(StorefrontSetting::class, StorefrontSettingPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);

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
