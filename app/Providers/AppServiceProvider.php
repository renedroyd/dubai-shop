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
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\PosCashSession;
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
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferRule;
use App\Policies\WarehousePolicy;
use App\Policies\PosLocationPolicy;
use App\Policies\PosTerminalPolicy;
use App\Policies\PosCashSessionPolicy;
use App\Policies\WarehouseLocationPolicy;
use App\Policies\WarehouseTransferPolicy;
use App\Policies\WarehouseTransferRulePolicy;
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
        Gate::policy(PosLocation::class, PosLocationPolicy::class);
        Gate::policy(PosTerminal::class, PosTerminalPolicy::class);
        Gate::policy(PosCashSession::class, PosCashSessionPolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);
        Gate::policy(WarehouseTransfer::class, WarehouseTransferPolicy::class);
        Gate::policy(WarehouseTransferRule::class, WarehouseTransferRulePolicy::class);

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

            if (is_object($subject)) {
                $warehouseIds = match (true) {
                    $subject instanceof Warehouse => [$subject->id],
                    $subject instanceof WarehouseLocation => [$subject->warehouse_id],
                    $subject instanceof Inventory => [$subject->warehouse_id],
                    $subject instanceof InventoryMovement => [$subject->inventory?->warehouse_id],
                    $subject instanceof InventoryLocationBalance => [$subject->inventory?->warehouse_id],
                    $subject instanceof WarehouseTransfer => [$subject->source_warehouse_id, $subject->destination_warehouse_id],
                    $subject instanceof PosTerminal => [$subject->warehouse_id],
                    $subject instanceof PosCashSession => [$subject->terminal?->warehouse_id],
                    $subject instanceof Payment && $subject->order?->channel === 'pos' => [$subject->order->pos_warehouse_id],
                    $subject instanceof Payment => $subject->order?->items()->with('inventory')->get()->pluck('inventory.warehouse_id')->all(),
                    $subject instanceof Order && $subject->channel === 'pos' => [$subject->pos_warehouse_id],
                    $subject instanceof Order => $subject->items()->with('inventory')->get()->pluck('inventory.warehouse_id')->all(),
                    $subject instanceof WarehouseTransferRule && ($subject->source_warehouse_id === null || $subject->destination_warehouse_id === null) => Warehouse::query()->pluck('id')->all(),
                    $subject instanceof WarehouseTransferRule => [$subject->source_warehouse_id, $subject->destination_warehouse_id],
                    $subject instanceof PosLocation => $subject->terminals()->pluck('warehouse_id')->all(),
                    default => null,
                };

                if ($warehouseIds !== null) {
                    $warehouseIds = array_values(array_unique(array_filter(array_map('intval', $warehouseIds))));
                    $permission = match ($resource) {
                        'inventories', 'inventory_movements', 'inventory_location_balances' =>
                            $actions[$ability] === 'view' ? 'view' : 'inventory.manage',
                        'warehouses', 'warehouse_locations' =>
                            $actions[$ability] === 'view' ? 'view' : 'warehouse.manage',
                        'warehouse_transfers' =>
                            $actions[$ability] === 'view' ? 'view' : ($actions[$ability] === 'create' ? 'transfers.request' : 'transfers.approve'),
                        'pos_locations' =>
                            $actions[$ability] === 'view' ? 'view' : 'warehouse.manage',
                        'pos_terminals' =>
                            $actions[$ability] === 'view' ? 'view' : 'warehouse.manage',
                        'pos_cash_sessions' =>
                            $actions[$ability] === 'view' ? 'view' : 'pos.operate',
                        'payments' => $subject->order?->channel === 'pos'
                            ? ($actions[$ability] === 'view' ? 'view' : 'pos.operate')
                            : 'view',
                        'orders' => $subject->channel === 'pos'
                            ? ($actions[$ability] === 'view' ? 'view' : 'pos.operate')
                            : 'view',
                        'warehouse_transfer_rules' => 'warehouse.manage',
                        default => null,
                    };

                    if ($permission !== null) {
                        foreach ($warehouseIds as $warehouseId) {
                            if (! $user->hasWarehousePermission($warehouseId, $permission)) {
                                return false;
                            }
                        }
                    }
                }
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
