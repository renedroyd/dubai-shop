# Preparación para producción — Fase 8

## Requisitos previos
- PHP 8.4.1 o superior y extensiones requeridas por Laravel.
- Base de datos persistente y respaldada.
- Node.js 22 para compilar assets durante el despliegue.
- Dominio con HTTPS y certificado válido.
- SMTP transaccional para futuras notificaciones; esta entrega no envía correos de pedidos todavía.

## Variables de entorno esenciales
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio`
- `APP_KEY` única y persistente.
- Credenciales de base de datos seguras.
- `SESSION_SECURE_COOKIE=true` al servir exclusivamente por HTTPS.
- `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` para bootstrap del administrador.

No incluyas secretos en Git ni reutilices credenciales de pruebas.

## Despliegue inicial
1. Instalar dependencias PHP con `composer install --no-dev --prefer-dist --optimize-autoloader`.
2. Compilar frontend con `npm ci && npm run build`.
3. Ejecutar `php artisan migrate --force`.
4. Crear el administrador con las variables ADMIN_* y `php artisan db:seed --force`.
5. Ejecutar `php artisan storage:link` para imágenes públicas del catálogo.
6. Configurar permisos de escritura para `storage` y `bootstrap/cache`.
7. Ejecutar `php artisan optimize` después de configurar el entorno.
8. Configurar respaldos automáticos de base de datos y archivos.

## Seguridad y datos
- El panel Filament requiere usuarios con `is_admin=true`.
- Las páginas de carrito, checkout y confirmación de pedido llevan `noindex,nofollow`.
- Las confirmaciones de pedido usan token aleatorio, respuesta `private, no-store` y política `no-referrer`.
- Los comprobantes Zelle se guardan en el disco privado local; no los muevas al disco público.
- El middleware añade X-Content-Type-Options, X-Frame-Options, Referrer-Policy y Permissions-Policy.
- `robots.txt` excluye el panel administrativo y declara el sitemap.
- Verifica que el almacenamiento privado de comprobantes esté incluido en los respaldos con acceso restringido.

## Operación
- `/up` es el endpoint de salud de Laravel.
- `/sitemap.xml` publica URLs indexables del catálogo.
- Configura monitorización de errores, latencia, uso de disco y disponibilidad de base de datos.
- Define una política de retención y eliminación de comprobantes de pago.
- Prueba el proceso de restauración de respaldos antes de abrir la tienda.

## Límites comerciales pendientes
- Las tarifas de envío y los impuestos no se calculan todavía; el checkout indica que se confirmarán por separado.
- No hay devoluciones ni reembolsos automatizados.
- No hay notificaciones por correo ni webhooks de pasarelas externas.
- Zelle continúa siendo revisión manual; efectivo contra entrega requiere confirmar el cobro en Filament.
- Antes de activar ventas reales, configura y valida datos de Zelle, moneda, zonas de entrega, tarifas, impuestos y políticas de cancelación.
