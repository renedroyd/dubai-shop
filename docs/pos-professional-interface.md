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


## POS-11 — clientes y fidelización (completado)

La pantalla POS integrada permite buscar clientes registrados por nombre, correo o teléfono guardado en sus direcciones. El cajero puede seleccionar una cuenta existente o crear una cuenta de cliente rápida desde la venta. La creación genera una cuenta de cliente no administrativa y una contraseña aleatoria; el cliente podrá establecer su contraseña mediante el flujo de recuperación de cuenta. Se conserva el registro de ventas ocasionales sin cuenta.

Al vincular una cuenta, orders.user_id relaciona la venta con el cliente sin duplicar la entidad de usuarios. Se muestran las últimas ventas POS del cliente únicamente en almacenes donde el cajero tiene permiso de consulta, además de los clientes frecuentes de los últimos 90 días para el almacén de la caja. El nombre y correo quedan también copiados como snapshot en la orden.

La infraestructura de puntos/fidelización queda para una entrega posterior: no se suman ni canjean puntos hasta definir conversión, vencimiento, límites y tratamiento de devoluciones.


## POS-12 — promociones, descuentos y precios (completado)

### Base de costos por almacén

El costo de adquisición y el precio mínimo de venta se gestionan en cada registro de inventario, cuya clave es almacén + producto/variante. Por tanto, un mismo producto o variante puede tener costos distintos en cada almacén sin modificar el precio de catálogo global.

Los importes se guardan en unidades menores (centavos) y usan la moneda del producto (CUP, USD o EUR), sin conversión automática. El precio mínimo opcional no puede ser inferior al costo de adquisición; cuando no se define un mínimo específico, el costo de adquisición constituye el límite inferior.

La edición requiere el permiso administrativo `inventory_costs.update` y permiso `inventory.manage` en el almacén correspondiente. La consulta de costos requiere `inventory_costs.view`. Cada modificación guarda usuario, valores anteriores/nuevos y motivo en `inventory_cost_changes`.

La base de costos y límites por almacén quedó integrada en PR #58. El motor de reglas permite configurar descuentos porcentuales/fijos y precios especiales por producto, categoría, marca o venta completa, con filtros opcionales por cliente, almacén y moneda. La prioridad determina qué regla única se aplica; no se acumulan reglas. Los descuentos que requieren aprobación necesitan el permiso de supervisor. Las ventas por debajo del costo/precio mínimo requieren el permiso explícito de excepción. Cada descuento aplicado conserva un evento de auditoría y snapshots de precio original, precio aplicado y costo. La validación final vuelve a calcular las reglas en el servicio de venta, dentro de la transacción.

## POS-13 — devoluciones, reembolsos y cambios (completado)

### Devoluciones y autorización

- Las devoluciones POS requieren dos personas distintas: quien procesa y quien autoriza. Se registran ambos usuarios y la fecha de autorización.
- Permisos dedicados: `pos_refunds.process`, `pos_refunds.approve` y `pos_refunds.view`.
- Se admiten devoluciones parciales y totales sin superar las cantidades vendidas ni el importe original cobrado.
- El importe devuelto respeta los descuentos por línea y el descuento global de la venta, con distribución determinista de unidades menores.

### Reembolso por los medios originales

- Los importes se distribuyen proporcionalmente entre los pagos originales de efectivo y Zelle, con límites acumulados por cada pago.
- Solo el componente en efectivo crea un movimiento de devolución en la caja. El componente Zelle se registra fuera del efectivo y conserva referencia al pago original.
- La venta original y sus pagos se actualizan de forma transaccional; cada componente conserva su estado y evento de auditoría.

### Estado y destino del inventario

- `resellable`: repone las unidades en las ubicaciones físicas de donde salieron en la venta.
- `quarantine`: registra las unidades en la ubicación `QUARANTINE` del almacén e incrementa `quantity_blocked`, por lo que no están disponibles para nuevas ventas.
- `discarded`: no reincorpora unidades al stock y crea un movimiento `return_discarded` con cantidad devuelta y observaciones.
- Cada línea de devolución guarda disposición y notas sobre el estado recibido.

### Cambios e historial

- Un cambio vincula la venta original, una devolución completada y una venta POS de reemplazo ya cobrada.
- El sistema valida almacén, moneda, permisos y unicidad del vínculo. Una devolución o venta de reemplazo no puede reutilizarse en otro cambio.
- Se conserva el valor devuelto, el valor de reemplazo y la diferencia neta firmada. La devolución y la venta de reemplazo mantienen sus cobros separados y auditables.
- La consulta de cambios está disponible en Ventas → Cambios POS. El detalle de cada pedido muestra las relaciones y la diferencia.
- El listado de pedidos permite buscar por número, cliente, correo y teléfono, y filtrar por fecha, terminal, almacén y estado de pago.

PR #61, #62 y #63 integran los bloques de devoluciones/reembolsos, disposición del inventario y cambios vinculados. Los tres bloques superaron CI de PHP 8.4, PHP 8.5 y Frontend assets antes de integrarse.


## POS-14A — periféricos base (integrado)

- Productos y variantes admiten códigos de barras únicos; el POS captura lectores tipo teclado (keyboard-wedge) mediante Enter.
- Cada terminal configura recibos térmicos de 58/80 mm y entre 1 y 3 copias.
- El recibo conserva descuentos y pagos mixtos y mantiene impresión de navegador como alternativa.

## POS-14B — QZ Tray (integrado en PR #66)

