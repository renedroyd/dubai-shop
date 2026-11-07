<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PosCashSession;
use App\Models\PosDiscountEvent;
use App\Models\PosLocation;
use App\Models\PosPriceRule;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosPricingRulesTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(string $currency = 'CUP'): array
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $warehouse = Warehouse::primary();
        $user->warehouseAccesses()->create([
            'warehouse_id' => $warehouse->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);
        $location = PosLocation::query()->create([
            'code' => 'PRICE-'.strtoupper(uniqid()),
            'name' => 'Tienda de pruebas',
            'is_active' => true,
        ]);
        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'PRICE-'.strtoupper(uniqid()),
            'name' => 'Caja de pruebas',
            'is_active' => true,
        ]);

        $session = app(PosCashSessionService::class)->open($terminal, $user->id, $currency, 5000);

        return [$user, $session, $warehouse];
    }

    private function product(string $currency = 'CUP'): Product
    {
        return Product::query()->create([
            'name' => 'Producto promoción',
            'slug' => 'producto-promocion-'.uniqid(),
            'sku' => 'PROMO-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => $currency,
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function inventory(Product $product, int $cost = 500, ?int $minimum = null): Inventory
    {
        $inventory = app(InventoryService::class)->setOnHand($product, 10, reason: 'Carga inicial');
        $inventory->update([
            'acquisition_cost_amount' => $cost,
            'minimum_sale_price_amount' => $minimum,
        ]);

        return $inventory->fresh();
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();
    }

    private function productRule(Product $product, int $value, string $type = PosPriceRule::TYPE_PERCENTAGE, ?int $warehouseId = null, ?int $customerId = null): PosPriceRule
    {
        return PosPriceRule::query()->create([
            'name' => 'Promoción de prueba',
            'scope' => PosPriceRule::SCOPE_PRODUCT,
            'target_id' => $product->id,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'currency' => $product->currency,
            'rule_type' => $type,
            'value_amount' => $value,
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    public function test_product_percentage_discount_is_applied_to_pos_total_and_audited(): void
    {
        [$cashier, $session, $warehouse] = $this->cashier();
        $product = $this->product();
        $inventory = $this->inventory($product);
        $rule = $this->productRule($product, 1000, warehouseId: $warehouse->id);

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );

        $this->assertSame(1250, $order->subtotal_amount);
        $this->assertSame(125, $order->discount_amount);
        $this->assertSame(1125, $order->total_amount);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 1125, 'status' => Payment::STATUS_PAID]);
        $this->assertDatabaseHas('pos_cash_movements', ['pos_cash_session_id' => $session->id, 'amount' => 1125]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'original_unit_price_amount' => 1250,
            'unit_price_amount' => 1125,
            'line_total_amount' => 1125,
            'discount_amount' => 125,
            'unit_cost_amount' => 500,
            'pos_price_rule_id' => $rule->id,
        ]);
        $this->assertDatabaseHas('pos_discount_events', [
            'order_id' => $order->id,
            'order_item_id' => $order->items()->value('id'),
            'applied_by' => $cashier->id,
            'discount_amount' => 125,
        ]);
        $this->assertSame(9, $inventory->fresh()->quantity_on_hand);
    }

    public function test_fixed_order_discount_is_applied_once_and_logged_without_changing_item_unit_price(): void
    {
        [$cashier, $session, $warehouse] = $this->cashier();
        $product = $this->product();
        $this->inventory($product);
        PosPriceRule::query()->create([
            'name' => 'Descuento de venta',
            'scope' => PosPriceRule::SCOPE_ORDER,
            'warehouse_id' => $warehouse->id,
            'currency' => 'CUP',
            'rule_type' => PosPriceRule::TYPE_FIXED,
            'value_amount' => 100,
            'priority' => 10,
            'is_active' => true,
        ]);

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );

        $this->assertSame(100, $order->discount_amount);
        $this->assertSame(1150, $order->total_amount);
        $this->assertSame(1250, $order->items()->firstOrFail()->unit_price_amount);
        $this->assertDatabaseHas('pos_discount_events', [
            'order_id' => $order->id,
            'order_item_id' => null,
            'scope' => 'order',
            'discount_amount' => 100,
        ]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 1150]);
    }

    public function test_discount_below_acquisition_cost_is_rejected_without_override_permission(): void
    {
        [$cashier, $session, $warehouse] = $this->cashier();
        $product = $this->product();
        $this->inventory($product, cost: 800);
        $this->productRule($product, 5000, warehouseId: $warehouse->id);

        try {
            app(PosSaleService::class)->sell(
                $session,
                $cashier->id,
                [['product_id' => $product->id, 'quantity' => 1]],
                $this->cashMethod()->id,
            );
            $this->fail('Expected the minimum price guard to reject the sale.');
        } catch (ValidationException) {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, PosDiscountEvent::query()->count());
        }
    }

    public function test_discount_below_floor_requires_and_records_explicit_override_permission(): void
    {
        [$cashier, $session, $warehouse] = $this->cashier();
        $role = AdminRole::query()->create([
            'name' => 'Supervisor de descuentos',
            'slug' => 'discount-supervisor-test',
            'permissions' => ['pos_discounts.override_floor'],
        ]);
        $cashier->roles()->attach($role);
        $product = $this->product();
        $this->inventory($product, cost: 800);
        $this->productRule($product, 5000, warehouseId: $warehouse->id);

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
        );

        $this->assertSame(625, $order->total_amount);
        $this->assertDatabaseHas('pos_discount_events', [
            'order_id' => $order->id,
            'applied_by' => $cashier->id,
            'approved_by' => $cashier->id,
        ]);
    }

    public function test_rule_requires_supervisor_permission_when_configured(): void
    {
        [$cashier, $session, $warehouse] = $this->cashier();
        $product = $this->product();
        $this->inventory($product);
        $rule = $this->productRule($product, 500, warehouseId: $warehouse->id);
        $rule->update(['requires_approval' => true]);

        try {
            app(PosSaleService::class)->sell(
                $session,
                $cashier->id,
                [['product_id' => $product->id, 'quantity' => 1]],
                $this->cashMethod()->id,
            );
            $this->fail('Expected the supervisor approval permission to be required.');
        } catch (ValidationException) {
            $this->assertSame(0, Order::query()->count());
        }
    }
}
