<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Payments\PaymentStatusService;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosLoyaltyService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PosLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private function openSession(User $cashier, string $currency = 'CUP'): PosCashSession
    {
        $cashier->update(['admin_access_enabled' => true]);
        $warehouse = Warehouse::primary();
        $cashier->warehouseAccesses()->firstOrCreate(
            ['warehouse_id' => $warehouse->id],
            ['permissions' => WarehouseUserAccess::PERMISSIONS],
        );

        $location = PosLocation::query()->create(['code' => 'LOY-'.strtoupper(uniqid()), 'name' => 'Tienda fidelización', 'is_active' => true]);
        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'LOY-'.strtoupper(uniqid()),
            'name' => 'Caja fidelización',
            'is_active' => true,
        ]);

        return app(PosCashSessionService::class)->open($terminal, $cashier->id, $currency, 5000);
    }

    private function product(string $currency = 'CUP'): Product
    {
        return Product::query()->create([
            'name' => 'Producto fidelización',
            'slug' => 'producto-loyalty-'.uniqid(),
            'sku' => 'LOY-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => $currency,
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    public function test_paid_account_linked_pos_sale_awards_currency_specific_points_once(): void
    {
        $cashier = User::factory()->create();
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);
        $session = $this->openSession($cashier);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $cash = PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $cash->id,
            ['user_id' => $customer->id],
        );

        $entry = $customer->fresh()->orders()->whereKey($order->id)->firstOrFail();
        $this->assertSame('paid', $entry->payment_status);
        $this->assertDatabaseHas('pos_loyalty_entries', [
            'order_id' => $order->id,
            'type' => 'earn',
            'points_delta' => 12,
            'idempotency_key' => 'pos-order-earned:'.$order->id,
        ]);

        $loyalty = app(PosLoyaltyService::class);
        $loyalty->awardForPaidPosOrder($order);
        $this->assertDatabaseCount('pos_loyalty_entries', 1);
        $this->assertDatabaseHas('pos_loyalty_wallets', ['user_id' => $customer->id, 'currency' => 'CUP', 'points_balance' => 12]);
    }

    public function test_guest_pos_sale_does_not_award_loyalty_points(): void
    {
        $cashier = User::factory()->create();
        $session = $this->openSession($cashier);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $cash = PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();

        $order = app(PosSaleService::class)->sell($session, $cashier->id, [['product_id' => $product->id, 'quantity' => 1]], $cash->id);

        $this->assertNull($order->user_id);
        $this->assertDatabaseCount('pos_loyalty_entries', 0);
        $this->assertDatabaseCount('pos_loyalty_wallets', 0);
    }

    public function test_zelle_pos_sale_awards_points_only_after_payment_approval(): void
    {
        Storage::fake('local');
        $cashier = User::factory()->create();
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);
        $session = $this->openSession($cashier, 'USD');
        $product = $this->product('USD');
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $zelle = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $zelle->update(['is_enabled' => true]);

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $zelle->id,
            ['user_id' => $customer->id],
            [
                'sender_name' => 'Cliente fidelización',
                'reference' => 'LOY-ZELLE-'.uniqid(),
                'transfer_date' => now()->toDateString(),
                'proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ],
        );

        $this->assertSame('awaiting_review', $order->payment_status);
        $this->assertDatabaseCount('pos_loyalty_entries', 0);

        app(PaymentStatusService::class)->approve($order->payments()->firstOrFail(), $cashier->id, 'Transferencia validada');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('pos_loyalty_entries', [
            'order_id' => $order->id,
            'type' => 'earn',
            'points_delta' => 12,
        ]);
        $this->assertDatabaseHas('pos_loyalty_wallets', ['user_id' => $customer->id, 'currency' => 'USD', 'points_balance' => 12]);
    }
}
