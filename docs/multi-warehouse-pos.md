# Multi almacén y POS — arquitectura y alcance

**Estado:** POS-1 a POS-6 integrados; POS-7 en implementación. Este documento distingue lo integrado de lo que aún está en desarrollo.

## Decisiones aprobadas

- El POS funciona conectado al servidor. Sin conexión, no permite vender, cobrar ni registrar operaciones; no se implementará modo offline ni sincronización posterior.
- Los almacenes pueden tener una jerarquía flexible de ubicaciones: área, pasillo, estantería y posición. Los niveles son opcionales.
- Un producto o variante puede existir en varios almacenes; cada saldo y sus reservas son independientes.
- Las transferencias utilizarán reglas configurables de aprobación según almacén, usuario/rol, cantidad, valor o tipo.
- Los canales (web y POS) no son almacenes. Cada canal consume existencias de almacenes expresamente autorizados.
- La tienda online solo consulta almacenes activos habilitados explícitamente para abastecimiento web; los secundarios no se habilitan automáticamente.
- El almacén principal recibe los saldos existentes durante la migración para preservar la operación actual.

## POS-1 — Núcleo multi almacén

### Incluido en esta entrega

- Tabla `warehouses` con código, nombre, tipo, dirección, notas, estado y marca de almacén principal.
- Tabla jerárquica `warehouse_locations`, con relación padre/hijos y tipos de ubicación.
- Asignación obligatoria de cada registro de inventario a un almacén.
- Migración de los registros de inventario existentes al almacén principal `MAIN`, conservando sus identificadores y movimientos.
- Clave única de inventario compuesta por almacén y clave de producto/variante.
- Campo de cantidad bloqueada; disponibilidad = físico - reservado - bloqueado.
- Servicio de inventario compatible con las llamadas existentes: usa el almacén principal por defecto y admite indicar un almacén explícito.
- El abastecimiento de la tienda online se define ahora en POS-5: consulta almacenes activos habilitados para web y respeta su prioridad.
- Relaciones Eloquent para consultar existencias por almacén.

### Evolución del núcleo

Los saldos por ubicación (POS-3), transferencias (POS-4) y abastecimiento web (POS-5) ya están integrados. Los terminales y su asignación de almacén se desarrollan en POS-6; las ventas y cajas corresponden a POS-7; los permisos por almacén y la aceptación integral corresponden a POS-8.

## Modelo de datos previsto

- `warehouses`: almacenes físicos/lógicos.
- `warehouse_locations`: árbol de ubicaciones con `warehouse_id`, `parent_id`, código, tipo y estado.
- `inventories`: saldo agregado por almacén y producto/variante.
- `inventory_movements`: libro de movimientos vinculado a `inventories`; la pertenencia al almacén se obtiene desde el inventario.
- `inventory_location_balances`: saldo físico por inventario y ubicación. La suma de sus cantidades debe coincidir con `inventories.quantity_on_hand`.
- `inventory_movements` conserva el historial agregado y añade ubicación de origen/destino para ajustes y movimientos internos.

## POS-3 — Saldos por ubicación (integrado)

- La migración crea un saldo por cada inventario en la ubicación técnica `UNASSIGNED`, preservando cantidades existentes.
- Cada almacén tiene una ubicación `UNASSIGNED`; el stock nuevo se registra allí hasta su ubicación física.
- Los movimientos entre ubicaciones son internos y no cambian el total del almacén.
- Los ajustes por ubicación actualizan atómicamente el saldo local y el total del inventario, respetando reservas y bloqueos.
- La conciliación compara el saldo agregado con la suma de saldos por ubicación.
- Filament permite consultar saldos, ajustar ubicaciones físicas y mover stock dentro del mismo almacén.

## POS-4 — Transferencias entre almacenes (integrado)

### Reglas y ciclo de vida

