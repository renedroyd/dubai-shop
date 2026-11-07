<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\OnlineWarehouseFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly OnlineWarehouseFulfillmentService $fulfillment) {}

    public function current(string $sessionId): Cart
    {
        return Cart::query()->firstOrCreate(
            ['session_id' => $sessionId],
            ['currency' => 'USD', 'expires_at' => now()->addDays(30)],
        )->load(['items.product.images', 'items.variant']);
    }

    public function add(string $sessionId, int $productId, ?int $variantId, int $quantity): Cart
    {
        return DB::transaction(function () use ($sessionId, $productId, $variantId, $quantity): Cart {
            $product = Product::query()->published()->lockForUpdate()->findOrFail($productId);
            $variant = null;

            if ($variantId) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($variantId);
            }

            $price = $variant?->price_amount ?? $product->price_amount;
            $currency = $product->currency;
            $cart = Cart::query()->firstOrCreate(
                ['session_id' => $sessionId],
                ['currency' => $currency, 'expires_at' => now()->addDays(30)],
            );
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            $hasItems = $cart->items()->exists();

            if ($hasItems && $cart->currency !== $currency) {
                throw ValidationException::withMessages([
                    'product_id' => 'No se pueden mezclar monedas diferentes en el mismo carrito.',
                ]);
            }

            if (! $hasItems && $cart->currency !== $currency) {
                $cart->update(['currency' => $currency]);
            }

            $lineKey = $variant ? 'variant:'.$variant->id : 'product:'.$product->id;
            $item = $cart->items()->where('line_key', $lineKey)->lockForUpdate()->first();
            $newQuantity = $quantity + ($item?->quantity ?? 0);

            if ($newQuantity < 1 || $newQuantity > 99) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad debe estar entre 1 y 99.']);
            }

            $available = $this->fulfillment->availableQuantity($product, $variant);

            if ($available !== null && $newQuantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'No hay existencias suficientes en los almacenes habilitados para la tienda online.',
                ]);
            }

            $cart->items()->updateOrCreate(
                ['line_key' => $lineKey],
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $newQuantity,
                    'unit_price_amount' => $price,
                    'currency' => $currency,
                ],
            );

            return $cart->load(['items.product.images', 'items.variant']);
        });
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): Cart
    {
        if ($item->cart_id !== $cart->id) {
            abort(404);
        }

        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe estar entre 1 y 99.']);
        }

        return DB::transaction(function () use ($cart, $item, $quantity): Cart {
            $lockedItem = CartItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->published()->lockForUpdate()->findOrFail($lockedItem->product_id);
            $variant = $lockedItem->product_variant_id
                ? ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->findOrFail($lockedItem->product_variant_id)
                : null;
            $available = $this->fulfillment->availableQuantity($product, $variant);

            if ($available !== null && $quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'No hay existencias suficientes en los almacenes habilitados para la tienda online.',
                ]);
            }

            $hasOtherItems = $cart->items()->where('id', '<>', $lockedItem->id)->exists();

            if ($hasOtherItems && $cart->currency !== $product->currency) {
                throw ValidationException::withMessages([
                    'quantity' => 'La moneda del producto cambió y no coincide con el resto del carrito.',
                ]);
            }

            if (! $hasOtherItems && $cart->currency !== $product->currency) {
                $cart->update(['currency' => $product->currency]);
            }

            $lockedItem->update([
                'quantity' => $quantity,
                'unit_price_amount' => $variant?->price_amount ?? $product->price_amount,
                'currency' => $product->currency,
            ]);

            return $cart->fresh()->load(['items.product.images', 'items.variant']);
        });
    }

    public function remove(Cart $cart, CartItem $item): Cart
    {
        abort_unless($item->cart_id === $cart->id, 404);
        $item->delete();

        return $cart->fresh()->load(['items.product.images', 'items.variant']);
    }
}
