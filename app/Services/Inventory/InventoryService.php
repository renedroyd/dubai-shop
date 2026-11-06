<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function setOnHand(
        Product $product,
        int $quantity,
        ?ProductVariant $variant = null,
        ?int $userId = null,
        ?string $reason = null,
        ?Warehouse $warehouse = null,
    ): Inventory {
        if ($quantity < 0) {
            throw new InvalidArgumentException('La existencia no puede ser negativa.');
        }

        if ($variant && $variant->product_id !== $product->id) {
            throw new InvalidArgumentException('La variante no pertenece al producto indicado.');
        }

        $warehouse ??= Warehouse::primary();

        if (! $warehouse->is_active) {
            throw new InvalidArgumentException('No se puede modificar el inventario de un almacén inactivo.');
        }

        return DB::transaction(function () use ($product, $variant, $quantity, $userId, $reason, $warehouse): Inventory {
            $key = $variant ? 'variant:'.$variant->id : 'product:'.$product->id;

            $inventory = Inventory::query()->firstOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'stock_key' => $key,
                ],
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'quantity_blocked' => 0,
                ],
            );

            $inventory = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            if ($quantity < ($inventory->quantity_reserved + $inventory->quantity_blocked)) {
                throw new InvalidArgumentException('La existencia no puede ser inferior a las unidades reservadas o bloqueadas.');
            }

            $delta = $quantity - $inventory->quantity_on_hand;

            if ($delta !== 0) {
                $inventory->update(['quantity_on_hand' => $quantity]);

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'adjustment',
                    'quantity_delta' => $delta,
                    'reason' => $reason,
                    'metadata' => ['warehouse_id' => $warehouse->id],
                ]);
            }

            return $inventory->refresh();
        });
    }
}
