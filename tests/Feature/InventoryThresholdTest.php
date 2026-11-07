<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_threshold_is_configurable_per_warehouse_and_uses_available_quantity(): void
    {
        $product = Product::query()->create([
            'name' => 'Producto con umbral',
            'slug' => 'producto-umbral-'.uniqid(),
            'sku' => 'THRESHOLD-'.strtoupper(uniqid()),
            'price_amount' => 1000,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        $inventory = app(InventoryService::class)->setOnHand(
            $product,
            20,
            reason: 'Carga inicial',
            warehouse: Warehouse::primary(),
        );

        $inventory->update([
            'quantity_reserved' => 5,
            'quantity_blocked' => 3,
            'minimum_stock_quantity' => 12,
        ]);

        $lowStockIds = Inventory::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= COALESCE(minimum_stock_quantity, 5)')
            ->pluck('id');

        $this->assertSame(12, $inventory->fresh()->available_quantity);
        $this->assertTrue($lowStockIds->contains($inventory->id));

        $inventory->update(['minimum_stock_quantity' => 11]);

        $lowStockIds = Inventory::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= COALESCE(minimum_stock_quantity, 5)')
            ->pluck('id');

        $this->assertFalse($lowStockIds->contains($inventory->id));
    }

    public function test_inventory_without_custom_threshold_uses_default_of_five_available_units(): void
    {
        $product = Product::query()->create([
            'name' => 'Producto umbral predeterminado',
            'slug' => 'producto-umbral-default-'.uniqid(),
            'sku' => 'THRESHOLD-DEFAULT-'.strtoupper(uniqid()),
            'price_amount' => 1000,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial', warehouse: Warehouse::primary());

        $this->assertNull($inventory->minimum_stock_quantity);
        $this->assertTrue(Inventory::query()
            ->whereKey($inventory->id)
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= COALESCE(minimum_stock_quantity, 5)')
            ->exists());
    }
}
