<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\OnlineWarehouseFulfillmentService;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_pos_sale_can_be_linked_to_a_registered_customer_account(): void
    {
        $cashier = User::factory()->create();
        $customer = User::factory()->create(['name' => 'Cliente Registrado', 'email' => 'cliente-pos@example.com', 'is_admin' => false, 'admin_access_enabled' => false]);
        $session = $this->openSession($cashier);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        $order = app(PosSaleService::class)->sell(
            $session,
            $cashier->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
            ['user_id' => $customer->id, 'name' => 'Nombre que no debe sustituir la cuenta', 'phone' => '555-0100'],
        );

        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('Cliente Registrado', $order->customer_name);
        $this->assertSame('cliente-pos@example.com', $order->customer_email);
        $this->assertSame($customer->id, $order->fresh()->user->id);
    }

    public function test_pos_sale_rejects_linking_an_administrator_as_a_customer(): void
    {
        $cashier = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $session = $this->openSession($cashier);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        try {
            app(PosSaleService::class)->sell(
                $session,
                $cashier->id,
                [['product_id' => $product->id, 'quantity' => 1]],
                $this->cashMethod()->id,
                ['user_id' => $admin->id],
            );
            $this->fail('Expected invalid customer account validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
        }
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

    public function test_pos_cash_payment_records_received_amount_and_change_without_inflating_cash_sales(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        $order = app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $this->cashMethod()->id,
            [],
            ['cash_received_amount' => 2000],
        );

        $payment = $order->payments()->firstOrFail();
        $this->assertSame(1250, $payment->amount);
        $this->assertSame(2000, $payment->metadata['cash_received_amount']);
        $this->assertSame(750, $payment->metadata['change_amount']);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_cash_session_id' => $session->id,
            'payment_id' => $payment->id,
            'type' => 'sale',
            'amount' => 1250,
        ]);
    }

    public function test_pos_cash_payment_rejects_tender_below_sale_total_without_creating_order(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        try {
            app(PosSaleService::class)->sell(
                $session,
                $user->id,
                [['product_id' => $product->id, 'quantity' => 1]],
                $this->cashMethod()->id,
                [],
                ['cash_received_amount' => 1000],
            );
            $this->fail('Expected insufficient cash tender validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_pos_zelle_sale_reserves_stock_until_payment_is_approved(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('USD');
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);
        $order = app(PosSaleService::class)->sell($session, $user->id, [['product_id' => $product->id, 'quantity' => 2]], $method->id, [], [
            'sender_name' => 'Cliente POS', 'reference' => 'ZELLE-POS-TEST',
            'transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf'),
        ]);
        $payment = $order->payments()->firstOrFail();
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame('awaiting_review', $order->payment_status);
        $this->assertSame(Payment::STATUS_AWAITING_REVIEW, $payment->status);
        $this->assertSame(2, $inventory->fresh()->quantity_reserved);
        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseMissing('pos_cash_movements', ['payment_id' => $payment->id]);
        Storage::disk('local')->assertExists($payment->proof_path);
        app(\App\Services\Payments\PaymentStatusService::class)->approve($payment, $user->id, 'Verificado');
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
    }

    public function test_rejected_pos_zelle_payment_releases_stock_and_cancels_order(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('USD');
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);
        $order = app(PosSaleService::class)->sell($session, $user->id, [['product_id' => $product->id, 'quantity' => 1]], $method->id, [], [
            'sender_name' => 'Cliente POS', 'reference' => 'ZELLE-POS-REJECT',
            'transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf'),
        ]);
        $payment = $order->payments()->firstOrFail();
        app(\App\Services\Payments\PaymentStatusService::class)->reject($payment, $user->id, 'No coincide la referencia');
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
    }

    public function test_pos_mixed_payment_records_cash_and_zelle_parts_and_reconciles_cash_only(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('USD');
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $cash = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $zelle = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $cash->update(['is_enabled' => true]);
        $zelle->update(['is_enabled' => true]);

        $order = app(PosSaleService::class)->sell($session, $user->id, [['product_id' => $product->id, 'quantity' => 1]], $cash->id, [], [
            'mixed' => true, 'cash_amount' => 500, 'zelle_amount' => 750, 'cash_received_amount' => 600,
            'sender_name' => 'Cliente mixto', 'reference' => 'ZELLE-MIX-001',
            'transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ]);

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'driver' => 'cash_on_delivery', 'amount' => 500, 'status' => Payment::STATUS_PAID]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'driver' => 'zelle_manual', 'amount' => 750, 'status' => Payment::STATUS_AWAITING_REVIEW]);
        $this->assertDatabaseHas('pos_cash_movements', ['pos_cash_session_id' => $session->id, 'type' => 'sale', 'amount' => 500]);
        $this->assertSame(1, $inventory->fresh()->quantity_reserved);
        app(\App\Services\Payments\PaymentStatusService::class)->approve($order->payments()->where('driver', 'zelle_manual')->firstOrFail(), $user->id, 'Confirmado');
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertSame(4, $inventory->fresh()->quantity_on_hand);
    }

    public function test_rejected_mixed_zelle_payment_refunds_cash_and_releases_inventory(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('USD');
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $cash = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $cash->update(['is_enabled' => true]);
        PaymentMethod::query()->where('driver', 'zelle_manual')->update(['is_enabled' => true]);

        $order = app(PosSaleService::class)->sell($session, $user->id, [['product_id' => $product->id, 'quantity' => 1]], $cash->id, [], [
            'mixed' => true, 'cash_amount' => 500, 'zelle_amount' => 750, 'cash_received_amount' => 500,
            'sender_name' => 'Cliente mixto', 'reference' => 'ZELLE-MIX-002',
            'transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ]);
        $zellePayment = $order->payments()->where('driver', 'zelle_manual')->firstOrFail();
        app(\App\Services\Payments\PaymentStatusService::class)->reject($zellePayment, $user->id, 'Transferencia no localizada');

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('pos_cash_movements', ['pos_cash_session_id' => $session->id, 'type' => 'refund', 'amount' => 500]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'driver' => 'cash_on_delivery', 'status' => Payment::STATUS_REFUNDED]);
    }

    public function test_pos_zelle_approval_is_idempotent_and_does_not_consume_stock_twice(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $session = $this->openSession($user, 'USD');
        $product = $this->product('USD');
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);
        $order = app(PosSaleService::class)->sell($session, $user->id, [['product_id' => $product->id, 'quantity' => 2]], $method->id, [], [
            'sender_name' => 'Cliente POS', 'reference' => 'ZELLE-IDEMPOTENT',
            'transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ]);
        $payment = $order->payments()->firstOrFail();
        $service = app(\App\Services\Payments\PaymentStatusService::class);
        $service->approve($payment, $user->id, 'Validado');
        try {
            $service->approve($payment, $user->id, 'Segundo intento');
            $this->fail('A repeated approval must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
            $this->assertSame(0, $inventory->fresh()->quantity_reserved);
            $this->assertDatabaseCount('inventory_movements', 3);
            $this->assertDatabaseHas('inventory_movements', ['reference_type' => Order::class, 'reference_id' => $order->id, 'type' => 'sale', 'quantity_delta' => -2]);
        }
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

    public function test_pos_sale_cannot_consume_units_reserved_by_online_checkout(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $inventory->update(['quantity_reserved' => 4]);

        $this->expectException(ValidationException::class);
        app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 2]],
            $this->cashMethod()->id,
        );
    }

    public function test_online_channel_sees_the_remaining_stock_after_a_pos_sale(): void
    {
        $user = User::factory()->create();
        $session = $this->openSession($user);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');

        app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 3]],
            $this->cashMethod()->id,
        );

        $this->assertSame(2, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(2, app(OnlineWarehouseFulfillmentService::class)->availableQuantity($product));
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
