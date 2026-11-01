# Carrito e inventario — Fase 4A

## Alcance entregado

- Carrito persistente asociado a la sesión del navegador.
- Líneas de carrito por producto o variante, con cantidades entre 1 y 99.
- Moneda única por carrito; se impide mezclar monedas.
- Precio unitario registrado al añadir el artículo. El checkout deberá recalcular todos los importes en servidor.
- Control de existencias al añadir o actualizar cantidades cuando el producto tenga inventario configurado.
- Los productos sin registro de inventario se consideran no controlados por stock, para no bloquear el catálogo existente.
- Ajustes de existencias transaccionales y auditados en `inventory_movements`.
- El carrito no reserva ni descuenta existencias. La reserva/descuento se definirá e implementará en checkout, con bloqueo transaccional.

## Rutas públicas

- `GET /carrito`
- `POST /carrito/items`
- `PATCH /carrito/items/{cartItem}`
- `DELETE /carrito/items/{cartItem}`

## Modelo de inventario

Cada registro de `inventories` representa el stock de un producto simple o de una variante. `stock_key` garantiza una clave estable (`product:{id}` o `variant:{id}`) aunque la base de datos permita múltiples valores NULL en índices compuestos.

- `quantity_on_hand`: unidades físicas registradas.
- `quantity_reserved`: unidades reservadas por operaciones futuras.
- `available_quantity`: cálculo `max(0, on_hand - reserved)`.
- `inventory_movements`: auditoría de ajustes con delta firmado.

La API de dominio actual permite ajustar stock desde servicios. La interfaz administrativa de inventario y sus permisos se incorporarán en la siguiente entrega.

## Límites deliberados

Esta fase no implementa checkout, pedidos, cobros, impuestos, envíos ni reserva de stock. No se conecta ninguna pasarela de pago. La acción de continuar al checkout permanece deshabilitada hasta definir esas reglas.

Livewire aún no se utiliza en estas pantallas. El carrito actual utiliza formularios HTTP tradicionales; se convertirá a componentes Livewire cuando se añada Livewire como dependencia directa y se defina el flujo reactivo de checkout.

## Decisiones necesarias para Fase 4B

1. Monedas y moneda base de la tienda.
2. Países/zonas de venta e impuestos aplicables.
3. Política de inventario: permitir pedidos sin stock, reservar al iniciar checkout o descontar al confirmar.
4. Métodos de pago y proveedores reales (incluyendo si se necesita pago manual/contra entrega).
5. Métodos de envío, zonas, tarifas y quién gestiona el despacho.
6. Política de cancelación, reembolso y devolución.
