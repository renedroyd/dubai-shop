<?php

namespace Tests\\Feature;

use App\\Models\\Product;
use App\\Models\\Warehouse;
use App\\Models\\WarehouseLocation;
use App\\Services\\Inventory\\InventoryService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use InvalidArgumentException;
use Tests\\TestCase;

class MultiWarehouseFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto multi almacén',
            'slug' => 'producto-multi-almacen-'.uniqid(),
            'sku' => 'MULTI-'.strtoupper(uniqid()),
            'price_amount' => 2500,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    public function test_migration_creates_a_primary_warehouse_and_unassigned_location(): void
    {
        $warehouse = Warehouse::primary();

        $this->assertSame('MAIN', $warehouse->code);
        $this->assertTrue($warehouse->is_primary);
        $this->assertDatabaseHas('warehouse_locations', [
            'warehouse_id' => $warehouse->id,
            'code' => 'UNASSIGNED',
            'type' => WarehouseLocation::TYPE_UNASSIGNED,
        ]);
    }

    public function test_same_product_can_have_independent_stock_in_multiple_warehouses(): void
    {
        $product = $this->product();
        $primary = Warehouse::primary();
        $secondary = Warehouse::query()->create([
            'code' => 'POS-01',
            'name' => 'Almacén POS principal',
            'type' => 'pos',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $primaryInventory = app(InventoryService::class)->setOnHand(
            $product,
            15,
            reason: 'Carga inicial principal',
            warehouse: $primary,
        );
        $secondaryInventory = app(InventoryService::class)->setOnHand(
            $product,
            6,
            reason: 'Carga inicial POS',
            warehouse: $secondary,
        );

        $this->assertNotSame($primaryInventory->id, $secondaryInventory->id);
        $this->assertSame(15, $primaryInventory->fresh()->quantity_on_hand);
        $this->assertSame(6, $secondaryInventory->fresh()->quantity_on_hand);
        $this->assertSame($primary->id, $primaryInventory->warehouse_id);
        $this->assertSame($secondary->id, $secondaryInventory->warehouse_id);
        $this->assertDatabaseCount('inventories', 2);
    }

    public function test_existing_inventory_service_defaults_to_the_primary_warehouse(): void
    {
        $product = $this->product();

        $inventory = app(InventoryService::class)->setOnHand($product, 8, reason: 'Carga inicial');

        $this->assertSame(Warehouse::primary()->id, $inventory->warehouse_id);
    }

    public function test_available_quantity_excludes_reserved_and_blocked_units(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 12, reason: 'Carga inicial');
        $inventory->update([
            'quantity_reserved' => 3,
            'quantity_blocked' => 2,
        ]);

        $this->assertSame(7, $inventory->fresh()->available_quantity);
    }

    public function test_warehouse_locations_support_nested_aisle_shelf_and_bin_levels(): void
    {
        $warehouse = Warehouse::primary();
        $aisle = WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'A',
            'name' => 'Pasillo A',
            'type' => WarehouseLocation::TYPE_AISLE,
        ]);
        $shelf = WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'parent_id' => $aisle->id,
            'code' => 'A-01',
            'name' => 'Estantería A-01',
            'type' => WarehouseLocation::TYPE_SHELF,
        ]);
        $bin = WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'parent_id' => $shelf->id,
            'code' => 'A-01-01',
            'name' => 'Posición A-01-01',
            'type' => WarehouseLocation::TYPE_BIN,
        ]);

        $this->assertSame($aisle->id, $shelf->parent->id);
        $this->assertSame($shelf->id, $bin->parent->id);
        $this->assertSame($warehouse->id, $bin->warehouse_id);
    }

    public function test_inventory_cannot_be_adjusted_in_an_inactive_warehouse(): void
    {
        $product = $this->product();
        $warehouse = Warehouse::query()->create([
            'code' => 'CLOSED',
            'name' => 'Almacén inactivo',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->setOnHand(
            $product,
            4,
            reason: 'No debe permitir el ajuste',
            warehouse: $warehouse,
        );
    }
}
