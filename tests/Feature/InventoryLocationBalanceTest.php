<?php

namespace Tests\Feature;

use App\Models\InventoryLocationBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryLocationService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryLocationBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto por ubicación',
            'slug' => 'producto-ubicacion-'.uniqid(),
            'sku' => 'LOC-'.strtoupper(uniqid()),
            'price_amount' => 1200,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function physicalLocation(Warehouse $warehouse, string $code): WarehouseLocation
    {
        return WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => $code,
            'name' => 'Ubicación '.$code,
            'type' => WarehouseLocation::TYPE_BIN,
            'is_active' => true,
        ]);
    }

    public function test_existing_inventory_is_backfilled_into_unassigned_location(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 14, reason: 'Stock inicial');
        $unassigned = WarehouseLocation::query()
            ->where('warehouse_id', $inventory->warehouse_id)
            ->where('type', WarehouseLocation::TYPE_UNASSIGNED)
            ->firstOrFail();

        $this->assertDatabaseHas('inventory_location_balances', [
            'inventory_id' => $inventory->id,
            'warehouse_location_id' => $unassigned->id,
            'quantity_on_hand' => 14,
        ]);
    }

    public function test_transfer_from_unassigned_to_physical_location_preserves_warehouse_total(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 14, reason: 'Stock inicial');
        $warehouse = Warehouse::query()->findOrFail($inventory->warehouse_id);
        $unassigned = $warehouse->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $bin = $this->physicalLocation($warehouse, 'A-01');

        app(InventoryLocationService::class)->transfer($inventory, $unassigned, $bin, 9, 1, 'Ubicación física inicial');

        $this->assertSame(14, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(5, $unassigned->inventoryBalances()->where('inventory_id', $inventory->id)->value('quantity_on_hand'));
        $this->assertSame(9, $bin->inventoryBalances()->where('inventory_id', $inventory->id)->value('quantity_on_hand'));
        $this->assertSame(0, app(InventoryLocationService::class)->reconcile($inventory)['difference']);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'location_transfer',
            'quantity_delta' => 0,
            'from_location_id' => $unassigned->id,
            'to_location_id' => $bin->id,
        ]);
    }

    public function test_location_adjustment_updates_aggregate_inventory_and_audit_movement(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 10, reason: 'Stock inicial');
        $warehouse = Warehouse::query()->findOrFail($inventory->warehouse_id);
        $unassigned = $warehouse->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $bin = $this->physicalLocation($warehouse, 'B-01');
        app(InventoryLocationService::class)->transfer($inventory, $unassigned, $bin, 10, 1, 'Asignación física');

        app(InventoryLocationService::class)->adjust($inventory, $bin, 12, 1, 'Conteo físico');

        $this->assertSame(12, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(12, $bin->inventoryBalances()->where('inventory_id', $inventory->id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'location_adjustment',
            'quantity_delta' => 2,
            'to_location_id' => $bin->id,
        ]);
        $this->assertTrue(app(InventoryLocationService::class)->reconcile($inventory)['is_balanced']);
    }

    public function test_adjustment_cannot_reduce_total_below_reserved_and_blocked_stock(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 10, reason: 'Stock inicial');
        $inventory->update(['quantity_reserved' => 4, 'quantity_blocked' => 2]);
        $warehouse = Warehouse::query()->findOrFail($inventory->warehouse_id);
        $unassigned = $warehouse->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $bin = $this->physicalLocation($warehouse, 'C-01');
        app(InventoryLocationService::class)->transfer($inventory, $unassigned, $bin, 10, 1, 'Asignación física');

        $this->expectException(ValidationException::class);

        app(InventoryLocationService::class)->adjust($inventory, $bin, 5, 1, 'Intento inválido');
    }

    public function test_transfer_rejects_destination_from_another_warehouse(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Stock inicial');
        $primary = Warehouse::query()->findOrFail($inventory->warehouse_id);
        $secondary = Warehouse::query()->create([
            'code' => 'OTHER',
            'name' => 'Otro almacén',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $unassigned = $primary->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $foreignBin = $this->physicalLocation($secondary, 'OTHER-01');

        $this->expectException(ValidationException::class);

        app(InventoryLocationService::class)->transfer($inventory, $unassigned, $foreignBin, 1, 1, 'No debe cruzar almacenes');
    }
}
