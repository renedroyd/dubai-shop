# Dubai Shop — Modelo de dominio y estado de implementación

Este documento describe el modelo lógico de referencia y distingue entidades existentes de capacidades conceptuales o futuras. No debe interpretarse cada tabla o relación propuesta como una migración ya creada. El catálogo (categorías, marcas, productos, imágenes y variantes), pedidos, inventario, pagos, clientes, direcciones, envíos, contenido y notificaciones tienen implementación funcional integrada. Las reglas de promociones/cupones y reseñas permanecen pendientes; los detalles exactos deben confirmarse en migraciones y modelos antes de modificar el esquema.

## Estado por contexto

| Contexto | Estado actual |
|---|---|
| Catálogo | Implementado; incluye categorías, marcas, productos, variantes e imágenes. Importación XLSX/CSV de categorías, marcas y productos; no importa imágenes ni variantes. |
| Inventario | Implementado con disponibilidad, reservas, movimientos y cumplimiento transaccional. |
| Clientes/direcciones | Implementado; mantiene checkout de invitado. |
| Carrito/checkout/pedidos | Implementado para invitado y cliente, con snapshots históricos. |
| Pagos | Drivers manuales Zelle y efectivo; auditoría de transiciones y comprobantes privados. No hay pasarela externa. |
| Envíos/logística | Tarifas y operación de tracking, repartidores e intentos de entrega integrados. |
| CMS/configuración | Banners, páginas, menús y configuración administrable integrados. |
| Notificaciones | Plantillas, eventos, colas, registro y reintentos integrados; SMTP/worker requieren configuración operativa. |
| Promociones/cupones | No implementados como motor de descuentos; requieren definición comercial. |
| Reseñas | No implementadas como sistema de valoraciones; no mostrar ratings ficticios. |

Las secciones siguientes sirven como mapa de datos y reglas para evolución. Antes de crear o alterar tablas, inspeccionar migraciones, modelos, factories y pruebas vigentes para evitar duplicar estructuras existentes.

## Catálogo
- **categories:** nombre, slug único, descripción, imagen, categoría padre nullable, estado y orden.
- **brands:** nombre, slug único, descripción, logo y estado.
- **products:** categoría principal, marca nullable, nombre, slug único, descripciones, SKU, precio en unidades menores, precio comparativo nullable, moneda ISO, estado, visibilidad, destacado, dimensiones opcionales y SEO.
- **product_variants:** producto, SKU único, opciones/atributos, precio opcional y estado.
- **product_images:** producto, variante nullable, ruta, texto alternativo, orden e imagen principal.
- **attributes / attribute_values / product_attribute_values:** atributos descriptivos y filtrables; separar de opciones que generan variantes.

Reglas: los productos simples pueden venderse sin variantes; el SKU debe ser único; no almacenar precios como float; controlar redirecciones SEO al cambiar slugs.

## Inventario y almacenes
- **warehouses:** almacenes con código único, tipo, dirección, estado y designación del almacén principal.
- **warehouse_locations:** árbol opcional de ubicaciones por almacén (área, pasillo, estantería y posición), con códigos únicos dentro del almacén.
- **inventories:** saldo agregado por almacén y producto/variante; la clave del artículo es única dentro del almacén.
- **inventory_movements:** inventario, tipo, cantidad con signo, motivo, referencia opcional y usuario responsable. El almacén se obtiene a través del inventario.
- **inventory_location_balances (siguiente etapa):** distribución del saldo de almacén entre ubicaciones físicas.

La cantidad disponible se deriva de existencias físicas menos reservas y unidades bloqueadas. Los registros existentes se migran al almacén principal sin recrear inventarios, preservando referencias históricas de pedidos y movimientos. El carrito y checkout continúan utilizando el almacén principal hasta que se implemente el abastecimiento configurable del canal web. Todo ajuste genera movimiento auditable.

## Clientes y direcciones
- **customers:** usuario nullable, nombre, email, teléfono y estado.
- **customer_addresses:** cliente, tipo, destinatario, país, región, ciudad, dirección, código postal e instrucciones.

Permitir checkout de invitado sin obligar a crear una cuenta. Los datos de contacto del pedido se copian como snapshot.

## Carrito y checkout
- **carts:** cliente nullable, identificador de sesión, moneda y expiración.
- **cart_items:** carrito, producto/variante y cantidad.

El precio del carrito es informativo y se recalcula en servidor al iniciar checkout. Cupón, impuestos y envío se validan nuevamente antes de confirmar.

## Pedidos
- **orders:** número público único, cliente nullable, estado, estado de pago, moneda, subtotal, descuentos, impuestos, envío, total, comprador snapshot, direcciones snapshot, notas y fechas relevantes.
- **order_items:** pedido, producto nullable, variante nullable, nombre snapshot, SKU snapshot, opciones snapshot, precio unitario, cantidad, descuento, impuesto y total de línea.

Los importes son enteros en unidades menores. La eliminación posterior de un producto no debe borrar ni alterar una línea histórica.

## Pagos
- **payments:** pedido, proveedor, referencia externa, estado, importe, moneda, respuesta saneada y fechas.
- **payment_webhook_events:** proveedor, identificador externo único, tipo, payload saneado, estado de procesamiento y fecha.

No almacenar datos de tarjetas. Los eventos webhook deben ser idempotentes.

## Envíos
- **shipping_methods:** nombre, descripción, reglas/tarifa configurables y estado.
- **shipments:** pedido, método, estado, transportista, tracking, coste y fechas.

## Promociones
- **coupons:** código normalizado único, tipo, valor, mínimo de compra, máximo de descuento, límites de uso, vigencia y estado.
- **coupon_redemptions:** cupón, pedido, cliente nullable y fecha.

La aplicación del descuento debe validarse en servidor y quedar vinculada al pedido.

## Contenido y configuración
- **pages:** título, slug, contenido estructurado, estado y SEO.
- **banners:** título interno, imagen, enlace, ubicación, vigencia, orden y estado.
- **menus / menu_items:** navegación administrable y jerárquica.
- **store_settings:** clave, valor tipado/JSON y grupo; acceso encapsulado mediante servicio de configuración y caché.

Evitar HTML arbitrario sin sanitización. La personalización visual debe usar tokens y opciones controladas, no CSS/JS libre por defecto.

## Reseñas
- **reviews:** producto, cliente, puntuación, título, comentario, estado de moderación y fecha.

La política de compra verificada se definirá con el flujo de pedidos completado.

## Relaciones principales
- Category has many Products; Category may have one parent Category.
- Brand has many Products.
- Product has many Variants, Images, Reviews and Inventory records por almacén.
- Warehouse has many WarehouseLocations, Inventories and users asignados (la asignación y permisos se implementarán en etapas posteriores).
- WarehouseLocation belongs to Warehouse and may have a parent and child locations.
- ProductVariant belongs to Product and has many Inventory records, uno por almacén.
- Customer has many Addresses, Carts and Orders.
- Cart has many CartItems.
- Order has many OrderItems, Payments and Shipments.
- Coupon has many Redemptions.
- Las políticas de eliminación de Product, Order y Customer deben preservar la integridad histórica.

## Decisiones pendientes para fases funcionales
Antes de implementar checkout y pagos, confirmar monedas admitidas, países/zonas de venta, cálculo de impuestos, métodos de pago reales, reglas de envío, política de stock/reservas, devoluciones y cancelaciones. No asumir proveedores ni credenciales.
