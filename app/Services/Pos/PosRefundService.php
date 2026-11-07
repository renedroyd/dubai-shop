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

            $payments = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                ->orderBy('id')->lockForUpdate()->get();

            if ($payments->isEmpty() || $payments->contains(fn (Payment $payment): bool => ! in_array($payment->driver, ['cash_on_delivery', 'zelle_manual'], true))) {
                throw ValidationException::withMessages(['payment' => 'No existen pagos originales válidos para reembolsar.']);
            }
            if ((int) $payments->sum('amount') !== (int) $lockedOrder->total_amount) {
                throw ValidationException::withMessages(['payment' => 'Los pagos originales no concilian con el total de la venta.']);
            }

            $cashier = User::query()->findOrFail($cashierId);
            if (! $cashier->hasAdminPermission('pos_refunds.process')) {
                throw new AuthorizationException('No tienes permiso para procesar devoluciones POS.');
            }
            $approver = User::query()->lockForUpdate()->findOrFail($approverId);
            if ($approver->id === $cashier->id || ! $approver->hasAdminPermission('pos_refunds.approve')) {
                throw new AuthorizationException('La devolución requiere la aprobación de otro usuario autorizado.');
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
            $saleLines = $lockedOrder->items()->orderBy('id')->get();
            $netLinesTotal = (int) $saleLines->sum('line_total_amount');
            $orderLevelDiscount = max(0, (int) $lockedOrder->discount_amount - (int) $saleLines->sum('discount_amount'));
            $remainingOrderDiscount = $orderLevelDiscount;
            $orderDiscountByItem = [];
            foreach ($saleLines as $position => $saleLine) {
                $allocatedDiscount = $position === $saleLines->count() - 1
                    ? $remainingOrderDiscount
                    : ($netLinesTotal > 0 ? intdiv($orderLevelDiscount * (int) $saleLine->line_total_amount, $netLinesTotal) : 0);
                $allocatedDiscount = min($remainingOrderDiscount, $allocatedDiscount);
                $orderDiscountByItem[$saleLine->id] = $allocatedDiscount;
                $remainingOrderDiscount -= $allocatedDiscount;
            }
            $saleLines = $lockedOrder->items()->orderBy('id')->get();
            $netLinesTotal = (int) $saleLines->sum('line_total_amount');
            $orderLevelDiscount = max(0, (int) $lockedOrder->discount_amount - (int) $saleLines->sum('discount_amount'));
            $remainingOrderDiscount = $orderLevelDiscount;
            $orderDiscountByItem = [];
            foreach ($saleLines as $position => $saleLine) {
                $allocatedDiscount = $position === $saleLines->count() - 1
                    ? $remainingOrderDiscount
                    : ($netLinesTotal > 0 ? intdiv($orderLevelDiscount * (int) $saleLine->line_total_amount, $netLinesTotal) : 0);
                $allocatedDiscount = min($remainingOrderDiscount, $allocatedDiscount);
                $orderDiscountByItem[$saleLine->id] = $allocatedDiscount;
                $remainingOrderDiscount -= $allocatedDiscount;
            }
            foreach ($quantities as $itemId => $quantity) {
                $item = OrderItem::query()->where('order_id', $lockedOrder->id)->whereKey($itemId)->lockForUpdate()->first();
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Uno de los artículos no pertenece a esta venta.']);
                }

                $alreadyReturned = (int) PosRefundItem::query()->where('order_item_id', $item->id)->sum('quantity');
                if ($alreadyReturned + $quantity > $item->quantity) {
                    throw ValidationException::withMessages(['items' => "La cantidad a devolver supera las unidades vendidas de {$item->product_name}."]);
                }

                $netItemAmount = max(0, (int) $item->line_total_amount - (int) ($orderDiscountByItem[$item->id] ?? 0));
                $amount = intdiv($netItemAmount * ($alreadyReturned + $quantity), (int) $item->quantity)
                    - intdiv($netItemAmount * $alreadyReturned, (int) $item->quantity);
                $refundAmount += $amount;
                $prepared[] = ['item' => $item, 'quantity' => $quantity, 'amount' => $amount, 'already_returned' => $alreadyReturned];
            }

            $alreadyRefundedAmount = (int) PosRefund::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', 'completed')
                ->sum('amount');
            $originalPaidAmount = (int) $payments->sum('amount');

            if ($refundAmount <= 0 || $alreadyRefundedAmount + $refundAmount > $originalPaidAmount) {
                throw ValidationException::withMessages(['amount' => 'El importe acumulado de las devoluciones no puede superar los pagos originales.']);
            }

            $paymentAllocations = [];
            $remainingRefund = $refundAmount;
            foreach ($payments as $index => $paymentPart) {
                $alreadyForPayment = (int) \App\Models\PosRefundPayment::query()->where('payment_id', $paymentPart->id)->sum('amount');
                $available = max(0, (int) $paymentPart->amount - $alreadyForPayment);
                $target = $index === $payments->count() - 1
                    ? $remainingRefund
                    : intdiv($refundAmount * (int) $paymentPart->amount, max(1, $originalPaidAmount));
                $allocated = min($available, $target, $remainingRefund);
                $paymentAllocations[] = ['payment' => $paymentPart, 'amount' => $allocated];
                $remainingRefund -= $allocated;
            }
            if ($remainingRefund > 0) {
                foreach ($paymentAllocations as $index => $allocation) {
                    $part = $allocation['payment'];
                    $alreadyForPayment = (int) \App\Models\PosRefundPayment::query()->where('payment_id', $part->id)->sum('amount');
                    $available = max(0, (int) $part->amount - $alreadyForPayment - $allocation['amount']);
                    $extra = min($available, $remainingRefund);
                    $paymentAllocations[$index]['amount'] += $extra;
                    $remainingRefund -= $extra;
                    if ($remainingRefund === 0) break;
                }
            }
            if ($remainingRefund > 0) {
                throw ValidationException::withMessages(['amount' => 'No se pudo distribuir el reembolso entre los pagos originales.']);
            }

            $refund = PosRefund::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $payments->first()->id,
                'pos_cash_session_id' => $lockedSession->id,
                'processed_by' => $cashierId,
                'approved_by' => $approver->id,
                'approved_at' => now(),
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

            $cashRefundAmount = 0;
            foreach ($paymentAllocations as $allocation) {
                $paymentPart = $allocation['payment'];
                $partAmount = (int) $allocation['amount'];
                if ($partAmount <= 0) continue;

                \App\Models\PosRefundPayment::query()->create([
                    'pos_refund_id' => $refund->id,
                    'payment_id' => $paymentPart->id,
                    'driver' => $paymentPart->driver,
                    'amount' => $partAmount,
                    'currency' => $paymentPart->currency,
                    'reference' => $paymentPart->driver === 'zelle_manual' ? $paymentPart->reference : null,
                ]);

                $alreadyForPayment = (int) \App\Models\PosRefundPayment::query()->where('payment_id', $paymentPart->id)->sum('amount');
                $newPaymentStatus = $alreadyForPayment >= (int) $paymentPart->amount
                    ? Payment::STATUS_REFUNDED
                    : Payment::STATUS_PARTIALLY_REFUNDED;
                $previousPaymentStatus = $paymentPart->status;
                $paymentPart->update(['status' => $newPaymentStatus, 'reviewed_by' => $approver->id, 'reviewed_at' => now()]);
                PaymentEvent::query()->create([
                    'payment_id' => $paymentPart->id,
                    'from_status' => $previousPaymentStatus,
                    'to_status' => $newPaymentStatus,
                    'actor_id' => $approver->id,
                    'note' => 'Reembolso POS '.$refund->id.' aprobado por '.$approver->name,
                    'metadata' => ['refund_id' => $refund->id, 'amount' => $partAmount, 'refund_driver' => $paymentPart->driver],
                ]);

                if ($paymentPart->driver === 'cash_on_delivery') {
                    $cashRefundAmount += $partAmount;
                    $lockedSession->movements()->create([
                        'payment_id' => $paymentPart->id,
                        'pos_refund_id' => $refund->id,
                        'user_id' => $cashierId,
                        'type' => PosCashMovement::TYPE_REFUND,
                        'amount' => $partAmount,
                        'currency' => $paymentPart->currency,
                        'reference' => (string) $refund->id,
                        'note' => trim($reason),
                        'metadata' => ['approved_by' => $approver->id, 'original_payment_id' => $paymentPart->id],
                    ]);
                }
            }

            $totalRefundedForOrder = (int) PosRefund::query()->where('order_id', $lockedOrder->id)->where('status', 'completed')->sum('amount');
            $lockedOrder->update(['payment_status' => $totalRefundedForOrder >= $originalPaidAmount
                ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED]);

            return $refund->load(['items.orderItem', 'payment', 'payments.payment', 'session', 'processor', 'approver']);
        });
    }
}
