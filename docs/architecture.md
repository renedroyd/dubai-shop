# Dubai Shop — Arquitectura técnica

## Objetivo y estado

Aplicación de comercio electrónico monolítica modular sobre Laravel 13. La tienda pública utiliza Blade y controladores Laravel; Filament 5 proporciona el panel administrativo y utiliza Livewire internamente. Tailwind CSS 4 y Vite 8 gestionan los assets.

La arquitectura debe evolucionar por dominios y casos de uso reales. No introducir microservicios ni capas vacías sin una necesidad concreta.

## Principios

1. **Una sola fuente de verdad:** datos comerciales en Eloquent y administrados desde Filament o flujos públicos autorizados.
2. **Monolito modular:** separar responsabilidades por dominio sin complejidad distribuida prematura.
3. **Lógica fuera de la interfaz:** controladores, vistas y recursos Filament coordinan; acciones/servicios aplican reglas de negocio.
4. **Dinero exacto:** importes en unidades menores (enteros) y moneda ISO; no usar `float` para cálculos monetarios.
5. **Pedidos históricos:** conservar snapshots de nombre, SKU, precio, cantidad, moneda, dirección y datos relevantes al momento de compra.
6. **Seguridad por defecto:** autorización en servidor, validación, CSRF, sesiones seguras y secretos fuera del repositorio.
7. **Configuración administrable:** contenido y opciones comerciales configurables desde Filament, con valores por defecto versionados.
8. **Pruebas por dominio:** pruebas unitarias para reglas y pruebas de integración para HTTP, autenticación, Filament y checkout.
9. **Idempotencia y auditoría:** pagos, inventario, notificaciones y operaciones logísticas deben tolerar reintentos sin duplicar efectos.

## Stack verificado

| Componente | Tecnología |
|---|---|
| Runtime | PHP 8.4+; CI en PHP 8.4 y 8.5 |
| Framework | Laravel 13 |
| Administración | Filament 5 |
| Escaparate | Blade + controladores Laravel |
| Reactividad del panel | Livewire integrado en Filament |
| CSS | Tailwind CSS 4 |
| Build | Vite 8 |
| Persistencia | Eloquent + migraciones |
| Calidad | GitHub Actions, PHPUnit/Pest según configuración, Composer Audit y npm audit |

No asumir que Livewire es una dependencia directa del escaparate. La tienda pública se implementa actualmente con Blade, controladores y JavaScript progresivo.

## Capas y responsabilidades

- `app/Models/`: persistencia Eloquent, relaciones y casts.
- `app/Actions/` y `app/Services/`: operaciones de negocio y coordinación entre dominios, cuando la complejidad lo justifica.
- `app/Policies/` y autorización de panel: RBAC por recurso/acción, acceso al panel y protección de las operaciones administrativas.
- `app/Filament/`: recursos, páginas, widgets y acciones administrativas.
- `app/Http/`: rutas, middleware, controladores y validación HTTP.
- `resources/views/`: layouts, componentes Blade y vistas públicas.
- `resources/css/` y `resources/js/`: estilos y comportamiento progresivo.
- `config/`: configuración técnica versionada; secretos y valores de entorno no deben codificarse.
- `database/migrations`, `seeders` y `factories`: evolución y datos reproducibles.

No crear carpetas vacías como arquitectura ornamental: introducir cada módulo al implementar su primer caso de uso.

## Contextos funcionales

| Contexto | Responsabilidad y estado |
|---|---|
| Catalog | Categorías, marcas, productos, variantes, imágenes, importación XLSX/CSV |
| Inventory | Existencias, reservas, movimientos y disponibilidad; núcleo multi almacén en POS-1 |
| Customers | Cuentas, perfiles y direcciones guardadas |
| Cart | Carrito de invitado y cliente |
| Checkout | Validación de precios, stock, envío y creación del pedido |
| Orders | Estados, snapshots, cancelación y consulta privada |
| Payments | Drivers manuales, comprobantes privados, auditoría de transiciones |
| Shipping | Tarifas, zonas, envíos y tracking |
| Promotions | Banners y campañas CMS; cupones/descuentos automáticos aún no definidos |
| Content | Banners, páginas y menús |
| StoreSettings | Identidad, idioma, moneda, contacto y opciones de tienda |
| Notifications | Plantillas, eventos, colas, registro y reintentos |
| Localization | Español e inglés iniciales; registro extensible de locales |

