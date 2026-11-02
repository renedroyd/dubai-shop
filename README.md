# Dubai Shop

Tienda online premium construida con Laravel, Livewire y FilamentPHP.

## Stack
- PHP 8.4.1+
- Laravel 13
- Filament 5
- Livewire (integración directa planificada para la interfaz pública)
- Tailwind CSS 4
- Vite 8

## Estado del proyecto
El proyecto ya cuenta con catálogo, carrito, checkout, pedidos, inventario, pagos manuales y tarifas de envío. La Fase 10 está integrada y la Fase 11 está en revisión en la PR #17; consulta el estado verificado y la hoja de ruta en [Estado del proyecto](docs/project-status.md).

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
