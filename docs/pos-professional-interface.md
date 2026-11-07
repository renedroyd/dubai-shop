# Interfaz POS profesional

## Objetivo

La interfaz de venta POS es una pantalla de operación independiente dentro del panel Filament. Se accede desde Punto de venta → Terminal de ventas, en /admin/pos-sales.

## Experiencia de caja

- Catálogo filtrable por nombre/SKU y categoría, limitado a 60 resultados por consulta.
- Productos activos de la moneda de la sesión, con imagen, SKU, precio y existencias del almacén asignado al terminal.
- Variantes seleccionables con precio propio y disponibilidad de su inventario.
- Carrito con incremento/decremento de cantidades, eliminación de líneas y total calculado en centavos.
- Búsqueda por nombre, correo o teléfono guardado; alta rápida de clientes y vinculación de la venta a una cuenta existente, conservando el cliente ocasional.
- Historial reciente de compras y clientes frecuentes limitado al almacén que el cajero puede consultar.
- Métodos de cobro: efectivo, Zelle manual solo USD y pago mixto efectivo/Zelle con revisión pendiente.
- Apertura de caja desde la pantalla, con terminal autorizado, moneda y fondo inicial.
- Entradas/salidas de efectivo y cierre con arqueo desde la misma interfaz.
- Selector de cajas abiertas cuando el cajero tiene más de un terminal.

## Integridad y seguridad

- La página requiere permiso global pos_cash_sessions.view y asignación pos.operate en al menos un almacén.
- La lista de terminales y sesiones se limita a almacenes donde el usuario puede operar.
- Los productos y existencias se consultan únicamente desde el almacén asignado a la sesión seleccionada.
- La interfaz usa PosCashSessionService para abrir, registrar movimientos y cerrar cajas.
- La interfaz usa PosSaleService para finalizar ventas. No crea órdenes, pagos ni movimientos de inventario directamente.
- La validación autoritativa de precio, moneda, inventario disponible, reservas, saldos por ubicación, terminal, permisos y estado de caja permanece en el servicio de dominio durante el cobro.
- No se permite vender offline. Se mantiene el modelo conectado al servidor.

## Alcance

La pantalla ofrece flujo táctil adaptable a escritorio/tablet y conserva los servicios de dominio para ventas, pagos, inventario y caja. Las ventas pueden ser de cliente ocasional o quedar vinculadas a una cuenta de cliente. El cobro admite efectivo, Zelle manual en USD y pagos mixtos; los pagos Zelle pendientes no autorizan la entrega hasta su aprobación. No se incorpora procesamiento automático de tarjeta.


## POS-10 — pagos avanzados (completado)

Primera entrega: el cobro en efectivo admite registrar el importe recibido. El cambio se calcula en unidades menores (centavos) y queda como metadato auditable del pago. El movimiento de caja registra únicamente el importe neto de la venta, nunca el efectivo entregado por el cliente; por tanto, el cambio no infla el efectivo esperado. Si el campo se deja vacío, se interpreta pago exacto.

La siguiente entrega incorporará Zelle manual en POS, evidencia privada, estados de revisión y reserva de inventario hasta la aprobación. Los pagos mixtos se habilitarán después de cerrar y probar por separado los flujos de efectivo y Zelle.


### POS-10 — Zelle manual

El POS ofrece Zelle únicamente en sesiones USD. Para registrar la venta se exige remitente, referencia, fecha y comprobante (PDF/JPG/PNG hasta 5 MB), almacenado en el disco privado local. La orden queda pendiente de revisión y el pago en estado `awaiting_review`; el stock se reserva sin descontarse ni generar movimiento de caja. La aprobación confirma la orden y consume la reserva; el rechazo cancela la orden y libera la reserva. La evidencia no se expone mediante URL pública.


### POS-10 — pagos mixtos

Permite dividir una venta USD entre efectivo y Zelle manual. Las dos partes deben ser mayores que cero y sumar exactamente el total. La caja registra únicamente el componente en efectivo; el componente Zelle queda pendiente y mantiene reservadas las existencias. Si Zelle se aprueba, se consume la reserva; si se rechaza, se libera y se registra la devolución del componente en efectivo como movimiento de caja y reversión auditada del pago.


### POS-10 — revisión y conciliación

La vista de pagos expone referencia, remitente, fecha de transferencia y usuario validador para facilitar la revisión. El comprobante se descarga mediante una acción protegida, desde almacenamiento privado. La aprobación POS verifica que los pagos ya confirmados más el pago que se aprueba coincidan exactamente con el total; el bloqueo transaccional y la transición de estado impiden confirmar dos veces o consumir dos veces la reserva. La caja solo contabiliza pagos en efectivo y sus devoluciones.


## Estado POS-10 — completado

La fase de pagos avanzados está integrada. El POS admite efectivo con cambio, Zelle manual en USD y pagos mixtos efectivo/Zelle. Los comprobantes se conservan en almacenamiento privado. Los pagos pendientes reservan existencias y requieren aprobación; el rechazo libera la reserva y, en pagos mixtos, registra la devolución del efectivo. La caja contabiliza solo entradas y devoluciones reales de efectivo, nunca el importe Zelle.

Los flujos están cubiertos por pruebas automatizadas y CI para PHP 8.4, PHP 8.5 y compilación frontend. Las fases siguientes contemplan clientes/fidelización, promociones, devoluciones, periféricos y reportes POS.


## POS-11 — clientes y fidelización (entrega en implementación)

La pantalla POS permite buscar clientes registrados por nombre, correo o teléfono guardado en sus direcciones. El cajero puede seleccionar una cuenta existente o crear una cuenta de cliente rápida desde la venta. La creación genera una cuenta de cliente no administrativa y una contraseña aleatoria; el cliente podrá establecer su contraseña mediante el flujo de recuperación de cuenta. Se conserva el registro de ventas ocasionales sin cuenta.

Al vincular una cuenta, orders.user_id relaciona la venta con el cliente sin duplicar la entidad de usuarios. Se muestran las últimas ventas POS del cliente únicamente en almacenes donde el cajero tiene permiso de consulta, además de los clientes frecuentes de los últimos 90 días para el almacén de la caja. El nombre y correo quedan también copiados como snapshot en la orden.

La infraestructura de puntos/fidelización queda para una entrega posterior: no se suman ni canjean puntos hasta definir conversión, vencimiento, límites y tratamiento de devoluciones.
