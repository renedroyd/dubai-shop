<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryLocationBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

        if ($userId !== null && ! User::query()->findOrFail($userId)->hasWarehousePermission($warehouse->id, 'inventory.manage')) {
            throw new AuthorizationException('No tienes permiso para gestionar inventario en este almacén.');
        }

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

            $unassigned = WarehouseLocation::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('type', WarehouseLocation::TYPE_UNASSIGNED)
                ->firstOrFail();

            $locationBalance = InventoryLocationBalance::query()->firstOrCreate(
                [
                    'inventory_id' => $inventory->id,
                    'warehouse_location_id' => $unassigned->id,
                ],
                ['quantity_on_hand' => 0],
            );

            $delta = $quantity - $inventory->quantity_on_hand;

            if ($delta !== 0) {

                if (($locationBalance->quantity_on_hand + $delta) < 0) {
                    throw new InvalidArgumentException(
                        'No hay suficientes unidades sin ubicación para aplicar este ajuste. Ajusta el stock desde su ubicación física.'
                    );
                }

                $locationBalance->increment('quantity_on_hand', $delta);
                $inventory->update(['quantity_on_hand' => $quantity]);

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'adjustment',
                    'quantity_delta' => $delta,
                    'from_location_id' => $delta < 0 ? $unassigned->id : null,
                    'to_location_id' => $delta > 0 ? $unassigned->id : null,
                    'reason' => $reason,
                    'metadata' => [
                        'warehouse_id' => $warehouse->id,
                        'location_id' => $unassigned->id,
                    ],
                ]);
            }

            return $inventory->refresh();
        });
    }
}
