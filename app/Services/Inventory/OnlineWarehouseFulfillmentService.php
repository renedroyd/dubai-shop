<?php

namespace App\\Services\\Inventory;

use App\\Models\\CartItem;
use App\\Models\\Inventory;
use App\\Models\\Product;
use App\\Models\\ProductVariant;
use App\\Models\\Warehouse;
use Illuminate\\Database\\Eloquent\\Collection;
use Illuminate\\Validation\\ValidationException;

class OnlineWarehouseFulfillmentService
{
    /**
     * Return online-eligible warehouses in fulfillment priority order.
     */
    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->active()
            ->where('online_fulfillment_enabled', true)
            ->orderBy('online_fulfillment_priority')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
    }

    public function availableQuantity(Product $product, ?ProductVariant $variant = null): ?int
    {
        $warehouses = $this->warehouses();

        if ($warehouses->isEmpty()) {
            return null;
        }

        $inventories = $this->inventoriesFor($product, $variant, $warehouses->modelKeys());

        if ($inventories->isEmpty()) {
            return null;
        }

        return (int) $inventories->sum(fn (Inventory $inventory): int => $inventory->available_quantity);
    }

    /**
     * Allocate each tracked cart line. Prefer one warehouse for the complete order;
     * if none can fulfill every line, fall back to priority-ordered split fulfillment.
     *
     * @param  iterable<CartItem>  $items
     * @return array<int, array<int, array{inventory: Inventory, quantity: int}>>
     */
    public function allocateOrder(iterable $items): array
    {
        $warehouses = $this->warehouses();

        if ($warehouses->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'No hay almacenes habilitados para preparar pedidos de la tienda online.',
            ]);
        }

        $lines = [];
        foreach ($items as $item) {
            $product = Product::query()->findOrFail($item->product_id);
            $variant = $item->product_variant_id
                ? ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->find($item->product_variant_id)
                : null;

            if ($item->product_variant_id && ! $variant) {
                throw ValidationException::withMessages(['cart' => 'Una variante del carrito ya no está disponible.']);
            }

            $inventories = $this->inventoriesFor($product, $variant, $warehouses->modelKeys(), lock: true);
            $lines[$item->id] = [
                'quantity' => (int) $item->quantity,
                'inventories' => $inventories->keyBy('warehouse_id'),
            ];
        }

        // Keep every line in one warehouse whenever a single eligible warehouse can cover the order.
        foreach ($warehouses as $warehouse) {
            $canFulfill = true;
            foreach ($lines as $line) {
                $inventory = $line['inventories']->get($warehouse->id);
                if (! $inventory || $inventory->available_quantity < $line['quantity']) {
                    $canFulfill = false;
                    break;
                }
            }

            if ($canFulfill) {
                $allocations = [];
                foreach ($lines as $itemId => $line) {
                    $allocations[$itemId] = [[
                        'inventory' => $line['inventories']->get($warehouse->id),
                        'quantity' => $line['quantity'],
                    ]];
                }

                return $allocations;
            }
        }

        // No single warehouse can cover the whole basket: allocate line-by-line by priority.
        $allocations = [];
        foreach ($lines as $itemId => $line) {
            $remaining = $line['quantity'];
            $allocations[$itemId] = [];

            foreach ($warehouses as $warehouse) {
                $inventory = $line['inventories']->get($warehouse->id);
                if (! $inventory || $inventory->available_quantity <= 0) {
                    continue;
                }

                $quantity = min($remaining, $inventory->available_quantity);
                if ($quantity > 0) {
                    $allocations[$itemId][] = ['inventory' => $inventory, 'quantity' => $quantity];
                    $remaining -= $quantity;
                }

                if ($remaining === 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'cart' => 'No hay existencias suficientes para completar el pedido en los almacenes habilitados para la tienda online.',
                ]);
            }
        }

        return $allocations;
    }

    private function inventoriesFor(
        Product $product,
        ?ProductVariant $variant,
        array $warehouseIds,
        bool $lock = false,
    ): Collection {
        $query = Inventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('product_id', $product->id)
            ->when(
                $variant,
                fn ($builder) => $builder->where('product_variant_id', $variant->id),
                fn ($builder) => $builder->whereNull('product_variant_id'),
            )
            ->orderBy('warehouse_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $inventories = $query->get();

        // Preserve the existing behavior: use product-level stock when no variant-specific stock is configured.
        if ($variant && $inventories->isEmpty()) {
            $fallback = Inventory::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->orderBy('warehouse_id');

            if ($lock) {
                $fallback->lockForUpdate();
            }

            $inventories = $fallback->get();
        }

        return $inventories;
    }
}
