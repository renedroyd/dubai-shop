<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\PosExchange;
use App\Models\PosRefund;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosExchangeService
{
    public function link(
        Order $originalOrder,
        Order $replacementOrder,
        PosRefund $refund,
        int $userId,
        string $reason,
    ): PosExchange {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indica el motivo del cambio.']);
        }

        return DB::transaction(function () use ($originalOrder, $replacementOrder, $refund, $userId, $reason): PosExchange {
            $orders = Order::query()
                ->whereIn('id', [$originalOrder->id, $replacementOrder->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $original = $orders->get($originalOrder->id);
            $replacement = $orders->get($replacementOrder->id);

            if (! $original || ! $replacement || $original->id === $replacement->id) {
                throw ValidationException::withMessages(['orders' => 'Selecciona dos ventas POS diferentes y válidas.']);
            }

            if ($original->channel !== 'pos' || $replacement->channel !== 'pos') {
                throw ValidationException::withMessages(['orders' => 'Los cambios solo pueden vincular ventas realizadas en el POS.']);
            }

            if (! in_array($original->payment_status, ['partially_refunded', 'refunded'], true)
                || $replacement->payment_status !== 'paid') {
                throw ValidationException::withMessages(['orders' => 'La venta original debe tener una devolución completada y la venta de reemplazo debe estar cobrada.']);
            }

            if ($original->currency !== $replacement->currency
                || (int) $original->pos_warehouse_id !== (int) $replacement->pos_warehouse_id) {
                throw ValidationException::withMessages(['orders' => 'La venta original y la de reemplazo deben pertenecer al mismo almacén y moneda.']);
            }

            $operator = User::query()->findOrFail($userId);
            if (! $operator->hasAdminPermission('pos_exchanges.create')
                || ! $operator->hasWarehousePermission($original->pos_warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para registrar cambios POS en este almacén.');
            }

            $lockedRefund = PosRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedRefund->order_id !== (int) $original->id || $lockedRefund->status !== 'completed') {
                throw ValidationException::withMessages(['refund' => 'La devolución seleccionada no pertenece a la venta original o no está completada.']);
            }

            if (PosExchange::query()->where('pos_refund_id', $lockedRefund->id)->exists()) {
                throw ValidationException::withMessages(['refund' => 'Esta devolución ya está vinculada a un cambio.']);
            }

            if (PosExchange::query()->where('replacement_order_id', $replacement->id)->exists()) {
                throw ValidationException::withMessages(['replacement_order_id' => 'Esta venta de reemplazo ya pertenece a otro cambio.']);
            }

            return PosExchange::query()->create([
                'original_order_id' => $original->id,
                'replacement_order_id' => $replacement->id,
                'pos_refund_id' => $lockedRefund->id,
                'processed_by' => $userId,
                'returned_amount' => $lockedRefund->amount,
                'replacement_amount' => $replacement->total_amount,
                'difference_amount' => (int) $replacement->total_amount - (int) $lockedRefund->amount,
                'currency' => $original->currency,
                'reason' => trim($reason),
                'exchanged_at' => now(),
            ]);
        });
    }
}
