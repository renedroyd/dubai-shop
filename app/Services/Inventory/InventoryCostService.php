<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryCostChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCostService
{
    public function updateCosts(
        Inventory $inventory,
        User $actor,
        ?int $acquisitionCostAmount,
        ?int $minimumSalePriceAmount,
        string $reason,
    ): Inventory {
        abort_unless($actor->hasAdminPermission('inventory_costs.update'), 403);
        abort_unless($actor->hasWarehousePermission($inventory->warehouse_id, 'inventory.manage'), 403);

        if (($acquisitionCostAmount !== null && $acquisitionCostAmount < 0)
            || ($minimumSalePriceAmount !== null && $minimumSalePriceAmount < 0)) {
            throw ValidationException::withMessages(['acquisition_cost' => 'Los importes no pueden ser negativos.']);
        }

        if ($acquisitionCostAmount !== null && $minimumSalePriceAmount !== null
            && $minimumSalePriceAmount < $acquisitionCostAmount) {
            throw ValidationException::withMessages([
                'minimum_sale_price' => 'El precio mínimo no puede ser inferior al costo de adquisición.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo del cambio de costos.']);
        }

        return DB::transaction(function () use ($inventory, $actor, $acquisitionCostAmount, $minimumSalePriceAmount, $reason): Inventory {
            $locked = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if (! $actor->hasWarehousePermission($locked->warehouse_id, 'inventory.manage')) {
                abort(403);
            }

            InventoryCostChange::query()->create([
                'inventory_id' => $locked->id,
                'user_id' => $actor->id,
                'previous_acquisition_cost_amount' => $locked->acquisition_cost_amount,
                'new_acquisition_cost_amount' => $acquisitionCostAmount,
                'previous_minimum_sale_price_amount' => $locked->minimum_sale_price_amount,
                'new_minimum_sale_price_amount' => $minimumSalePriceAmount,
                'reason' => trim($reason),
            ]);

            $locked->update([
                'acquisition_cost_amount' => $acquisitionCostAmount,
                'minimum_sale_price_amount' => $minimumSalePriceAmount,
            ]);

            return $locked->refresh();
        });
    }
}
