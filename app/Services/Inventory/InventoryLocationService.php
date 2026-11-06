<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryLocationBalance;
use App\Models\InventoryMovement;
use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLocationService
{
    public function adjust(
        Inventory $inventory,
        WarehouseLocation $location,
        int $quantity,
        int $userId,
        string $reason,
    ): InventoryLocationBalance {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => __('La existencia no puede ser negativa.')]);
        }

        return DB::transaction(function () use ($inventory, $location, $quantity, $userId, $reason): InventoryLocationBalance {
            $inventory = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $this->assertLocationBelongsToInventory($location, $inventory);

            $balance = $this->balanceFor($inventory, $location);
            $delta = $quantity - $balance->quantity_on_hand;
            $newTotal = $inventory->quantity_on_hand + $delta;

            if ($newTotal < ($inventory->quantity_reserved + $inventory->quantity_blocked)) {
                throw ValidationException::withMessages([
                    'quantity' => __('El ajuste dejaría el almacén por debajo de las unidades reservadas o bloqueadas.'),
                ]);
            }

            if ($delta !== 0) {
                $balance->update(['quantity_on_hand' => $quantity]);
                $inventory->update(['quantity_on_hand' => $newTotal]);

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'type' => 'location_adjustment',
                    'quantity_delta' => $delta,
                    'to_location_id' => $delta > 0 ? $location->id : null,
                    'from_location_id' => $delta < 0 ? $location->id : null,
                    'reason' => $reason,
                    'metadata' => [
                        'warehouse_id' => $inventory->warehouse_id,
                        'location_id' => $location->id,
                        'location_quantity_before' => $balance->quantity_on_hand - $delta,
                        'location_quantity_after' => $quantity,
                        'inventory_quantity_after' => $newTotal,
                    ],
                ]);
            }

            return $balance->refresh();
        });
    }

    public function transfer(
        Inventory $inventory,
        WarehouseLocation $from,
        WarehouseLocation $to,
        int $quantity,
        int $userId,
        string $reason,
    ): void {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => __('La cantidad a mover debe ser mayor que cero.')]);
        }

        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_location_id' => __('La ubicación de origen y destino deben ser diferentes.')]);
        }

        DB::transaction(function () use ($inventory, $from, $to, $quantity, $userId, $reason): void {
            $inventory = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $this->assertLocationBelongsToInventory($from, $inventory, allowUnassigned: true);
            $this->assertLocationBelongsToInventory($to, $inventory);

            $source = $this->balanceFor($inventory, $from);
            $destination = $this->balanceFor($inventory, $to);

            if ($source->quantity_on_hand < $quantity) {
                throw ValidationException::withMessages(['quantity' => __('La ubicación de origen no tiene suficientes unidades.')]);
            }

            $source->decrement('quantity_on_hand', $quantity);
            $destination->increment('quantity_on_hand', $quantity);

            InventoryMovement::query()->create([
                'inventory_id' => $inventory->id,
                'user_id' => $userId,
                'type' => 'location_transfer',
                'quantity_delta' => 0,
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'reason' => $reason,
                'metadata' => [
                    'warehouse_id' => $inventory->warehouse_id,
                    'quantity' => $quantity,
                    'from_location_code' => $from->code,
                    'to_location_code' => $to->code,
                ],
            ]);
        });
    }

    public function reconcile(Inventory $inventory): array
    {
        $inventory->refresh();
        $locationTotal = (int) InventoryLocationBalance::query()
            ->where('inventory_id', $inventory->id)
            ->sum('quantity_on_hand');

        return [
            'inventory_id' => $inventory->id,
            'warehouse_id' => $inventory->warehouse_id,
            'inventory_quantity' => $inventory->quantity_on_hand,
            'location_quantity' => $locationTotal,
            'difference' => $inventory->quantity_on_hand - $locationTotal,
            'is_balanced' => $inventory->quantity_on_hand === $locationTotal,
        ];
    }

    private function assertLocationBelongsToInventory(WarehouseLocation $location, Inventory $inventory, bool $allowUnassigned = false): void
    {
        if ((int) $location->warehouse_id !== (int) $inventory->warehouse_id) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => __('La ubicación debe pertenecer al mismo almacén que el inventario.'),
            ]);
        }

        if (! $location->is_active || (! $allowUnassigned && $location->type === WarehouseLocation::TYPE_UNASSIGNED)) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => __('Selecciona una ubicación física activa.'),
            ]);
        }

        if (! $inventory->warehouse?->is_active) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => __('No se puede operar en un almacén inactivo.'),
            ]);
        }
    }

    private function balanceFor(Inventory $inventory, WarehouseLocation $location): InventoryLocationBalance
    {
        return InventoryLocationBalance::query()->firstOrCreate(
            [
                'inventory_id' => $inventory->id,
                'warehouse_location_id' => $location->id,
            ],
            ['quantity_on_hand' => 0],
        );
    }
}
