# Multi almacén y POS — arquitectura y alcance

**Estado:** POS-1, POS-2 y POS-3 integrados; POS-4 en implementación. Este documento distingue lo integrado de lo que aún está en desarrollo.

## Decisiones aprobadas

- El POS funciona conectado al servidor. Sin conexión, no permite vender, cobrar ni registrar operaciones; no se implementará modo offline ni sincronización posterior.
- Los almacenes pueden tener una jerarquía flexible de ubicaciones: área, pasillo, estantería y posición. Los niveles son opcionales.
- Un producto o variante puede existir en varios almacenes; cada saldo y sus reservas son independientes.
- Las transferencias utilizarán reglas configurables de aprobación según almacén, usuario/rol, cantidad, valor o tipo.
- Los canales (web y POS) no son almacenes. Cada canal consume existencias de almacenes expresamente autorizados.
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
- Carrito y checkout siguen consultando el almacén principal hasta que se implemente la política de abastecimiento web.
- Relaciones Eloquent para consultar existencias por almacén.

### No incluido todavía

- Saldos por ubicación física y asignación de unidades a posiciones.
- Transferencias, aprobaciones, despacho, tránsito y recepción.
- Reglas de abastecimiento de tienda online.
- Terminales, cajas y ventas POS.
- Permisos por almacén.

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

## POS-4 — Transferencias entre almacenes (en implementación)

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

### Alcance aún pendiente

- Pruebas de CI y validación de los flujos completos.
- Reversión controlada de transferencias ya despachadas.
- Reportes y notificaciones operativas.
- POS-5: política de abastecimiento multi almacén para la tienda online.

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

1. POS-2: administración de almacenes y árbol de ubicaciones en Filament (en implementación).
2. POS-3: saldos por ubicación, ajustes, movimientos y conciliación.
3. POS-4: transferencias y reglas configurables de aprobación.
4. POS-5: abastecimiento de tienda online.
5. POS-6: terminales POS y asignación de almacén.
6. POS-7: ventas, pagos, cajas, recibos y devoluciones.
7. POS-8: permisos por almacén, informes y pruebas integrales.
