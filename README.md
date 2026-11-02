# Dubai Shop

Tienda online premium construida con Laravel 13, Filament 5, Blade, Tailwind CSS 4 y Vite 8. El storefront admite compra como invitado; Filament proporciona la administración.

## Stack
- PHP 8.4.1+
- Laravel 13
- Filament 5
- Blade, Tailwind CSS 4 y Vite 8
- Livewire está disponible como dependencia de Filament; la integración directa en el storefront sigue pendiente de decisión/implementación.

## Estado del proyecto
El núcleo de catálogo, carrito, checkout, pedidos, inventario, pagos manuales y tarifas de envío para Cuba está implementado. La tienda continúa en desarrollo y requiere configuración comercial real, pruebas de aceptación y trabajo de preparación para producción.

Consulta:
- [Estado actual, brechas y hoja de ruta](docs/project-status.md)
- [Arquitectura técnica](docs/architecture.md)
- [Modelo de dominio](docs/domain-model.md)
- [Checkout, pedidos y pagos](docs/checkout-orders-payments.md)

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
GitHub Actions valida Composer, ejecuta pruebas Laravel en PHP 8.4 y 8.5 y compila los assets frontend en pushes a ramas de trabajo y pull requests hacia main.

## Guía de contribución
- No trabajar directamente sobre main.
- Crear una rama por fase o cambio.
- Mantener reglas comerciales fuera de componentes de interfaz.
- Añadir pruebas para cada regla de negocio nueva.
- No integrar cambios sin revisión y aprobación explícita.
