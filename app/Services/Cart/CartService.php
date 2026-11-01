<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
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

            if ($cart->items()->exists() && $cart->currency !== $currency) {
                throw ValidationException::withMessages([
                    'product_id' => 'No se pueden mezclar monedas diferentes en el mismo carrito.',
                ]);
            }

            $lineKey = $variant ? 'variant:'.$variant->id : 'product:'.$product->id;
            $item = $cart->items()->where('line_key', $lineKey)->lockForUpdate()->first();
            $newQuantity = $quantity + ($item?->quantity ?? 0);

            if ($newQuantity < 1 || $newQuantity > 99) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad debe estar entre 1 y 99.']);
            }

            $inventoryQuery = Inventory::query()->where('product_id', $product->id);
            $inventoryQuery = $variant
                ? $inventoryQuery->where('product_variant_id', $variant->id)
                : $inventoryQuery->whereNull('product_variant_id');
            $inventory = $inventoryQuery->lockForUpdate()->first();

            if ($inventory && $newQuantity > $inventory->available_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'No hay existencias suficientes para esa cantidad.',
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
            $inventoryQuery = Inventory::query()->where('product_id', $lockedItem->product_id);
            $inventoryQuery = $lockedItem->product_variant_id
                ? $inventoryQuery->where('product_variant_id', $lockedItem->product_variant_id)
                : $inventoryQuery->whereNull('product_variant_id');
            $inventory = $inventoryQuery->lockForUpdate()->first();

            if ($inventory && $quantity > $inventory->available_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'No hay existencias suficientes para esa cantidad.',
                ]);
            }

            $lockedItem->update(['quantity' => $quantity]);

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
