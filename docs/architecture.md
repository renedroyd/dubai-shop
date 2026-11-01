# Dubai Shop — Arquitectura técnica

## Objetivo
Construir una tienda online monolítica modular sobre Laravel 13, Livewire y Filament 5. La tienda pública y el panel administrativo comparten modelos y casos de uso, pero mantienen capas de presentación independientes.

## Principios
1. **Una sola fuente de verdad:** los datos comerciales se persisten en modelos Eloquent y se administran desde Filament.
2. **Monolito modular:** separar dominios por responsabilidad sin introducir microservicios prematuramente.
3. **Lógica fuera de la interfaz:** componentes Livewire y recursos Filament representan y validan; acciones y servicios ejecutan reglas de negocio.
4. **Dinero exacto:** importes persistidos como enteros en unidades menores y código ISO de moneda; nunca usar float para cálculos monetarios.
5. **Pedidos históricos inmutables:** cada línea conserva nombre, SKU, precio, cantidad e impuestos al momento de compra.
6. **Seguridad por defecto:** Policies, validación en servidor, CSRF y secretos únicamente en entorno.
7. **Configuración administrable:** contenido, navegación, identidad visual y opciones comerciales editables desde Filament, con valores por defecto versionados.
8. **Pruebas por dominio:** cubrir reglas críticas con pruebas unitarias y flujos HTTP/Livewire con pruebas de integración.

## Capas
- app/Domain/<Context>/: reglas, enums, value objects y contratos cuando la complejidad lo justifique.
- app/Actions/<Context>/: operaciones de negocio explícitas.
- app/Services/<Context>/: coordinadores e integraciones entre componentes.
- app/Models/: persistencia Eloquent y relaciones.
- app/Policies/: autorización.
- app/Livewire/: interfaz pública reactiva.
- app/Filament/: administración.
- resources/views/: layouts, componentes Blade y vistas.
- config/: configuración técnica; las opciones editables de tienda viven en base de datos.

No crear carpetas vacías como arquitectura ornamental: incorporar cada módulo al implementar su primer caso de uso.

## Contextos funcionales

| Contexto | Responsabilidad |
| --- | --- |
| Catalog | Productos, categorías, marcas, variantes, imágenes y atributos |
| Inventory | Existencias, almacenes opcionales y movimientos auditables |
| Customers | Perfil, direcciones y datos de contacto |
| Cart | Carrito persistente para invitado y cliente autenticado |
| Checkout | Validación de disponibilidad, dirección, envío, impuestos y creación transaccional del pedido |
| Orders | Ciclo de vida, estados, líneas históricas y cancelaciones |
| Payments | Intentos, transacciones, referencias externas y conciliación |
| Shipping | Métodos, tarifas, etiquetas y seguimiento |
| Promotions | Cupones, reglas de descuento y vigencia |
| Content | Banners, páginas, menús y bloques de inicio |
| StoreSettings | Identidad, moneda, contacto, redes, SEO y opciones de visualización |
| Reviews | Valoraciones moderadas asociadas a productos y clientes |

## Dependencias
- PHP ^8.3
- Laravel ^13.17
- Filament ^5.0
- Livewire: incorporar como dependencia directa al iniciar la interfaz reactiva; comprobar versión compatible con Laravel 13 antes de fijarla.
- Tailwind CSS ^4 y Vite ^8 ya forman parte de la base frontend.

## Reglas transversales
- Checkout y actualización de inventario deben ejecutarse en transacciones.
- Prevenir sobreventa mediante bloqueos de fila o actualizaciones condicionales atómicas.
- Nunca confiar en precios, descuentos, totales ni permisos enviados por el navegador.
- Las pasarelas se integrarán detrás de contratos/adaptadores; no acoplar el pedido a un proveedor.
- Los webhooks deben verificar firma, ser idempotentes y registrar identificadores externos.
- Los cambios de estado de pedido y pago deben quedar auditables.
- Las imágenes deben almacenarse mediante Laravel Filesystem.

## Convenciones
- Clases y métodos en inglés siguiendo convenciones Laravel.
- Etiquetas y textos de interfaz en español, preparados para traducción.
- Migraciones pequeñas y reversibles.
- Enums PHP para estados cerrados; tablas para catálogos administrables.
- Evitar paquetes adicionales si Laravel, Livewire o Filament ya resuelven la necesidad.

## Entrega por fases
1. Arquitectura, documentación, CI y modelo de dominio acordado.
2. Sistema visual, layout público y componentes base.
3. Catálogo y administración de productos.
4. Carrito, checkout, pedidos, pagos, envíos e inventario.
5. Administración, métricas, permisos y operaciones.
6. Editor de contenido y personalización de la tienda.
7. SEO, notificaciones e integraciones.
8. Pruebas integrales, accesibilidad, rendimiento y preparación de producción.
