<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
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
        return [
            'customer_name' => 'Cliente de prueba',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '+13055550100',
            'shipping_address' => '100 Main Street',
            'shipping_city' => 'Miami',
            'shipping_region' => 'Florida',
            'shipping_country' => 'US',
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

        $this->assertSame(5198, $order->total_amount);
        $this->assertSame('pending_collection', $order->payment_status);
        $this->assertSame(Payment::STATUS_PENDING_COLLECTION, $order->payments->first()->status);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('cart_items', 0);
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
}