- Las reglas pueden limitar almacén de origen, almacén de destino, tipo de transferencia, cantidad mínima/máxima, roles solicitantes y roles autorizados para aprobar.
- Las reglas se evalúan por prioridad ascendente. La primera regla aplicable define la aprobación requerida; la aprobación automática es explícita.
- Se puede exigir una o varias aprobaciones de usuarios distintos. El solicitante no puede aprobar su propia transferencia salvo que la regla lo permita.
- Crear o aprobar una transferencia no cambia las existencias.
- El despacho valida stock disponible (físico menos reservado y bloqueado), descuenta del origen y registra la salida al tránsito.
- La recepción puede registrarse parcialmente. Las unidades buenas ingresan al inventario y ubicación de destino; las dañadas quedan contabilizadas como diferencia y nunca se suman al stock.
- Los movimientos enlazan la transferencia y se registran eventos de auditoría para creación, decisiones, despacho, daños y recepción.
- Las operaciones de inventario se ejecutan en transacciones y bloquean los inventarios afectados para reducir riesgos de concurrencia.

### Estados

`pending_approval` → `approved` → `in_transit` → `partially_received` → `received`

También se contemplan `rejected` y `cancelled`. La cancelación de una transferencia despachada requiere un flujo de reversión separado.

### Pendientes posteriores

- Reversión controlada de transferencias ya despachadas.
- Reportes y notificaciones operativas.

## POS-5 — Abastecimiento multi almacén de la tienda online (integrado)

- Cada almacén tiene un indicador explícito para habilitar el abastecimiento web y una prioridad numérica; menor número significa mayor prioridad.
- La migración habilita el almacén principal existente con prioridad 10. Los demás almacenes quedan deshabilitados hasta que un administrador los active.
- Carrito y checkout consultan únicamente los almacenes activos habilitados para web y calculan disponibilidad como existencia física menos reservas y bloqueos.
- Al confirmar el pedido, el asignador intenta cubrir todo el carrito desde un solo almacén, respetando prioridad. Si ninguno puede cubrir el pedido completo, distribuye las líneas y cantidades entre almacenes según prioridad.
- Cada tramo asignado se conserva como línea de pedido con su inventory_id; las reservas quedan asociadas al inventario del almacén que las atenderá.
- Los productos sin inventario controlado conservan el flujo actual.
- Al completar la entrega, se descuentan las existencias agregadas y los saldos por ubicación, registrando la distribución física consumida.
- No se suman existencias de almacenes no habilitados para la web. POS y canal web continúan siendo canales distintos.

### Criterios de aceptación POS-5

1. Activar/desactivar un almacén para pedidos online y modificar su prioridad desde Filament.
2. La disponibilidad del carrito suma solo stock disponible de almacenes online activos.
3. Si un almacén cubre todo el carrito, se reserva desde ese almacén.
4. Si ningún almacén cubre el carrito completo, se permite abastecimiento dividido según prioridad.
5. Las reservas y salidas mantienen conciliados los saldos agregados y por ubicación.
6. El checkout falla de forma segura si el stock cambia o resulta insuficiente antes de reservar.

## POS-6 — Establecimientos, terminales y almacén asignado (integrado)

- Los establecimientos POS representan puntos físicos de operación y se administran independientemente de los almacenes.
- Cada establecimiento tiene código único, nombre, dirección, teléfono, notas y estado.
- Cada terminal pertenece a un establecimiento y tiene código único dentro de ese establecimiento, nombre y estado.
- Cada terminal tiene un almacén activo asignado; las futuras ventas de ese terminal descontarán del inventario de ese almacén.
- Un terminal activo no puede operar si su establecimiento o almacén se desactiva. El sistema impide desactivar un establecimiento o almacén que tenga terminales activos; primero deben desactivarse sus terminales.
- La administración de establecimientos y terminales se realiza en Filament con permisos RBAC independientes.
- Esta fase no crea todavía ventas, cajas, arqueos, recibos, cobros ni devoluciones; eso corresponde a POS-7.
- Tampoco implementa todavía restricciones por usuario/cajero o permisos por almacén; se abordarán en POS-8.

### Criterios de aceptación POS-6

1. Crear y administrar establecimientos POS con códigos únicos.
2. Crear terminales vinculados a un establecimiento y asignarles un almacén activo.
3. Permitir reutilizar el código de terminal en establecimientos diferentes, pero no dentro del mismo establecimiento.
4. Impedir asignar terminales activos a establecimientos o almacenes inactivos.
5. Impedir desactivar establecimientos o almacenes con terminales activos.
6. Aplicar permisos RBAC separados para consultar, crear, modificar y eliminar establecimientos y terminales.

