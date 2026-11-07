# Interfaz POS profesional

## Objetivo

La interfaz de venta POS es una pantalla de operación independiente dentro del panel Filament. Se accede desde Punto de venta → Terminal de ventas, en /admin/pos-sales.

## Experiencia de caja

- Catálogo filtrable por nombre/SKU y categoría, limitado a 60 resultados por consulta.
- Productos activos de la moneda de la sesión, con imagen, SKU, precio y existencias del almacén asignado al terminal.
- Variantes seleccionables con precio propio y disponibilidad de su inventario.
- Carrito con incremento/decremento de cantidades, eliminación de líneas y total calculado en centavos.
- Datos opcionales de cliente y selección de método de cobro inmediato en efectivo habilitado.
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

La pantalla ofrece flujo táctil adaptable a escritorio/tablet y conserva el listado administrativo de cajas y arqueos, recibos y devoluciones existentes. El recibo se consulta desde Pedidos después de registrar una venta. No se incorpora procesamiento de tarjeta ni Zelle automático; la primera versión del servicio POS solo admite cobro inmediato en efectivo habilitado.
