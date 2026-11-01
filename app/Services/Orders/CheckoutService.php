<?php

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Payments\PaymentDriverManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private readonly PaymentDriverManager $drivers) {}

    public function placeOrder(Cart $cart, array $customer, PaymentMethod $method, array $paymentData, string $publicToken): Order
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Tu carrito está vacío.']);
        }

        return DB::transaction(function () use ($cart, $customer, $method, $paymentData, $publicToken): Order {
            $lines = [];
            $subtotal = 0;

            foreach ($cart->items as $cartItem) {
                $product = Product::query()->published()->lockForUpdate()->find($cartItem->product_id);

                if (! $product) {
                    throw ValidationException::withMessages(['cart' => 'Uno de los productos ya no está disponible.']);
                }

                $variant = $cartItem->product_variant_id
                    ? ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->find($cartItem->product_variant_id)
                    : null;

                if ($cartItem->product_variant_id && ! $variant) {
                    throw ValidationException::withMessages(['cart' => 'Una variante del carrito ya no está disponible.']);
                }

                $inventory = Inventory::query()
                    ->when($variant, fn ($query) => $query->where('product_variant_id', $variant->id))
                    ->when(! $variant, fn ($query) => $query->where('product_id', $product->id)->whereNull('product_variant_id'))
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $cartItem->quantity > $inventory->available_quantity) {
                    throw ValidationException::withMessages(['cart' => "No hay existencias suficientes de {$product->name}."]);
                }

                $price = $variant?->price_amount ?? $product->price_amount;
                $lineTotal = $price * $cartItem->quantity;
                $subtotal += $lineTotal;

                $lines[] = [
                    'product_id' => $product->id,
                    'inventory_id' => $inventory?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $cartItem->quantity,
                    'unit_price_amount' => $price,
                    'line_total_amount' => $lineTotal,
                    'currency' => $product->currency,
                ];
            }

            $order = Order::query()->create([
                'number' => 'DS-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'public_token_hash' => hash('sha256', $publicToken),
                'user_id' => auth()->id(),
                ...$customer,
                'currency' => $cart->currency,
                'subtotal_amount' => $subtotal,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'status' => Order::STATUS_PENDING,
                'payment_status' => 'awaiting_payment',
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);

                if ($line['inventory_id']) {
                    $inventory = Inventory::query()->lockForUpdate()->findOrFail($line['inventory_id']);
                    $inventory->increment('quantity_reserved', $line['quantity']);
                    InventoryMovement::query()->create([
                        'inventory_id' => $inventory->id,
                        'user_id' => auth()->id(),
                        'type' => 'reservation',
                        'quantity_delta' => -$line['quantity'],
                        'reason' => 'Reserva por pedido '.$order->number,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'metadata' => ['order_number' => $order->number],
                    ]);
                }
            }

            $payment = $this->drivers->driver($method->driver)->createPayment($order, $method, $paymentData);
            $payment->events()->create([
                'from_status' => null,
                'to_status' => $payment->status,
                'note' => 'Pago creado durante el checkout.',
            ]);
            $order->update(['payment_status' => match ($payment->status) {
                'awaiting_review' => 'awaiting_review',
                'pending_collection' => 'pending_collection',
                'paid' => 'paid',
                default => 'awaiting_payment',
            }]);
            $cart->items()->delete();

            return $order->load(['items', 'payments.method']);
        });
    }
}
