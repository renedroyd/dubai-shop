<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentStatusService
{
    public function approve(Payment $payment, ?int $actorId = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $actorId, $note): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if ($lockedPayment->status !== Payment::STATUS_AWAITING_REVIEW || $order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Este pago ya no está disponible para aprobación.']);
            }

            if ($order->channel === 'pos') {
                app(OrderInventoryService::class)->fulfill($order);
            }
            $from = $lockedPayment->status;
            $lockedPayment->update([
                'status' => Payment::STATUS_PAID,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $order->update([
                'payment_status' => 'paid',
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
            $lockedPayment->events()->create([
                'from_status' => $from,
                'to_status' => Payment::STATUS_PAID,
                'actor_id' => $actorId,
                'note' => $note,
            ]);

            return $lockedPayment->fresh();
        });
    }

    public function reject(Payment $payment, ?int $actorId = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $actorId, $note): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if ($lockedPayment->status !== Payment::STATUS_AWAITING_REVIEW || $order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Este pago ya no está disponible para rechazo.']);
            }

            if ($order->channel === 'pos') {
                app(OrderInventoryService::class)->release($order);
                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
            }
            $from = $lockedPayment->status;
            $lockedPayment->update([
                'status' => Payment::STATUS_REJECTED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $order->update(['payment_status' => 'payment_rejected']);
            $lockedPayment->events()->create([
                'from_status' => $from,
                'to_status' => Payment::STATUS_REJECTED,
                'actor_id' => $actorId,
                'note' => $note,
            ]);

            return $lockedPayment->fresh();
        });
    }

    public function confirmCollection(Payment $payment, ?int $actorId = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $actorId, $note): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if ($lockedPayment->driver !== 'cash_on_delivery' || $lockedPayment->status !== Payment::STATUS_PENDING_COLLECTION) {
                throw ValidationException::withMessages(['payment' => 'Este pago no está pendiente de cobro contra entrega.']);
            }

            if ($order->status === Order::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['payment' => 'No se puede registrar el cobro de un pedido cancelado.']);
            }

            $from = $lockedPayment->status;
            $lockedPayment->update([
                'status' => Payment::STATUS_PAID,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $order->update(['payment_status' => 'paid']);
            $lockedPayment->events()->create([
                'from_status' => $from,
                'to_status' => Payment::STATUS_PAID,
                'actor_id' => $actorId,
                'note' => $note,
            ]);

            return $lockedPayment->fresh();
        });
    }
}
