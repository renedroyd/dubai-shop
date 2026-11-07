# Dubai Shop — Clásico Dubai

Tienda online premium construida con Laravel 13 y Filament 5, con escaparate público Blade y una identidad visual negro obsidiana y dorado champagne.

## Estado actual

**Corte documental: 6 de noviembre de 2026.** Las Fases 1–16 y las mejoras posteriores PR #29–37 están integradas. Esta entrega incorpora el control de acceso administrativo por roles y permisos (PR #38).

La última entrega de producto añade RBAC al panel Filament. El asistente de banners continúa generando prompts para usar manualmente en ChatGPT; no utiliza la API de OpenAI ni requiere claves o facturación de API.

Consulta primero:
- [Guía de configuración de la tienda y del POS](docs/store-and-pos-setup.md)\n- [Estado actual del proyecto](docs/project-status.md)
- [Hoja de ruta](ROADMAP.md)
- [Arquitectura técnica](docs/architecture.md)
- [Modelo de dominio](docs/domain-model.md)
- [Preparación para producción](docs/production-readiness.md)
- [Operación logística y seguimiento](docs/logistics-and-tracking.md)
- [Notificaciones transaccionales](docs/transactional-notifications.md)
- [Asistente de IA para banners](docs/ai-banner-workflow.md)
- [Control de acceso administrativo](docs/admin-access-control.md)

## Stack

- PHP 8.4+ (CI valida PHP 8.4 y 8.5).
- Laravel 13.
- Filament 5 para administración.
- Blade y controladores Laravel para el escaparate público.
- Livewire como parte de Filament 5; no describir el storefront como una aplicación Livewire.
- Tailwind CSS 4 y Vite 8.
- Eloquent y migraciones Laravel.
- GitHub Actions para pruebas, auditorías y compilación frontend.

## Funcionalidades principales

- Catálogo con categorías, marcas, productos, variantes, imágenes, búsqueda, filtros y disponibilidad.
- Importación de categorías, marcas y productos desde Excel/CSV.
- Carrito y checkout de invitado, pedidos, inventario y reservas.
- Pagos manuales Zelle (USD) y efectivo contra entrega (según configuración).
- Tarifas de envío configurables; operación inicial restringida a Guantánamo y compromiso de 24 horas.
- Cuentas de clientes, historial de pedidos y direcciones guardadas, manteniendo la compra como invitado.
- Notificaciones transaccionales editables desde Filament y procesadas mediante colas.
- Gestión logística, repartidores, seguimiento privado e intentos de entrega.
- CMS de banners, páginas y menús.
- Diseño público y panel administrativo con identidad premium negro/dorado.
- Español e inglés, con posibilidad de incorporar idiomas mediante el registro de locales y sus traducciones.
- Asistente para generar prompts de imágenes de banners con una cuenta gratuita de ChatGPT.

## Requisitos

PHP 8.4+, Composer 2, Node.js 22+ y npm. La versión de base de datos debe ser compatible con Laravel 13 y con los drivers configurados en el proyecto.

## Instalación local

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura la base de datos y demás variables en .env
php artisan migrate
npm ci
npm run build
php artisan serve
```

Panel administrativo: `/admin`.

Para desarrollo:
```bash
npm run dev
php artisan test
```

## Comprobaciones de preparación para producción

Antes de publicar, revisa [docs/production-readiness.md](docs/production-readiness.md) y ejecuta:

```bash
php artisan app:production-readiness
composer audit
npm audit --audit-level=high
php artisan test
npm run build
```

El preflight valida configuración y conectividad básica; no sustituye la prueba real de HTTPS, SMTP, workers, backups/restauración, monitoreo ni aceptación funcional. No uses `php artisan serve` como servidor de producción.

## Contribución y flujo de trabajo

- No trabajar directamente sobre `main`.
- Crear una rama por fase o cambio.
- Revisar estado del repositorio y CI antes de implementar.
- Mantener reglas comerciales fuera de componentes de interfaz.
- Añadir pruebas para reglas de negocio y regresiones.
- Ejecutar pruebas, auditorías y build antes de abrir PR.
- No integrar cambios en `main` sin aprobación explícita.