- QZ Tray se utiliza como puente local gratuito y de código abierto para comunicar el navegador con impresoras USB o de red instaladas en la computadora de caja.
- El nombre de impresora y la configuración de gaveta se guardan por terminal; la apertura ESC/POS se limita a terminales habilitados y se dispara después de una venta que haya cobrado un componente en efectivo. Zelle puro no abre la gaveta.
- Se mantiene una acción de prueba de periféricos protegida por permiso de operaciones de caja.
- QZ Tray debe estar instalado y ejecutándose en cada puesto. Sin firma de mensajes, QZ Tray puede solicitar confirmación; no se almacenan claves privadas en el navegador ni en el repositorio. La prueba física de impresora, pin y cable de gaveta es necesaria antes de aceptar cada terminal.


## POS-15 — dashboard e informes avanzados (integrado en PR #67)

- Filtros por período (hasta 366 días), almacén, terminal, cajero y moneda; todos los resultados respetan `reports.view` por almacén.
- Resumen de ventas por moneda y comparación con el período inmediatamente anterior.
- Desgloses por almacén, terminal, cajero, producto/variante, categoría, marca y método de pago.
- Los importes por método descuentan las asignaciones de reembolso efectuadas dentro del período. Los reembolsos se presentan también por almacén y moneda.
- Diferencias netas y absolutas de arqueos, sesiones de caja, movimientos de inventario y transferencias.
- Margen bruto estimado a partir del costo histórico guardado en cada línea y del descuento global del pedido. Si existe alguna línea sin costo histórico, el total de margen se marca como no disponible en lugar de mostrar un resultado engañoso. El margen no representa utilidad neta y se muestra antes de reembolsos.
- Exportación CSV de pedidos pagados dentro del alcance de filtros y almacenes autorizados.
- No se suman importes de monedas diferentes ni se aplica conversión automática. Los reportes por producto/categoría/marca reflejan descuentos de línea; los descuentos globales no se prorratean en esos desgloses.


## POS-16A — turnos, cajeros y control de caja (integrado en PR #68)

- Cada terminal puede restringirse a una lista de cajeros autorizados. Si la lista está vacía, se mantiene el acceso de cualquier usuario con permiso `pos.operate` para el almacén.
- Cada sesión guarda una etiqueta de turno/jornada y el cajero que la abrió.
- El límite de efectivo del terminal se expresa en centavos y se aplica al fondo inicial, entradas manuales y ventas que cobren efectivo. Los pagos exclusivamente Zelle no se bloquean ni alteran el saldo de caja.
- En terminales con cierre supervisado, el cajero registra el efectivo contado y solicita cierre; la sesión queda bloqueada inmediatamente para nuevas ventas y movimientos.
- Un usuario con permiso `pos_cash_sessions.approve_close` y acceso operativo al almacén debe aprobar el cierre. El solicitante no puede aprobar su propia solicitud. Se auditan solicitante, aprobador, importes y diferencia.
- Las sesiones pendientes de aprobación no se consideran cierres definitivos en los informes de diferencias de caja.

## POS-16B — alertas de inventario y reposición (integrado en PR #69)

- Umbral mínimo configurable por existencia de producto/variante y almacén; si no se define, se usa 5 unidades.
- Alertas de existencias disponibles bajas, restando reservas y bloqueos.
- Solicitudes de reposición desde la existencia con stock bajo, seleccionando origen, ubicaciones y cantidad; se crean como transferencias `replenishment` con las reglas de aprobación ya configuradas.
- Crear la solicitud no mueve ni reserva inventario. El stock se descuenta únicamente al despachar una transferencia aprobada.


## Estado de integración POS-1 a POS-16

Todos los bloques POS-1 a POS-16 están integrados en `main` (PR #49–69). El CI posterior a POS-16B (PR #69) pasó en PHP 8.4, PHP 8.5 y Frontend assets. La aceptación de hardware QZ Tray/impresoras/gavetas y las pruebas operativas de cajas y transferencias deben ejecutarse en las ubicaciones reales; la integración no implica aceptación de producción.


## POS-16C — seguimiento de transferencias e incidencias (en revisión, PR #71)

- Dashboard Filament con conteos por estado, transferencias activas/en tránsito y últimas 30 transferencias.
- Muestra origen/destino, tipo, solicitante, fecha y último evento con actor.
- Incidencias recientes de transferencias rechazadas y unidades dañadas.
- Conteos entrantes/salientes por almacén.
- Requiere `pos_reports.view` y limita resultados a transferencias cuyos dos almacenes están dentro del alcance `reports.view` del usuario.
- No modifica reglas de aprobación, stock ni el flujo de despacho/recepción.


### POS-16D — indicadores operativos por establecimiento (integrado en PR #72)

Dashboard filtrable por período que agrupa resultados por establecimiento: pedidos y ventas por moneda, reembolsos por moneda, terminales activos, sesiones de caja, cajas abiertas y cierres pendientes de aprobación. La autorización se calcula a partir de los almacenes accesibles con `reports.view`; no se exponen establecimientos ajenos al ámbito del usuario ni se agregan importes de monedas diferentes.


### Corrección POS — límites de efectivo por moneda (integrada en PR #73)

Los límites de efectivo se configuran independientemente para CUP, USD y EUR en cada terminal. El servicio valida el fondo inicial, entradas manuales y ventas que realmente cobran efectivo contra el límite de la moneda de la sesión. Los pagos exclusivamente Zelle no afectan el límite. Los valores existentes de `cash_limit_amount` se migran a CUP, la moneda predeterminada inicial del POS; el administrador puede ajustar USD/EUR desde la configuración del terminal.