El modelo de reseñas/valoraciones y las promociones con cupones no deben presentarse como funcionalidades terminadas hasta que exista implementación y aceptación específica.

## Reglas transversales

- Checkout, reserva/liberación de inventario y transiciones críticas deben usar transacciones y bloqueo/actualización atómica cuando corresponda.
- El inventario se identifica por almacén y producto/variante. Hasta completar el abastecimiento multicanal, carrito y checkout consultan explícitamente el almacén principal.
- El almacén principal recibe en migración los saldos existentes, conservando IDs de inventario y referencias históricas.
- Nunca confiar en precios, descuentos, totales, stock o permisos enviados por el navegador.
- Las líneas de pedido conservan snapshots y no dependen de que el producto siga existiendo.
- Los pagos deben registrar transiciones auditables; integraciones futuras deben verificar firma, ser idempotentes y no almacenar datos de tarjeta.
- Los comprobantes de pago permanecen en almacenamiento privado y detrás de autorización.
- Las notificaciones se despachan después del commit y se procesan mediante una cola supervisada.
- Las imágenes se almacenan mediante Laravel Filesystem; el disco público no debe utilizarse para archivos privados.
- Las operaciones logísticas y de inventario deben ser idempotentes ante reintentos.
- Los proxies inversos solo se consideran confiables cuando están configurados explícitamente. El comodín `TRUSTED_PROXIES=*` se reserva exclusivamente al entorno local.

## Decisiones comerciales actualmente reflejadas

- Identidad pública: Clásico Dubai.
- Monedas admitidas por el modelo actual: USD, CUP y EUR; no se convierten ni se mezclan dentro de un pedido.
- Zelle manual restringido a USD.
- Efectivo contra entrega sujeto a configuración operativa.
- Cobertura logística inicial: provincia de Guantánamo.
- Compromiso de entrega configurado: 24 horas.
- No ampliar zonas, tarifas o métodos de pago sin decisión comercial y validación.

Los importes, destinatarios, tarifas, credenciales y contactos reales deben verificarse en el entorno; no incluir datos personales ni secretos en esta documentación.

## Seguridad y producción

El panel aplica RBAC mediante roles asociados a usuarios, permisos almacenados por rol, excepciones individuales de usuario y políticas Laravel por recurso. `canAccessPanel()` exige que el acceso esté habilitado y que el usuario tenga `panel.access`; las operaciones CRUD y acciones personalizadas se verifican en servidor. La gestión de usuarios/roles queda reservada al Superadministrador, cuya cuenta y rol de emergencia están protegidos. La migración convierte los administradores existentes (`is_admin = true`) en Superadministradores.

La configuración de proxies, cabeceras, HSTS condicional a HTTPS, rate limits, validación de archivos, auditorías de dependencias y el comando `php artisan app:production-readiness` forman parte de la base integrada. La política CSP permanece aplazada hasta definir una configuración compatible con Filament/Livewire y probarla.

La documentación operativa vigente es [Preparación para producción](production-readiness.md). CI no verifica la configuración real del dominio, certificado, SMTP, workers, backups, restauración ni monitoreo.

## Internacionalización

Español e inglés son los idiomas iniciales. El registro de locales permite añadir idiomas posteriores mediante configuración y catálogos de traducción. El contenido comercial escrito por administradores (nombres/descripciones de productos, páginas y banners) no se traduce automáticamente.

## Proceso de entrega

Cada cambio se desarrolla en una rama independiente. Revisar `main`, CI y documentación antes de implementar; añadir pruebas; ejecutar suite, auditorías y build; abrir PR. No integrar a `main` sin aprobación explícita.
