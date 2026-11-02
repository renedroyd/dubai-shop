<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Inventory;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderInventoryService;
use App\Models\Product;
use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto de checkout',
            'slug' => 'producto-checkout',
            'sku' => 'CHECKOUT-001',
            'price_amount' => 2599,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function addProductToCart(Product $product): void
    {
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('cart.index'));
    }

    private function customerData(PaymentMethod $method): array
    {
        ShippingZone::query()->create([
            'province' => 'La Habana',
            'municipality' => 'Habana Vieja',
            'shipping_amount' => 4.50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        return [
            'customer_name' => 'Cliente de prueba',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '+13055550100',
            'shipping_address' => 'Calle 23 # 456',
            'shipping_city' => 'Habana Vieja',
            'shipping_locality' => 'Centro Habana',
            'shipping_region' => 'La Habana',
            'shipping_country' => 'CU',
            'payment_method_id' => $method->id,
        ];
    }

    public function test_cash_on_delivery_checkout_creates_order_and_pending_collection_payment(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $this->addProductToCart($product);
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();

        $response = $this->post(route('checkout.store'), $this->customerData($method));

        $response->assertRedirect();
        $order = Order::query()->with('payments')->firstOrFail();

        $this->assertSame(450, $order->shipping_amount);
        $this->assertSame(0, $order->tax_amount);
        $this->assertSame(5648, $order->total_amount);
        $this->assertSame('Habana Vieja · La Habana', $order->shipping_zone_label);
        $this->assertSame('pending_collection', $order->payment_status);
        $this->assertSame(Payment::STATUS_PENDING_COLLECTION, $order->payments->first()->status);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('cart_items', 0);
    }


    public function test_checkout_form_displays_cuba_delivery_fields_and_shipping_quote(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Entregas disponibles únicamente en Cuba.')
            ->assertSee('shipping-region', false)
            ->assertSee('shipping-city', false)
            ->assertSee('Calcular costo de envío')
            ->assertSee('checkout.shipping-quote', false);
    }

    public function test_checkout_shipping_quote_returns_configured_zone_and_amount(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        ShippingZone::query()->create([
            'province' => 'La Habana',
            'municipality' => 'Habana Vieja',
            'shipping_amount' => 4.50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->getJson(route('checkout.shipping-quote', [
            'shipping_region' => 'La Habana',
            'shipping_city' => 'Habana Vieja',
            'shipping_country' => 'CU',
        ]))->assertOk()->assertJson([
            'amount' => '4.50',
            'currency' => 'USD',
            'zone' => 'Habana Vieja · La Habana',
        ]);
    }

    public function test_checkout_rejects_delivery_outside_cuba(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();

        $this->post(route('checkout.store'), [
            ...$this->customerData($method),
            'shipping_country' => 'US',
        ])->assertSessionHasErrors('shipping_country');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_shipping_rate_uses_the_most_specific_matching_zone(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $data = $this->customerData($method);
        ShippingZone::query()->create([
            'province' => 'La Habana',
            'municipality' => 'Habana Vieja',
            'locality' => 'Centro Habana',
            'shipping_amount' => 2.25,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->post(route('checkout.store'), $data)->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame(225, $order->shipping_amount);
        $this->assertSame('Centro Habana · Habana Vieja · La Habana', $order->shipping_zone_label);
        $this->assertSame(5423, $order->total_amount);
    }

    public function test_checkout_rejects_an_address_without_a_configured_shipping_rate(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $data = $this->customerData($method);
        $data['shipping_region'] = 'Matanzas';
        $data['shipping_city'] = 'Matanzas';

        $this->post(route('checkout.store'), $data)->assertSessionHasErrors('shipping_city');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_zelle_checkout_requires_and_privately_stores_payment_proof(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $product = $this->product();
        $this->addProductToCart($product);
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);

        $data = $this->customerData($method);
        $this->post(route('checkout.store'), $data)->assertSessionHasErrors('payment_proof');

        $response = $this->post(route('checkout.store'), [
            ...$data,
            'payment_reference' => 'ZELLE-12345',
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $payment = Payment::query()->firstOrFail();
        $this->assertSame(Payment::STATUS_AWAITING_REVIEW, $payment->status);
        $this->assertSame('ZELLE-12345', $payment->reference);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame('awaiting_review', $payment->order->payment_status);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'from_status' => null,
            'to_status' => Payment::STATUS_AWAITING_REVIEW,
        ]);
    }

    public function test_order_confirmation_requires_the_secret_access_token(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $this->post(route('checkout.store'), $this->customerData($method));
        $order = Order::query()->firstOrFail();

        $token = 'invalid-token';
        $this->get(route('orders.show', ['order' => $order->number, 'token' => $token]))->assertNotFound();
    }

    public function test_checkout_is_not_available_when_cart_is_empty(): void
    {
        $this->withoutVite();
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
    }

    public function test_checkout_reserves_tracked_stock_until_delivery_or_cancellation(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Stock inicial');
        $this->addProductToCart($product);
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();

        $this->post(route('checkout.store'), $this->customerData($method));
        $order = Order::query()->firstOrFail();
        $this->assertSame(2, $inventory->fresh()->quantity_reserved);
        $this->assertSame(3, $inventory->fresh()->available_quantity);

        app(OrderInventoryService::class)->release($order);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertNotNull($order->fresh()->inventory_processed_at);
    }

    public function test_fulfilling_order_deducts_reserved_stock_once(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Stock inicial');
        $this->addProductToCart($product);
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $this->post(route('checkout.store'), $this->customerData($method));
        $order = Order::query()->firstOrFail();

        app(OrderInventoryService::class)->fulfill($order);
        app(OrderInventoryService::class)->fulfill($order->fresh());

        $this->assertSame(3, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'sale',
            'quantity_delta' => -2,
        ]);
    }


    public function test_order_confirmation_is_private_and_never_cached_or_indexed(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();

        $checkout = $this->post(route('checkout.store'), $this->customerData($method));
        $confirmation = $this->get($checkout->headers->get('Location'));

        $confirmation->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }


    public function test_zelle_approval_updates_order_and_records_the_reviewer_decision(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);

        $this->post(route('checkout.store'), [
            ...$this->customerData($method),
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ]);

        $payment = Payment::query()->firstOrFail();
        app(\App\Services\Payments\PaymentStatusService::class)->approve($payment, null, 'Transferencia verificada.');

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->payment_status);
        $this->assertSame(Order::STATUS_CONFIRMED, $payment->order->fresh()->status);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'from_status' => Payment::STATUS_AWAITING_REVIEW,
            'to_status' => Payment::STATUS_PAID,
            'note' => 'Transferencia verificada.',
        ]);
    }

    public function test_zelle_rejection_updates_payment_and_records_reason(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'zelle_manual')->firstOrFail();
        $method->update(['is_enabled' => true]);

        $this->post(route('checkout.store'), [
            ...$this->customerData($method),
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
        ]);

        $payment = Payment::query()->firstOrFail();
        app(\App\Services\Payments\PaymentStatusService::class)->reject($payment, null, 'No coincide el importe.');

        $this->assertSame(Payment::STATUS_REJECTED, $payment->fresh()->status);
        $this->assertSame('payment_rejected', $payment->order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'to_status' => Payment::STATUS_REJECTED,
            'note' => 'No coincide el importe.',
        ]);
    }

    public function test_cash_collection_confirmation_is_audited_and_cannot_be_repeated(): void
    {
        $this->withoutVite();
        $this->addProductToCart($this->product());
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->firstOrFail();
        $this->post(route('checkout.store'), $this->customerData($method));

        $payment = Payment::query()->firstOrFail();
        $service = app(\App\Services\Payments\PaymentStatusService::class);
        $service->confirmCollection($payment, null, 'Efectivo recibido.');
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'from_status' => Payment::STATUS_PENDING_COLLECTION,
            'to_status' => Payment::STATUS_PAID,
            'note' => 'Efectivo recibido.',
        ]);

        try {
            $service->confirmCollection($payment->fresh());
            $this->fail('No se debe permitir confirmar el mismo cobro dos veces.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertDatabaseCount('payment_events', 2);
        }
    }

}
