<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderNotification;
use App\Mail\OrderNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Observers\OrderObserver;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_notification_uses_active_template_and_records_success(): void
    {
        Mail::fake();
        $order = $this->createOrder();

        NotificationTemplate::query()->create([
            'event_key' => 'order_received',
            'name' => 'Pedido recibido',
            'subject' => 'Pedido {order_number} confirmado para {customer_name}',
            'body' => 'Total: {order_total} {currency} en {store_name}.',
            'is_active' => true,
        ]);

        (new SendOrderNotification($order->id, 'order_received'))->handle();

        Mail::assertSent(OrderNotificationMail::class, fn (OrderNotificationMail $mail): bool =>
            $mail->hasTo('cliente@example.com')
            && $mail->subjectLine === 'Pedido '.$order->number.' confirmado para Cliente Dubai'
            && str_contains($mail->body, '10.00 CUP')
        );

        $this->assertDatabaseHas('notification_logs', [
            'order_id' => $order->id,
            'event_key' => 'order_received',
            'recipient' => 'cliente@example.com',
            'status' => 'sent',
            'attempts' => 1,
        ]);
    }

    public function test_disabled_notification_template_is_logged_as_skipped(): void
    {
        Mail::fake();
        $order = $this->createOrder();

        NotificationTemplate::query()->create([
            'event_key' => 'order_received',
            'name' => 'Pedido recibido',
            'subject' => 'Pedido {order_number}',
            'body' => 'Hola {customer_name}',
            'is_active' => false,
        ]);

        (new SendOrderNotification($order->id, 'order_received'))->handle();

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('notification_logs', [
            'order_id' => $order->id,
            'event_key' => 'order_received',
            'status' => 'skipped',
        ]);
    }

    public function test_order_observer_dispatches_order_received_after_creation(): void
    {
        Queue::fake();
        $order = $this->createOrder();

        (new OrderObserver())->created($order);

        Queue::assertPushed(SendOrderNotification::class, fn (SendOrderNotification $job): bool =>
            $job->orderId === $order->id && $job->eventKey === 'order_received'
        );
    }

    public function test_default_notification_templates_are_seeded_idempotently(): void
    {
        $seeder = new NotificationTemplateSeeder();
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('notification_templates', 8);
        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'payment_approved',
            'is_active' => true,
        ]);
    }

    private function createOrder(): Order
    {
        return Order::query()->create([
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
            'payment_status' => 'awaiting_payment',
            'placed_at' => now(),
        ]);
    }
}
