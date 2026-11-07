<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\InventoryCostChange;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryCostManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Gestor de costos',
            'slug' => 'cost-manager-test',
            'permissions' => ['inventory_costs.view', 'inventory_costs.update'],
        ]);
        $user->roles()->attach($role);
        $user->warehouseAccesses()->create([
            'warehouse_id' => Warehouse::primary()->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);

        return $user;
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto con costo',
            'slug' => 'producto-con-costo-'.uniqid(),
            'sku' => 'COST-'.strtoupper(uniqid()),
            'price_amount' => 2500,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    public function test_costs_are_saved_per_inventory_record_and_audited(): void
    {
        $manager = $this->manager();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        $updated = app(InventoryCostService::class)->updateCosts($inventory, $manager, 1250, 1800, 'Factura de compra');

        $this->assertSame(1250, $updated->acquisition_cost_amount);
        $this->assertSame(1800, $updated->minimum_sale_price_amount);
        $this->assertDatabaseHas('inventory_cost_changes', [
            'inventory_id' => $inventory->id,
            'user_id' => $manager->id,
            'previous_acquisition_cost_amount' => null,
            'new_acquisition_cost_amount' => 1250,
            'new_minimum_sale_price_amount' => 1800,
            'reason' => 'Factura de compra',
        ]);
    }

    public function test_minimum_sale_price_cannot_be_lower_than_acquisition_cost(): void
    {
        $manager = $this->manager();
        $inventory = app(InventoryService::class)->setOnHand($this->product(), 5, reason: 'Carga inicial');

        try {
            app(InventoryCostService::class)->updateCosts($inventory, $manager, 2000, 1999, 'Prueba de límite');
            $this->fail('Expected the minimum price validation to fail.');
        } catch (ValidationException) {
            $this->assertSame(0, InventoryCostChange::query()->count());
        }
    }

    public function test_user_without_cost_permission_cannot_change_inventory_costs(): void
    {
        $manager = $this->manager();
        $inventory = app(InventoryService::class)->setOnHand($this->product(), 5, reason: 'Carga inicial');
        $manager->directPermissions()->create(['permission' => 'inventory_costs.update', 'allowed' => false]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InventoryCostService::class)->updateCosts($inventory, $manager, 1000, null, 'Sin autorización');
    }

    public function test_cost_isolated_by_warehouse_inventory_row(): void
    {
        $manager = $this->manager();
        $product = $this->product();
        $primary = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga principal');
        $otherWarehouse = Warehouse::query()->create([
            'code' => 'COST-SECONDARY',
            'name' => 'Almacén secundario',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $manager->warehouseAccesses()->create([
            'warehouse_id' => $otherWarehouse->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);
        $secondary = app(InventoryService::class)->setOnHand($product, 3, reason: 'Carga secundaria', warehouse: $otherWarehouse);

        app(InventoryCostService::class)->updateCosts($primary, $manager, 1000, null, 'Costo principal');
        app(InventoryCostService::class)->updateCosts($secondary, $manager, 1500, null, 'Costo secundario');

        $this->assertSame(1000, $primary->fresh()->acquisition_cost_amount);
        $this->assertSame(1500, $secondary->fresh()->acquisition_cost_amount);
    }
}
