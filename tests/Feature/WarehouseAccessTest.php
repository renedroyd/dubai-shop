<?php

namespace Tests\Feature;

use App\Filament\Pages\PosReports;
use App\Models\AdminRole;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Pos\PosCashSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseAccessTest extends TestCase
{
    use RefreshDatabase;

    private function warehouse(string $code): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Almacén '.$code,
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    private function assign(User $user, Warehouse $warehouse, array $permissions): void
    {
        $user->update(['admin_access_enabled' => true]);
        $user->warehouseAccesses()->create([
            'warehouse_id' => $warehouse->id,
            'permissions' => $permissions,
        ]);
    }

    private function terminal(Warehouse $warehouse): PosTerminal
    {
        $location = PosLocation::query()->create([
            'code' => 'LOC-'.$warehouse->code,
            'name' => 'Tienda '.$warehouse->name,
            'is_active' => true,
        ]);

        return PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'POS-'.$warehouse->code,
            'name' => 'Terminal '.$warehouse->name,
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto con acceso restringido',
            'slug' => 'producto-acceso-'.uniqid(),
            'sku' => 'ACCESS-'.strtoupper(uniqid()),
            'price_amount' => 1500,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    public function test_user_receives_only_explicit_permissions_for_each_warehouse(): void
    {
        $primary = Warehouse::primary();
        $secondary = $this->warehouse('ACCESS-'.strtoupper(uniqid()));
        $user = User::factory()->create();
        $this->assign($user, $secondary, ['inventory.manage']);

        $this->assertTrue($user->hasWarehousePermission($secondary, 'view'));
        $this->assertTrue($user->hasWarehousePermission($secondary, 'inventory.manage'));
        $this->assertFalse($user->hasWarehousePermission($secondary, 'pos.operate'));
        $this->assertFalse($user->hasWarehousePermission($primary, 'view'));
        $this->assertSame([$secondary->id], $user->warehouseIdsWithPermission('view'));
    }

    public function test_superadministrator_keeps_global_warehouse_access(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $user->roles()->attach(AdminRole::query()->where('slug', 'superadmin')->firstOrFail());

        $this->assertTrue($user->hasWarehousePermission(Warehouse::primary(), 'pos.operate'));
        $this->assertTrue($user->hasWarehousePermission($this->warehouse('GLOBAL-'.strtoupper(uniqid())), 'reports.view'));
    }

    public function test_inventory_queries_are_limited_to_assigned_warehouses(): void
    {
        $primary = Warehouse::primary();
        $secondary = $this->warehouse('SCOPE-'.strtoupper(uniqid()));
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, warehouse: $primary);
        app(InventoryService::class)->setOnHand($product, 8, warehouse: $secondary);
        $user = User::factory()->create();
        $this->assign($user, $secondary, ['view']);

        $this->assertSame(
            [$secondary->id],
            Inventory::query()->accessibleTo($user)->pluck('warehouse_id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_global_inventory_role_cannot_bypass_missing_warehouse_permission(): void
    {
        $warehouse = Warehouse::primary();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 4, warehouse: $warehouse);
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Gestor global de inventario',
            'slug' => 'inventory-global-'.uniqid(),
            'permissions' => ['panel.access', 'inventories.view', 'inventories.update'],
        ]);
        $user->roles()->attach($role);
        $this->assign($user, $warehouse, ['view']);

        $this->assertTrue(Gate::forUser($user)->allows('view', $inventory));
        $this->assertFalse(Gate::forUser($user)->allows('update', $inventory));
    }

    public function test_pos_session_cannot_be_opened_without_pos_permission_for_its_warehouse(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $terminal = $this->terminal(Warehouse::primary());

        $this->expectException(AuthorizationException::class);
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 0);
    }

    public function test_inventory_adjustment_cannot_bypass_warehouse_assignment(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $product = $this->product();

        $this->expectException(AuthorizationException::class);
        app(InventoryService::class)->setOnHand($product, 3, userId: $user->id, warehouse: Warehouse::primary());
    }

    public function test_reports_require_both_global_resource_permission_and_warehouse_report_access(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Reportes',
            'slug' => 'report-access-'.uniqid(),
            'permissions' => ['panel.access', 'pos_reports.view'],
        ]);
        $user->roles()->attach($role);
        $this->assign($user, Warehouse::primary(), ['reports.view']);

        $this->actingAs($user);
        $this->assertTrue(PosReports::canAccess());
    }

    public function test_reports_are_hidden_without_warehouse_report_assignment(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Reportes sin almacén',
            'slug' => 'report-no-warehouse-'.uniqid(),
            'permissions' => ['panel.access', 'pos_reports.view'],
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user);
        $this->assertFalse(PosReports::canAccess());
    }
}
