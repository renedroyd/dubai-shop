# Dubai Shop — Modelo de dominio propuesto

Este documento define el mapa lógico inicial. No implica que las tablas ya existan. Las migraciones se incorporarán junto con los casos de uso de cada fase para evitar esquemas especulativos difíciles de evolucionar.

## Catálogo
- **categories:** nombre, slug único, descripción, imagen, categoría padre nullable, estado y orden.
- **brands:** nombre, slug único, descripción, logo y estado.
- **products:** categoría principal, marca nullable, nombre, slug único, descripciones, SKU, precio en unidades menores, precio comparativo nullable, moneda ISO, estado, visibilidad, destacado, dimensiones opcionales y SEO.
- **product_variants:** producto, SKU único, opciones/atributos, precio opcional y estado.
- **product_images:** producto, variante nullable, ruta, texto alternativo, orden e imagen principal.
- **attributes / attribute_values / product_attribute_values:** atributos descriptivos y filtrables; separar de opciones que generan variantes.

Reglas: los productos simples pueden venderse sin variantes; el SKU debe ser único; no almacenar precios como float; controlar redirecciones SEO al cambiar slugs.

## Inventario
- **inventories:** variante o producto vendible, cantidad disponible y cantidad reservada.
- **inventory_movements:** artículo, tipo, cantidad con signo, motivo, referencia opcional y usuario responsable.

La cantidad disponible para venta se deriva de existencias menos reservas. Todo ajuste manual genera movimiento auditable.

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
- Product has many Variants, Images and Reviews.
- ProductVariant belongs to Product and has Inventory.
- Customer has many Addresses, Carts and Orders.
- Cart has many CartItems.
- Order has many OrderItems, Payments and Shipments.
- Coupon has many Redemptions.
- Las políticas de eliminación de Product, Order y Customer deben preservar la integridad histórica.

## Decisiones pendientes para fases funcionales
Antes de implementar checkout y pagos, confirmar monedas admitidas, países/zonas de venta, cálculo de impuestos, métodos de pago reales, reglas de envío, política de stock/reservas, devoluciones y cancelaciones. No asumir proveedores ni credenciales.
