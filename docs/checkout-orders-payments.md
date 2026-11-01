# Checkout, pedidos y pagos — Fase 4B

## Alcance
- Checkout de invitado con datos de contacto y dirección de entrega.
- Pedidos con instantánea de productos, variantes, SKU, cantidades, precios y moneda.
- Número de pedido y token aleatorio para consultar el comprobante de pedido sin crear una cuenta.
- Métodos de pago seleccionables desde el panel Filament.
- Driver de pagos intercambiable mediante `PaymentDriver` y `PaymentDriverManager`.
- Zelle con comprobante privado y revisión manual.
- Efectivo contra entrega con confirmación manual de cobro.
- Panel Filament para revisar comprobantes, aprobar/rechazar pagos, consultar pedidos y avanzar su estado.
- Reservas de inventario al crear el pedido; liberación al cancelar y descuento de existencias al marcar el pedido como entregado.

## Configuración inicial
En Filament > Configuración > Métodos de pago:
1. Editar Zelle.
2. Introducir los valores `recipient_name` y `recipient_email` en la configuración, además de las instrucciones visibles al cliente.
3. Activar el método únicamente después de comprobar los datos.
4. Efectivo contra entrega viene habilitado inicialmente.

Los comprobantes se almacenan en el disco privado `local` bajo `payment-proofs`. No se publican mediante `/storage`; la descarga se ofrece únicamente desde el panel autenticado.

## Estados
- Pedido: `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`.
- Pago Zelle: `awaiting_review` → `paid` o `rejected`.
- Efectivo: `pending_collection` → `paid` cuando el personal confirma el cobro.

La aprobación de Zelle confirma el pedido. Un pedido COD requiere confirmación administrativa. El inventario reservado se descuenta al completar la entrega; la cancelación libera la reserva. Las operaciones de inventario son idempotentes mediante `inventory_processed_at`.

## Integraciones futuras
Para integrar otra pasarela:
1. Crear una clase que implemente `App\\Contracts\\Payments\\PaymentDriver`.
2. Registrar la clase en `config/payments.php`.
3. Implementar la creación de pago y las instrucciones / datos necesarios para el checkout.
4. Agregar validación específica de entrada y manejo de callbacks/webhooks firmados para proveedores asíncronos.
5. Añadir pruebas de integración y seguridad del proveedor.

No se almacenan datos de tarjetas. Las credenciales privadas de futuras pasarelas deben residir en variables de entorno o en un almacén de secretos, nunca en campos públicos.

## Límites que requieren definición comercial
- El importe registrado actualmente incluye únicamente productos. Envío e impuestos se mantienen en cero hasta configurar zonas, tarifas y reglas fiscales; el checkout informa al cliente que esos importes se confirmarán por separado.
- Zelle se crea deshabilitado hasta que se configuren y verifiquen los datos del destinatario.
- No se integra todavía una pasarela externa ni se reciben webhooks.
- No se implementa aún el correo transaccional; la pantalla de confirmación muestra el estado del pedido.
