<?php

namespace App\Services\Inventory;

use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OnlineWarehouseFulfillmentService
{
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
     * @param iterable<CartItem> $items
     * @return array<int, array<int, array{inventory: ?Inventory, quantity: int}>>
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
                'tracked' => $inventories->isNotEmpty(),
                'inventories' => $inventories->keyBy('warehouse_id'),
            ];
        }

        foreach ($warehouses as $warehouse) {
            $canFulfill = true;
            foreach ($lines as $line) {
                if (! $line['tracked']) {
                    continue;
                }

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
                        'inventory' => $line['tracked'] ? $line['inventories']->get($warehouse->id) : null,
                        'quantity' => $line['quantity'],
                    ]];
                }

                return $allocations;
            }
        }

        $allocations = [];
        foreach ($lines as $itemId => $line) {
            if (! $line['tracked']) {
                $allocations[$itemId] = [['inventory' => null, 'quantity' => $line['quantity']]];
                continue;
            }

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

    private function inventoriesFor(Product $product, ?ProductVariant $variant, array $warehouseIds, bool $lock = false): Collection
    {
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
