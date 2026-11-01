<?php

namespace App\Services\Orders;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderInventoryService
{
    public function release(Order $order): void
    {
        $this->process($order, false);
    }

    public function fulfill(Order $order): void
    {
        $this->process($order, true);
    }

    private function process(Order $order, bool $fulfill): void
    {
        DB::transaction(function () use ($order, $fulfill): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->inventory_processed_at) {
                return;
            }

            foreach ($lockedOrder->items()->whereNotNull('inventory_id')->get() as $item) {
                $inventory = Inventory::query()->lockForUpdate()->findOrFail($item->inventory_id);

                if ($inventory->quantity_reserved < $item->quantity) {
                    throw ValidationException::withMessages([
                        'inventory' => 'La reserva de inventario del pedido no coincide con las existencias actuales.',
                    ]);
                }

                if ($fulfill && $inventory->quantity_on_hand < $item->quantity) {
                    throw ValidationException::withMessages([
                        'inventory' => 'No hay existencias suficientes para completar este pedido.',
                    ]);
                }

                $inventory->decrement('quantity_reserved', $item->quantity);

                if ($fulfill) {
                    $inventory->decrement('quantity_on_hand', $item->quantity);
                }

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => auth()->id(),
                    'type' => $fulfill ? 'sale' : 'reservation_release',
                    'quantity_delta' => $fulfill ? -$item->quantity : $item->quantity,
                    'reason' => ($fulfill ? 'Salida por entrega del pedido ' : 'Liberación de reserva del pedido ').$lockedOrder->number,
                    'reference_type' => Order::class,
                    'reference_id' => $lockedOrder->id,
                    'metadata' => ['order_number' => $lockedOrder->number],
                ]);
            }

            $lockedOrder->update(['inventory_processed_at' => now()]);
        });
    }
}
