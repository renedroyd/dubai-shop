<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'order_received' => [
                'name' => 'Pedido recibido',
                'subject' => 'Hemos recibido tu pedido {order_number}',
                'body' => "Hola {customer_name},\n\nHemos recibido tu pedido {order_number} por {order_total} {currency}.\nEstado actual: {order_status}.\n\nGracias por comprar en {store_name}.",
            ],
            'payment_approved' => [
                'name' => 'Pago aprobado',
                'subject' => 'Pago aprobado para el pedido {order_number}',
                'body' => "Hola {customer_name},\n\nTu pago del pedido {order_number} ha sido aprobado.\nTotal: {order_total} {currency}.\n\nGracias por confiar en {store_name}.",
            ],
            'payment_rejected' => [
                'name' => 'Pago rechazado',
                'subject' => 'Necesitamos revisar el pago del pedido {order_number}',
                'body' => "Hola {customer_name},\n\nEl pago del pedido {order_number} no fue aprobado. Ponte en contacto con nuestro equipo para recibir asistencia.\n\n{store_name}.",
            ],
            'order_confirmed' => [
                'name' => 'Pedido confirmado',
                'subject' => 'Tu pedido {order_number} está confirmado',
                'body' => "Hola {customer_name},\n\nTu pedido {order_number} ha sido confirmado y pasará a preparación.\n\n{store_name}.",
            ],
            'order_processing' => [
                'name' => 'Pedido en preparación',
                'subject' => 'Estamos preparando tu pedido {order_number}',
                'body' => "Hola {customer_name},\n\nEstamos preparando tu pedido {order_number}.\n\n{store_name}.",
            ],
            'order_shipped' => [
                'name' => 'Pedido enviado',
                'subject' => 'Tu pedido {order_number} está en camino',
                'body' => "Hola {customer_name},\n\nTu pedido {order_number} ha sido marcado como enviado.\n\n{store_name}.",
            ],
            'order_delivered' => [
                'name' => 'Pedido entregado',
                'subject' => 'Tu pedido {order_number} ha sido entregado',
                'body' => "Hola {customer_name},\n\nTu pedido {order_number} figura como entregado. Esperamos que disfrutes tu compra.\n\n{store_name}.",
            ],
            'delivery_attempt_failed' => [
                'name' => 'Intento de entrega no completado',
                'subject' => 'No pudimos completar la entrega del pedido {order_number}',
                'body' => "Hola {customer_name},\n\nHemos intentado entregar tu pedido {order_number}, pero no fue posible completar la entrega. Nuestro equipo coordinará un nuevo intento.\n\nSeguimiento: {tracking_code}\nRepartidor: {courier_name}\nTeléfono: {courier_phone}\n\n{store_name}.",
            ],
            'order_cancelled' => [
                'name' => 'Pedido cancelado',
                'subject' => 'Actualización de tu pedido {order_number}',
                'body' => "Hola {customer_name},\n\nTu pedido {order_number} ha sido cancelado. Si necesitas ayuda, contacta con nuestro equipo.\n\n{store_name}.",
            ],
        ];

        foreach ($templates as $eventKey => $template) {
            NotificationTemplate::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [...$template, 'is_active' => true],
            );
        }
    }
}
