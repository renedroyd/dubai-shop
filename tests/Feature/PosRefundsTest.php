<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Payment;
use App\Models\AdminRole;
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
use App\Services\Pos\PosRefundService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosRefundsTest extends TestCase
{
    use RefreshDatabase;

    private function openCashSession(User $user): PosCashSession
    {
        $user->update(['admin_access_enabled' => true]);
        $user->warehouseAccesses()->firstOrCreate(
            ['warehouse_id' => Warehouse::primary()->id],
            ['permissions' => WarehouseUserAccess::PERMISSIONS],
        );

        $location = PosLocation::query()->create(['code' => 'REFUND-LOC', 'name' => 'Tienda devoluciones', 'is_active' => true]);
        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'REFUND-POS',
            'name' => 'Caja devoluciones',
            'is_active' => true,
        ]);

        return app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 10000);
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto reembolsable',
            'slug' => 'producto-reembolsable-'.uniqid(),
            'sku' => 'REFUND-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();
    }

    private function completedSale(User $user): array
    {
        $session = $this->openCashSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $order = app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 3]],
            $this->cashMethod()->id,
        );

        return [$session, $order, $inventory];
    }

    public function test_partial_and_full_refunds_restore_inventory_and_reconcile_cash(): void
    {
        $user = User::factory()->create();
        [$session, $order, $inventory] = $this->completedSale($user);
        $item = $order->items()->firstOrFail();
        $service = app(PosRefundService::class);

        $partial = $service->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ], 'Producto devuelto en buen estado', $user->id);

        $this->assertSame(1250, $partial->amount);
        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(Payment::STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);

        $full = $service->refund($order->fresh(), $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 2],
        ], 'Devolución del resto', $user->id);

        $this->assertSame(2500, $full->amount);
        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(Payment::STATUS_REFUNDED, $order->fresh()->payment_status);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_cash_session_id' => $session->id,
            'type' => 'refund',
            'amount' => 1250,
        ]);
        $this->assertSame(3750, (int) $session->movements()->where('type', 'refund')->sum('amount'));
    }

    public function test_refund_cannot_return_more_units_than_were_sold(): void
    {
        $user = User::factory()->create();
        [$session, $order] = $this->completedSale($user);
        $item = $order->items()->firstOrFail();

        $this->expectException(ValidationException::class);
        app(PosRefundService::class)->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 4],
        ], 'Cantidad superior a la venta', $user->id);
    }

    public function test_refund_requires_open_session_for_same_terminal_and_currency(): void
    {
        $user = User::factory()->create();
        [$session, $order] = $this->completedSale($user);
        app(PosCashSessionService::class)->close($session, $user->id, 13750);
        $item = $order->items()->firstOrFail();

        $this->expectException(ValidationException::class);
        app(PosRefundService::class)->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ], 'Caja cerrada', $user->id);
    }
}
