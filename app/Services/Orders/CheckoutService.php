<?php

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Payments\PaymentDriverManager;
use App\Services\Inventory\OnlineWarehouseFulfillmentService;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly PaymentDriverManager $drivers,
        private readonly ShippingRateService $shippingRates,
        private readonly OnlineWarehouseFulfillmentService $fulfillment,
    ) {}

    public function placeOrder(Cart $cart, array $customer, PaymentMethod $method, array $paymentData, string $publicToken): Order
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Tu carrito está vacío.']);
        }

        return DB::transaction(function () use ($cart, $customer, $method, $paymentData, $publicToken): Order {
            $allocations = $this->fulfillment->allocateOrder($cart->items);
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

                $price = $variant?->price_amount ?? $product->price_amount;

                foreach ($allocations[$cartItem->id] ?? [] as $allocation) {
                    $quantity = (int) $allocation['quantity'];
                    $inventory = $allocation['inventory'];
                    $lineTotal = $price * $quantity;
                    $subtotal += $lineTotal;

                    $lines[] = [
                        'product_id' => $product->id,
                        'inventory_id' => $inventory?->id,
                        'product_variant_id' => $variant?->id,
                        'product_name' => $product->name,
                        'variant_name' => $variant?->name,
                        'sku' => $variant?->sku ?? $product->sku,
                        'quantity' => $quantity,
                        'unit_price_amount' => $price,
                        'line_total_amount' => $lineTotal,
                        'currency' => $product->currency,
                    ];
                }
            }

            $zone = $this->shippingRates->resolve($customer, $cart->currency);
            $shippingAmount = $this->decimalToMinorUnits((string) $zone->shipping_amount);
            $taxAmount = 0;
            $placedAt = now();
            $deliveryCommitmentHours = 24;

            $order = Order::query()->create([
                'number' => 'DS-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'public_token_hash' => hash('sha256', $publicToken),
                'user_id' => auth()->id(),
                ...$customer,
                'shipping_zone_id' => $zone->id,
                'shipping_zone_label' => $zone->label(),
                'currency' => $cart->currency,
                'subtotal_amount' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $shippingAmount + $taxAmount,
                'delivery_commitment_hours' => $deliveryCommitmentHours,
                'delivery_due_at' => $placedAt->copy()->addHours($deliveryCommitmentHours),
                'status' => Order::STATUS_PENDING,
                'payment_status' => 'awaiting_payment',
                'placed_at' => $placedAt,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);

                if ($line['inventory_id']) {
                    $inventory = Inventory::query()->lockForUpdate()->findOrFail($line['inventory_id']);

                    if ($inventory->available_quantity < $line['quantity']) {
                        throw ValidationException::withMessages([
                            'cart' => 'Las existencias cambiaron mientras se procesaba el pedido. Actualiza el carrito e inténtalo de nuevo.',
                        ]);
                    }

                    $inventory->increment('quantity_reserved', $line['quantity']);
                    InventoryMovement::query()->create([
                        'inventory_id' => $inventory->id,
                        'user_id' => auth()->id(),
                        'type' => 'reservation',
                        'quantity_delta' => -$line['quantity'],
                        'reason' => 'Reserva por pedido '.$order->number,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'metadata' => [
                            'order_number' => $order->number,
                            'warehouse_id' => $inventory->warehouse_id,
                        ],
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

    private function decimalToMinorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
