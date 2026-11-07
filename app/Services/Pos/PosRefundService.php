<?php

namespace App\Services\Pos;

use App\Models\Inventory;
use App\Models\InventoryLocationBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\PosRefund;
use App\Models\PosRefundItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosRefundService
{
    public function refund(
        Order $order,
        PosCashSession $session,
        int $cashierId,
        array $lines,
        string $reason,
        int $approverId,
    ): PosRefund {
        if ($lines === [] || trim($reason) === '') {
            throw ValidationException::withMessages(['items' => 'Selecciona artículos y escribe el motivo de la devolución.']);
        }

        return DB::transaction(function () use ($order, $session, $cashierId, $lines, $reason, $approverId): PosRefund {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedSession = PosCashSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($lockedOrder->channel !== 'pos' || ! in_array($lockedOrder->payment_status, ['paid', Payment::STATUS_PARTIALLY_REFUNDED], true)) {
                throw ValidationException::withMessages(['order' => 'Solo se pueden devolver ventas POS cobradas o parcialmente reembolsadas.']);
            }

            $lockedSession->load('terminal');

            if (! User::query()->findOrFail($cashierId)->hasWarehousePermission($lockedSession->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para devolver ventas de este almacén.');
            }

            if (! $lockedSession->isOpen()
                || $lockedSession->pos_terminal_id !== $lockedOrder->pos_terminal_id
                || $lockedSession->currency !== $lockedOrder->currency) {
                throw ValidationException::withMessages(['session' => 'La devolución requiere una caja abierta del mismo terminal y moneda de la venta.']);
            }

            $payment = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_PARTIALLY_REFUNDED])
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->driver !== 'cash_on_delivery') {
                throw ValidationException::withMessages(['payment' => 'No existe un cobro POS en efectivo válido para reembolsar.']);
            }

            $quantities = [];
            foreach ($lines as $index => $line) {
                $itemId = (int) ($line['order_item_id'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($itemId < 1 || $quantity < 1) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Cada artículo debe tener una cantidad positiva.']);
                }
                $quantities[$itemId] = ($quantities[$itemId] ?? 0) + $quantity;
            }

            $prepared = [];
            $refundAmount = 0;
            foreach ($quantities as $itemId => $quantity) {
                $item = OrderItem::query()->where('order_id', $lockedOrder->id)->whereKey($itemId)->lockForUpdate()->first();
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Uno de los artículos no pertenece a esta venta.']);
                }

                $alreadyReturned = (int) PosRefundItem::query()->where('order_item_id', $item->id)->sum('quantity');
                if ($alreadyReturned + $quantity > $item->quantity) {
                    throw ValidationException::withMessages(['items' => "La cantidad a devolver supera las unidades vendidas de {$item->product_name}."]);
                }

                $amount = (int) $item->unit_price_amount * $quantity;
                $refundAmount += $amount;
                $prepared[] = ['item' => $item, 'quantity' => $quantity, 'amount' => $amount, 'already_returned' => $alreadyReturned];
            }

            $alreadyRefundedAmount = (int) PosRefund::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'completed')
                ->sum('amount');

            if ($refundAmount <= 0 || $alreadyRefundedAmount + $refundAmount > $payment->amount) {
                throw ValidationException::withMessages(['amount' => 'El importe acumulado de las devoluciones no puede superar el pago original.']);
            }

            $refund = PosRefund::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $payment->id,
                'pos_cash_session_id' => $lockedSession->id,
                'processed_by' => $cashierId,
                'amount' => $refundAmount,
                'currency' => $lockedOrder->currency,
                'status' => 'completed',
                'reason' => trim($reason),
                'refunded_at' => now(),
            ]);

            foreach ($prepared as $entry) {
                /** @var OrderItem $item */
                $item = $entry['item'];
                $quantity = $entry['quantity'];
                $allocations = [];

                if ($item->inventory_id !== null) {
                    $inventory = Inventory::query()->lockForUpdate()->findOrFail($item->inventory_id);
                    $saleMovement = InventoryMovement::query()
                        ->where('inventory_id', $inventory->id)
                        ->where('reference_type', Order::class)
                        ->where('reference_id', $lockedOrder->id)
                        ->where('type', 'sale')
                        ->where('metadata->order_item_id', $item->id)
                        ->first();

                    $originalAllocations = $saleMovement?->metadata['location_allocations'] ?? [];
                    if ($originalAllocations === []) {
                        throw ValidationException::withMessages(['inventory' => 'No se encontró la distribución original del stock. No se puede devolver esta línea con seguridad.']);
                    }

                    $skip = (int) $entry['already_returned'];
                    $remaining = $quantity;

                    foreach ($originalAllocations as $allocation) {
                        $segmentQuantity = (int) ($allocation['quantity'] ?? 0);
                        if ($skip >= $segmentQuantity) {
                            $skip -= $segmentQuantity;
                            continue;
                        }

                        $restorable = $segmentQuantity - $skip;
                        $restore = min($remaining, $restorable);
                        if ($restore <= 0) {
                            $skip = 0;
                            continue;
                        }

                        $locationId = (int) $allocation['location_id'];
                        $balance = InventoryLocationBalance::query()
                            ->where('inventory_id', $inventory->id)
                            ->where('warehouse_location_id', $locationId)
                            ->lockForUpdate()
                            ->first();

                        if (! $balance) {
                            $balance = InventoryLocationBalance::query()->create([
                                'inventory_id' => $inventory->id,
                                'warehouse_location_id' => $locationId,
                                'quantity_on_hand' => 0,
                            ]);
                        }

                        $balance->increment('quantity_on_hand', $restore);
                        $allocations[] = ['location_id' => $locationId, 'quantity' => $restore];
                        $remaining -= $restore;
                        $skip = 0;

                        if ($remaining === 0) {
                            break;
                        }
                    }

                    if ($remaining > 0) {
                        throw ValidationException::withMessages(['inventory' => 'La cantidad devuelta no coincide con la distribución original del inventario.']);
                    }

                    $inventory->increment('quantity_on_hand', $quantity);
                    InventoryMovement::query()->create([
                        'inventory_id' => $inventory->id,
                        'user_id' => $cashierId,
                        'type' => 'return',
                        'quantity_delta' => $quantity,
                        'to_location_id' => count($allocations) === 1 ? $allocations[0]['location_id'] : null,
                        'reason' => 'Devolución '.$refund->id.' de la venta '.$lockedOrder->number,
                        'reference_type' => PosRefund::class,
                        'reference_id' => $refund->id,
                        'metadata' => [
                            'order_id' => $lockedOrder->id,
                            'order_item_id' => $item->id,
                            'warehouse_id' => $lockedOrder->pos_warehouse_id,
                            'location_allocations' => $allocations,
                        ],
                    ]);
                }

                PosRefundItem::query()->create([
                    'pos_refund_id' => $refund->id,
                    'order_item_id' => $item->id,
                    'quantity' => $quantity,
                    'amount' => $entry['amount'],
                ]);
            }

            $newPaymentStatus = $alreadyRefundedAmount + $refundAmount === (int) $payment->amount
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED;

            $previousPaymentStatus = $payment->status;
            $payment->update([
                'status' => $newPaymentStatus,
                'reviewed_by' => $cashierId,
                'reviewed_at' => now(),
                'review_note' => 'Reembolso POS '.$refund->id.': '.trim($reason),
            ]);

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'from_status' => $previousPaymentStatus,
                'to_status' => $newPaymentStatus,
                'actor_id' => $cashierId,
                'note' => 'Reembolso POS '.$refund->id.': '.trim($reason),
                'metadata' => ['refund_id' => $refund->id, 'amount' => $refundAmount, 'cash_session_id' => $lockedSession->id],
            ]);

            $lockedOrder->update(['payment_status' => $newPaymentStatus]);

            $lockedSession->movements()->create([
                'payment_id' => $payment->id,
                'pos_refund_id' => $refund->id,
                'user_id' => $cashierId,
                'type' => PosCashMovement::TYPE_REFUND,
                'amount' => $refundAmount,
                'currency' => $lockedOrder->currency,
                'reference' => (string) $refund->id,
                'note' => trim($reason),
            ]);

            return $refund->load(['items.orderItem', 'payment', 'session', 'processor']);
        });
    }
}
