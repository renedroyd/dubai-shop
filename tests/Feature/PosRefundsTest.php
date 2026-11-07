<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Payment;
use App\Models\PosRefund;
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

    private function authorizedApprover(User $cashier): User
    {
        $cashier->update(['admin_access_enabled' => true]);
        $processRole = AdminRole::query()->create(['name' => 'Procesador devoluciones', 'slug' => 'refund-process-'.uniqid(), 'permissions' => ['pos_refunds.process']]);
        $cashier->roles()->attach($processRole);
        $approver = User::factory()->create(['admin_access_enabled' => true]);
        $approveRole = AdminRole::query()->create(['name' => 'Autorizador devoluciones', 'slug' => 'refund-approve-'.uniqid(), 'permissions' => ['pos_refunds.approve']]);
        $approver->roles()->attach($approveRole);
        return $approver;
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
        $approver = $this->authorizedApprover($user);

        $partial = $service->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ], 'Producto devuelto en buen estado', $approver->id);

        $this->assertSame(1250, $partial->amount);
        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(Payment::STATUS_PARTIALLY_REFUNDED, $order->fresh()->payment_status);

        $full = $service->refund($order->fresh(), $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 2],
        ], 'Devolución del resto', $approver->id);

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

    public function test_mixed_refund_follows_original_payment_proportions_and_only_cash_hits_drawer(): void
    {
        $cashier = User::factory()->create();
        [$session, $order] = $this->completedSale($cashier);
        $cashPayment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $cashPayment->update(['amount' => 2500, 'currency' => 'USD']);
        $order->update(['currency' => 'USD']);
        $session->update(['currency' => 'USD']);
        $order->items()->update(['currency' => 'USD']);
        $zellePayment = Payment::query()->create([
            'order_id' => $order->id,
            'driver' => 'zelle_manual',
            'status' => Payment::STATUS_PAID,
            'amount' => 1250,
            'currency' => 'USD',
            'reference' => 'ZELLE-ORIGINAL-REF',
        ]);
        $item = $order->items()->firstOrFail();
        $approver = $this->authorizedApprover($cashier);

        $refund = app(PosRefundService::class)->refund($order, $session, $cashier->id, [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ], 'Devolución de pago mixto', $approver->id);

        $this->assertSame(1250, $refund->amount);
        $this->assertDatabaseHas('pos_refund_payments', ['pos_refund_id' => $refund->id, 'payment_id' => $cashPayment->id, 'driver' => 'cash_on_delivery', 'amount' => 833]);
        $this->assertDatabaseHas('pos_refund_payments', ['pos_refund_id' => $refund->id, 'payment_id' => $zellePayment->id, 'driver' => 'zelle_manual', 'amount' => 417]);
        $this->assertSame(833, (int) $session->movements()->where('type', 'refund')->sum('amount'));
    }
    public function test_quarantine_returns_are_blocked_and_discarded_returns_do_not_reenter_stock(): void
    {
        $cashier = User::factory()->create();
        [$session, $order, $inventory] = $this->completedSale($cashier);
        $item = $order->items()->firstOrFail();
        $approver = $this->authorizedApprover($cashier);

        $quarantineRefund = app(PosRefundService::class)->refund($order, $session, $cashier->id, [
            ['order_item_id' => $item->id, 'quantity' => 1, 'disposition' => 'quarantine', 'condition_note' => 'Empaque dañado'],
        ], 'Artículo recibido con daño', $approver->id);

        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(1, $inventory->fresh()->quantity_blocked);
        $this->assertSame(2, $inventory->fresh()->available_quantity);
        $this->assertDatabaseHas('pos_refund_items', ['pos_refund_id' => $quarantineRefund->id, 'disposition' => 'quarantine']);
        $this->assertDatabaseHas('warehouse_locations', ['warehouse_id' => $inventory->warehouse_id, 'code' => 'QUARANTINE', 'type' => 'quarantine']);

        $discardRefund = app(PosRefundService::class)->refund($order->fresh(), $session, $cashier->id, [
            ['order_item_id' => $item->id, 'quantity' => 1, 'disposition' => 'discarded', 'condition_note' => 'No recuperable'],
        ], 'Artículo no recuperable', $approver->id);

        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(1, $inventory->fresh()->quantity_blocked);
        $this->assertDatabaseHas('pos_refund_items', ['pos_refund_id' => $discardRefund->id, 'disposition' => 'discarded']);
        $this->assertDatabaseHas('inventory_movements', ['reference_type' => PosRefund::class, 'reference_id' => $discardRefund->id, 'type' => 'return_discarded', 'quantity_delta' => 0]);
    }
    public function test_refund_cannot_return_more_units_than_were_sold(): void
    {
        $user = User::factory()->create();
        [$session, $order] = $this->completedSale($user);
        $item = $order->items()->firstOrFail();
        $approver = $this->authorizedApprover($user);

        $this->expectException(ValidationException::class);
        app(PosRefundService::class)->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 4],
        ], 'Cantidad superior a la venta', $approver->id);
    }

    public function test_refund_requires_open_session_for_same_terminal_and_currency(): void
    {
        $user = User::factory()->create();
        [$session, $order] = $this->completedSale($user);
        app(PosCashSessionService::class)->close($session, $user->id, 13750);
        $approver = $this->authorizedApprover($user);
        $item = $order->items()->firstOrFail();

        $this->expectException(ValidationException::class);
        app(PosRefundService::class)->refund($order, $session, $user->id, [
            ['order_item_id' => $item->id, 'quantity' => 1],
        ], 'Caja cerrada', $approver->id);
    }
}
