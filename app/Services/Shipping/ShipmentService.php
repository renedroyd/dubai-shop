<?php

namespace App\Services\Shipping;

use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Orders\OrderInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function assignCourier(Order $order, string $name, ?string $phone, ?int $actorId = null): Shipment
    {
        return DB::transaction(function () use ($order, $name, $phone, $actorId): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($lockedOrder->status, [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED], true)) {
                throw ValidationException::withMessages(['order' => 'El pedido debe estar en preparación o enviado para asignar un repartidor.']);
            }

            $shipment = Shipment::query()->firstOrCreate(
                ['order_id' => $lockedOrder->id],
                ['tracking_code' => 'CD-'.Str::upper(Str::random(10)), 'status' => Shipment::STATUS_ASSIGNED],
            );
            $shipment->update(['courier_name' => $name, 'courier_phone' => $phone]);
            $this->event($shipment, 'courier_assigned', 'Repartidor asignado: '.$name, ['phone' => $phone], $actorId);

            return $shipment->refresh();
        });
    }

    public function dispatch(Order $order, ?int $actorId = null): Shipment
    {
        return DB::transaction(function () use ($order, $actorId): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->status !== Order::STATUS_PROCESSING) {
                throw ValidationException::withMessages(['order' => 'Solo se pueden despachar pedidos en preparación.']);
            }

            $shipment = Shipment::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if (! $shipment || blank($shipment->courier_name)) {
                throw ValidationException::withMessages(['shipment' => 'Asigna un repartidor antes de despachar el pedido.']);
            }
            if ($shipment->status === Shipment::STATUS_DELIVERED) {
                throw ValidationException::withMessages(['shipment' => 'Este envío ya fue entregado.']);
            }

            $shipment->update(['status' => Shipment::STATUS_DISPATCHED, 'dispatched_at' => now()]);
            $lockedOrder->update(['status' => Order::STATUS_SHIPPED]);
            $this->event($shipment, 'dispatched', 'Pedido despachado para entrega.', [], $actorId);

            return $shipment->refresh();
        });
    }

    public function recordAttempt(
        Shipment $shipment,
        string $result,
        ?string $recipientName,
        ?string $note,
        ?int $actorId = null,
    ): DeliveryAttempt {
        return DB::transaction(function () use ($shipment, $result, $recipientName, $note, $actorId): DeliveryAttempt {
            $lockedShipment = Shipment::query()->lockForUpdate()->with('order')->findOrFail($shipment->id);
            if ($lockedShipment->status === Shipment::STATUS_DELIVERED) {
                throw ValidationException::withMessages(['shipment' => 'No se pueden registrar intentos después de la entrega.']);
            }
            if ($lockedShipment->status !== Shipment::STATUS_DISPATCHED && $lockedShipment->status !== Shipment::STATUS_DELIVERY_FAILED) {
                throw ValidationException::withMessages(['shipment' => 'El envío debe estar despachado para registrar un intento.']);
            }
            if (! in_array($result, ['delivered', 'failed'], true)) {
                throw ValidationException::withMessages(['result' => 'Resultado de entrega no válido.']);
            }

            $attempt = $lockedShipment->attempts()->create([
                'attempt_number' => $lockedShipment->attempts()->count() + 1,
                'result' => $result,
                'recipient_name' => $recipientName,
                'note' => $note,
                'recorded_by' => $actorId,
                'attempted_at' => now(),
            ]);

            if ($result === 'delivered') {
                if ($lockedShipment->order->payment_status !== 'paid') {
                    throw ValidationException::withMessages(['shipment' => 'Confirma el cobro antes de completar la entrega.']);
                }

                app(OrderInventoryService::class)->fulfill($lockedShipment->order);
                $lockedShipment->update([
                    'status' => Shipment::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'recipient_name' => $recipientName,
                    'delivery_note' => $note,
                ]);
                $lockedShipment->order->update(['status' => Order::STATUS_DELIVERED]);
                $this->event($lockedShipment, 'delivered', 'Entrega confirmada'.($recipientName ? ' a '.$recipientName : '').'.', ['note' => $note], $actorId);
            } else {
                $lockedShipment->update(['status' => Shipment::STATUS_DELIVERY_FAILED]);
                $this->event($lockedShipment, 'delivery_failed', 'Intento de entrega no completado.', ['note' => $note], $actorId);
            }

            return $attempt;
        });
    }

    private function event(Shipment $shipment, string $key, string $description, array $metadata, ?int $actorId): void
    {
        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'event_key' => $key,
            'description' => $description,
            'metadata' => $metadata,
            'recorded_by' => $actorId,
            'occurred_at' => now(),
        ]);
    }
}
