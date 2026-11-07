<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\OnlineWarehouseFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineWarehouseFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'sku' => strtoupper(Str::random(8)),
            'price_amount' => 1250,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function secondaryWarehouse(array $attributes = []): Warehouse
    {
        return Warehouse::query()->create(array_merge([
            'code' => 'SECONDARY-'.Str::upper(Str::random(5)),
            'name' => 'Almacén secundario',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
            'online_fulfillment_enabled' => true,
            'online_fulfillment_priority' => 20,
        ], $attributes));
    }

    private function cartWith(array $products): Cart
    {
        $cart = Cart::query()->create([
            'session_id' => (string) Str::uuid(),
            'currency' => 'USD',
            'expires_at' => now()->addDay(),
        ]);

        foreach ($products as $product) {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'line_key' => 'product:'.$product->id,
                'quantity' => 1,
                'unit_price_amount' => $product->price_amount,
                'currency' => $product->currency,
            ]);
        }

        return $cart->load('items');
    }

    public function test_existing_primary_warehouse_is_enabled_for_online_fulfillment_by_default(): void
    {
        $primary = Warehouse::primary();

        $this->assertTrue($primary->online_fulfillment_enabled);
        $this->assertSame(10, $primary->online_fulfillment_priority);
    }

    public function test_order_prefers_one_warehouse_when_it_can_fulfill_the_entire_cart(): void
    {
        $primary = Warehouse::primary();
        $primary->update(['online_fulfillment_enabled' => true, 'online_fulfillment_priority' => 10]);
        $secondary = $this->secondaryWarehouse();

        $first = $this->product('Producto uno');
        $second = $this->product('Producto dos');
        $inventory = app(InventoryService::class);

        $primaryFirst = $inventory->setOnHand($first, 5, reason: 'Test', warehouse: $primary);
        $primarySecond = $inventory->setOnHand($second, 5, reason: 'Test', warehouse: $primary);
        $inventory->setOnHand($first, 10, reason: 'Test', warehouse: $secondary);
        $inventory->setOnHand($second, 10, reason: 'Test', warehouse: $secondary);

        $cart = $this->cartWith([$first, $second]);
        $allocations = app(OnlineWarehouseFulfillmentService::class)->allocateOrder($cart->items);

        $this->assertSame($primary->id, $allocations[$cart->items[0]->id][0]['inventory']->warehouse_id);
        $this->assertSame($primary->id, $allocations[$cart->items[1]->id][0]['inventory']->warehouse_id);
        $this->assertSame($primaryFirst->id, $allocations[$cart->items[0]->id][0]['inventory']->id);
        $this->assertSame($primarySecond->id, $allocations[$cart->items[1]->id][0]['inventory']->id);
    }

    public function test_order_splits_fulfillment_by_priority_when_no_single_warehouse_can_cover_all_lines(): void
    {
        $primary = Warehouse::primary();
        $primary->update(['online_fulfillment_enabled' => true, 'online_fulfillment_priority' => 10]);
        $secondary = $this->secondaryWarehouse();

        $first = $this->product('Producto A');
        $second = $this->product('Producto B');
        $inventory = app(InventoryService::class);

        $inventory->setOnHand($first, 2, reason: 'Test', warehouse: $primary);
        $inventory->setOnHand($second, 0, reason: 'Test', warehouse: $primary);
        $inventory->setOnHand($first, 0, reason: 'Test', warehouse: $secondary);
        $inventory->setOnHand($second, 2, reason: 'Test', warehouse: $secondary);

        $cart = $this->cartWith([$first, $second]);
        $allocations = app(OnlineWarehouseFulfillmentService::class)->allocateOrder($cart->items);

        $this->assertSame($primary->id, $allocations[$cart->items[0]->id][0]['inventory']->warehouse_id);
        $this->assertSame($secondary->id, $allocations[$cart->items[1]->id][0]['inventory']->warehouse_id);
    }

    public function test_online_availability_excludes_warehouses_not_enabled_for_web(): void
    {
        $primary = Warehouse::primary();
        $primary->update(['online_fulfillment_enabled' => true]);
        $disabled = $this->secondaryWarehouse(['online_fulfillment_enabled' => false]);
        $product = $this->product('Producto con stock');
        $inventory = app(InventoryService::class);

        $inventory->setOnHand($product, 3, reason: 'Test', warehouse: $primary);
        $inventory->setOnHand($product, 20, reason: 'Test', warehouse: $disabled);

        $this->assertSame(3, app(OnlineWarehouseFulfillmentService::class)->availableQuantity($product));
    }
}
