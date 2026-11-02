<?php

namespace Tests\Feature\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_assigned_dispatched_and_tracked(): void
    {
        $order = $this->createOrder(['status' => Order::STATUS_PROCESSING]);
        $service = app(ShipmentService::class);

        $shipment = $service->assignCourier($order, 'Repartidor de prueba', '53000000', 1);
        $this->assertSame(Shipment::STATUS_ASSIGNED, $shipment->status);
        $this->assertNotEmpty($shipment->tracking_code);

        $shipment = $service->dispatch($order->refresh(), 1);

        $this->assertSame(Shipment::STATUS_DISPATCHED, $shipment->status);
        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'event_key' => 'dispatched',
        ]);
    }

    public function test_failed_delivery_attempt_is_audited_and_can_be_retried(): void
    {
        Queue::fake();
        $order = $this->createOrder(['status' => Order::STATUS_PROCESSING]);
        $service = app(ShipmentService::class);
        $shipment = $service->assignCourier($order, 'Repartidor', null);
        $service->dispatch($order);

        $attempt = $service->recordAttempt($shipment, 'failed', null, 'Cliente ausente');

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(Shipment::STATUS_DELIVERY_FAILED, $shipment->fresh()->status);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'event_key' => 'delivery_failed',
        ]);

        $service->dispatch($order->refresh());

        $this->assertSame(Shipment::STATUS_DISPATCHED, $shipment->fresh()->status);
    }

    public function test_delivery_cannot_be_completed_before_payment_is_confirmed(): void
    {
        $order = $this->createOrder(['status' => Order::STATUS_PROCESSING]);
        $service = app(ShipmentService::class);
        $shipment = $service->assignCourier($order, 'Repartidor', null);
        $service->dispatch($order);

        try {
            $service->recordAttempt($shipment, 'delivered', 'Destinatario', 'Recibido');
            $this->fail('Se esperaba impedir la entrega con pago pendiente.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('delivery_attempts', 0);
            $this->assertSame(Shipment::STATUS_DISPATCHED, $shipment->fresh()->status);
        }
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::withoutEvents(fn (): Order => Order::query()->create(array_merge([
            'number' => 'DS-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
            'public_token_hash' => hash('sha256', Str::random(64)),
            'customer_name' => 'Cliente Dubai',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '53000000',
            'shipping_address' => 'Calle 1',
            'shipping_city' => 'Guantánamo',
            'shipping_region' => 'Guantánamo',
            'shipping_country' => 'CU',
            'currency' => 'CUP',
            'subtotal_amount' => 1000,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending_collection',
            'placed_at' => now(),
        ], $overrides)));
    }
}
