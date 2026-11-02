# Dubai Shop

Tienda online premium construida con Laravel, Livewire y FilamentPHP.

## Stack
- PHP 8.4.1+
- Laravel 13
- Filament 5
- Livewire utilizado por Filament 5; la tienda pública actual utiliza Blade y controladores Laravel
- Tailwind CSS 4
- Vite 8

## Estado del proyecto
El proyecto cuenta con catálogo, carrito, checkout, pedidos, inventario, pagos manuales, tarifas de envío, CMS y marca Clásico Dubai. Las Fases 1–13 están integradas. La Fase 14 (operación logística y seguimiento de entregas) está en desarrollo en una rama independiente. Consulta el [estado del proyecto](docs/project-status.md) y la [guía de notificaciones](docs/transactional-notifications.md).

Consulta:
- [Arquitectura técnica](docs/architecture.md)
- [Modelo de dominio](docs/domain-model.md)

## Requisitos
PHP 8.4.1+, Composer 2, Node.js 22+ y npm.

## Instalación local
```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura la conexión de base de datos en .env
php artisan migrate
npm install
npm run build
php artisan serve
```

Panel administrativo: /admin.

## Desarrollo
```bash
npm run dev
php artisan test
```

## Calidad continua
GitHub Actions ejecuta validación de Composer, pruebas PHP y compilación frontend en pushes a ramas de trabajo y pull requests hacia main.

## Guía de contribución
- No trabajar directamente sobre main.
- Crear una rama por fase o cambio.
- Mantener reglas comerciales fuera de componentes de interfaz.
- Añadir pruebas para cada regla de negocio nueva.
- No integrar cambios sin revisión y aprobación explícita.