## POS-7 — Ventas, caja, recibos y devoluciones (implementación lista para validar)

- Las ventas POS se registran como pedidos compartidos (orders) con canal pos, terminal, sesión de caja y cajero asociados.
- Cada terminal utiliza exclusivamente el almacén activo que tiene asignado; el POS no suma existencias de otros almacenes.
- La venta bloquea la sesión, inventario y saldos por ubicación dentro de una transacción. Si el stock agregado y el distribuido por ubicaciones no concuerdan, la operación se rechaza sin guardar una venta parcial.
- La salida de inventario genera movimientos auditables vinculados al pedido y reduce el saldo agregado y el de sus ubicaciones.
- Las sesiones de caja registran fondo inicial, entradas/salidas manuales, ventas cobradas, efectivo esperado, efectivo contado y diferencia de arqueo.
- Un terminal solo puede tener una sesión abierta; no se permiten ventas ni movimientos manuales en sesiones cerradas.
- En la primera entrega, el cobro inmediato POS usa un método de efectivo habilitado. Zelle y otros métodos manuales no se marcan automáticamente como cobrados desde el POS.
- Los recibos consultan los mismos pedidos y pagos. Las devoluciones parciales o totales requieren una sesión abierta del mismo terminal/moneda, registran el reembolso, descuentan efectivo de caja y restauran stock en las ubicaciones originales.

### Criterios de aceptación POS-7

1. Abrir una sesión de caja para un terminal activo y evitar sesiones simultáneas en el mismo terminal.
2. Registrar ventas como pedidos del canal POS, asociados a terminal, sesión y cajero.
3. Validar precio, moneda y existencias del almacén asignado antes de confirmar la venta.
4. Descontar stock agregado y por ubicación de forma atómica y auditable.
5. Registrar pagos compartidos y movimientos de efectivo en la sesión.
6. Registrar entradas/salidas de caja y cerrar con arqueo y diferencia calculada.
7. Consultar e imprimir un recibo POS desde el pedido.
8. Rechazar ventas en sesiones cerradas o con inventario inconsistente.
9. Registrar devoluciones parciales/totales en caja abierta del mismo terminal y moneda, sin exceder unidades ni importes originales.
10. Reintegrar el inventario en las ubicaciones de origen y registrar movimientos de caja, pagos y auditoría del reembolso.

## Reglas de integridad

1. Un almacén principal debe existir y estar activo.
2. Los códigos de almacén son únicos; los códigos de ubicación son únicos dentro de cada almacén.
3. La misma clave de producto/variante puede repetirse en almacenes distintos, nunca dos veces dentro del mismo almacén.
4. No permitir saldo físico negativo ni reducirlo por debajo de reservas/bloqueos.
5. Las reservas y salidas deben apuntar al inventario concreto que atiende la operación.
6. No sumar saldos de almacenes distintos para prometer disponibilidad sin una regla explícita de canal.
7. Las operaciones de stock se ejecutan en transacciones y generan movimientos auditables.

## Migración y compatibilidad

La migración crea el almacén principal y una ubicación técnica `UNASSIGNED`. Todos los inventarios existentes se asignan a `MAIN` sin recrear filas, de modo que `order_items.inventory_id` y el historial de movimientos sigan apuntando al mismo registro. El rollback se detiene si existen claves duplicadas entre almacenes, para evitar eliminar inventario al intentar restaurar la antigua unicidad global.

La tienda pública conserva temporalmente el comportamiento anterior: carrito, checkout y relaciones de stock heredadas consultan el almacén principal. La selección por prioridades y las reservas por almacén se implementarán en una etapa posterior antes de habilitar ventas desde otros almacenes.

## Secuencia posterior

1. POS-7: ventas integradas con pedidos y pagos, cajas, arqueos, recibos y devoluciones.
2. POS-8: permisos por almacén y usuario, informes y pruebas integrales.
