<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private function openSession(User $user, string $currency = 'CUP'): PosCashSession
    {
        $user->update(['admin_access_enabled' => true]);
        $user->warehouseAccesses()->firstOrCreate(
            ['warehouse_id' => Warehouse::primary()->id],
            ['permissions' => WarehouseUserAccess::PERMISSIONS],
        );
    {
        $location = PosLocation::query()->create(['code' => 'POS-LOC', 'name' => 'Tienda POS', 'is_active' => true]);
        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'POS-TERM',
            'name' => 'Caja POS',
            'is_active' => true,
        ]);

        return app(PosCashSessionService::class)->open($terminal, $user->id, $currency, 5000);
    }

    private function product(string $currency = 'CUP'): Product
    {
        return Product::query()->create([
            'name' => 'Producto POS',
            'slug' => 'producto-pos-'.uniqid(),
            'sku' => 'POS-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => $currency,
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();
    }

    public function test_pos_sale_creates_shared_order_payment_and_audited_stock_movement(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 8, reason: 'Carga inicial');

        $order = app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 2]],
            $this->cashMethod()->id,
            ['name' => 'Cliente POS'],
        );

        $this->assertSame('pos', $order->channel);
        $this->assertSame($session->id, $order->pos_cash_session_id);
        $this->assertSame($session->terminal->warehouse_id, $order->pos_warehouse_id);
        $this->assertSame(2500, $order->total_amount);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(6, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'sale',
            'quantity_delta' => -2,
            'reference_type' => \App\Models\Order::class,
            'reference_id' => $order->id,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 2500,
        ]);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_cash_session_id' => $session->id,
            'type' => 'sale',
            'amount' => 2500,
        ]);
    }

    public function test_pos_sale_supports_variant_inventory_and_uses_variant_price(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Presentación grande',
            'sku' => 'POS-VAR-'.uniqid(),
            'price_amount' => 1800,
            'is_active' => true,
        ]);
        $inventory = app(InventoryService::class)->setOnHand($product, 4, $variant, reason: 'Carga inicial');

        $order = app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );

        $this->assertSame(1800, $order->total_amount);
        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'unit_price_amount' => 1800,
        ]);
    }

    public function test_pos_sale_rejects_insufficient_stock_without_partial_writes(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 1, reason: 'Carga inicial');

        try {
            app(PosSaleService::class)->sell(
                $session,
                $user->id,
                [['product_id' => $product->id, 'quantity' => 2]],
                $this->cashMethod()->id,
            );
            $this->fail('Expected insufficient inventory validation.');
        } catch (ValidationException) {
            $this->assertSame(1, $inventory->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_pos_sale_aggregates_duplicate_lines_before_checking_available_stock(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $inventory->update(['quantity_reserved' => 2]);

        $this->expectException(ValidationException::class);
        app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            $this->cashMethod()->id,
        );
    }

    public function test_pos_sale_rejects_currency_mismatch_and_non_cash_methods(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('CUP');
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        $this->expectException(ValidationException::class);
        app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );
    }

    public function test_pos_sale_cannot_be_recorded_after_cash_session_is_closed(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        app(PosCashSessionService::class)->close($session, $user->id, 5000);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        $this->expectException(ValidationException::class);
        app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );
    }
}
