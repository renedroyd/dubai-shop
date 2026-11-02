# Operación logística y seguimiento — Fase 14

## Alcance

La logística se gestiona sobre el pedido existente. Cada pedido despachado dispone de un envío asociado, un código de seguimiento único, datos de repartidor, eventos cronológicos e intentos de entrega auditables.

La fase mantiene las decisiones comerciales vigentes:
- Cobertura inicial: provincia de Guantánamo.
- Compromiso registrado en el pedido: entrega dentro de 24 horas.
- No se amplían zonas ni tarifas automáticamente.
- No se confirma una entrega hasta que el pago esté marcado como pagado.

## Flujo administrativo

1. Confirmar el pedido y pasarlo a preparación desde Filament > Ventas > Pedidos.
2. Asignar nombre y teléfono del repartidor. La primera asignación crea el envío y su código de seguimiento.
3. Despachar el pedido. Esta operación cambia el pedido a enviado y agrega un evento al historial.
4. Registrar cada intento de entrega:
   - **Entregado:** requiere pago confirmado; guarda fecha y persona que recibe, ejecuta el cumplimiento de inventario y cambia el pedido a entregado.
   - **No se pudo entregar:** guarda el intento y la incidencia; el cliente recibe una notificación y el envío puede volver a despacharse tras coordinar un nuevo intento.
5. Consultar el código, el repartidor, los eventos y los intentos desde el detalle del pedido.

Los cambios se realizan mediante ShipmentService y transacciones con bloqueo de filas para evitar despachos o cierres concurrentes inconsistentes.

## Seguimiento del cliente

El cliente consulta el estado desde el enlace privado del pedido (número + token aleatorio). La página muestra el código de seguimiento, el contacto del repartidor, la cronología y los intentos registrados. La página mantiene cabeceras privadas/no-store y no expone información de otros pedidos.

## Datos

- `shipments`: envío único por pedido, código, estado, repartidor, fechas y datos de recepción.
- `delivery_attempts`: secuencia de intentos, resultado, destinatario, observaciones, actor y fecha.
- `shipment_events`: historial cronológico inmutable de asignación, despacho, incidencias y entrega.

## Notificaciones

Se añade el evento `delivery_attempt_failed`, editable desde Filament > Comunicación > Plantillas de correo. Variables adicionales:
- `{tracking_code}`
- `{courier_name}`
- `{courier_phone}`

La notificación se procesa mediante la cola de Laravel. Para recibirla en un entorno real se requiere SMTP configurado y un worker activo.

## Pruebas y aceptación

Las pruebas automatizadas cubren asignación, despacho, auditoría de intentos, reintento y bloqueo de entrega cuando el pago no está confirmado. Antes del lanzamiento se debe probar el recorrido completo con personal/repartidor real y confirmar el canal de contacto.
