# Preparación para producción — Fase 16

Esta guía complementa las pruebas automatizadas. Un CI verde no significa que la tienda esté lista para recibir pedidos reales: HTTPS, secretos, SMTP, workers, copias de seguridad, restauración y monitoreo deben configurarse y probarse en el entorno de despliegue.

## Estado de seguridad cubierto por el código

- Filament valida el acceso mediante `User::canAccessPanel()` y exige `is_admin=true`. Las cuentas de clientes no deben recibir permisos administrativos.
- Los comprobantes Zelle se validan como JPG/JPEG/PNG/PDF con un máximo de 5 MB, se guardan en el disco privado local (`storage/app/private`) y se descargan desde una acción protegida del panel. No cambiar el disco a `public` ni exponer rutas directas.
- Las imágenes de productos, categorías y banners se validan como imágenes y tienen un límite de 5 MB; la importación de catálogo acepta archivos de hasta 10 MB.
- El enlace público de pedido requiere un token aleatorio cuya huella SHA-256 se almacena en la base de datos. La respuesta se marca `private, no-store` y `no-referrer`.
- Las rutas de registro, login, recuperación, restablecimiento y checkout tienen límites de solicitudes. Las operaciones de carrito y el cambio de idioma también están limitados.
- Se aplican cabeceras contra MIME sniffing, framing, filtración de referencias y permisos del navegador. HSTS se emite únicamente en solicitudes HTTPS.
- Los proxies inversos no se consideran confiables por defecto. `TRUSTED_PROXIES` acepta una lista explícita separada por comas. La excepción `TRUSTED_PROXIES=*` solo se admite, por sí sola, cuando `APP_ENV=local`; en cualquier otro entorno se rechaza.
- CI ejecuta `composer audit` y `npm audit --audit-level=high` además de las pruebas y el build; la revisión de esta fase no reporta avisos Composer y la auditoría npm pasó.

La política CSP no se activa en esta entrega: antes debe definirse y probarse una estrategia compatible con los scripts de Filament/Livewire y los recursos reales del storefront; una política improvisada podría romper el panel o debilitarse mediante excepciones amplias.

## Variables obligatorias del entorno de producción

Configura estas variables directamente en el proveedor de despliegue o en el gestor de secretos. Nunca las subas al repositorio:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio-real
APP_KEY=<clave-unica-y-persistente>
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true

TRUSTED_PROXIES=<IPs-o-CIDR-reales-del-proxy>
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=<servidor-smtp>
MAIL_PORT=587
MAIL_USERNAME=<usuario>
MAIL_PASSWORD=<secreto>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=<correo-del-dominio>
MAIL_FROM_NAME="${APP_NAME}"
```

Notas:
- Si el servidor recibe tráfico directamente, deja `TRUSTED_PROXIES` vacío. Si hay proxy, CDN o túnel, configura solo las IP/CIDR de los saltos que realmente controlas. No uses `*` salvo que la aplicación sea inaccesible desde cualquier origen que pueda falsificar cabeceras.
- Activa `SESSION_SECURE_COOKIE=true` solo cuando todo el tráfico público use HTTPS y el proxy esté configurado correctamente.
- Conserva el mismo `APP_KEY` entre despliegues. Cambiarla invalida sesiones y datos cifrados existentes.
- No uses `QUEUE_CONNECTION=sync` en producción. Debe existir un worker supervisado y reiniciable.
- Configura Zelle, remitente de correo, base de datos y credenciales de almacenamiento con valores reales; no reutilices datos de pruebas.

## Verificación previa automatizada

Antes de poner la aplicación en producción, ejecuta:

```bash
php artisan app:production-readiness
```

El comando comprueba `APP_ENV`, modo debug, URL HTTPS, clave de aplicación, atributos de cookies/sesión, drivers persistentes de sesión/cache/cola, transporte y remitente de correo, configuración explícita de proxies, conexión a base de datos y permisos de los directorios de almacenamiento privado y público. Devuelve un código de salida distinto de cero si detecta una configuración insegura o incompleta; corrige los puntos indicados y vuelve a ejecutarlo.

La configuración `TRUSTED_PROXIES` ahora se carga desde `config/app.php`, de modo que queda incluida en `php artisan config:cache`; no se lee directamente desde `env()` durante el registro del middleware. Si no existe un proxy inverso, deja la variable vacía. El comodín `*` se permite únicamente en `APP_ENV=local` y no puede combinarse con IP/CIDR explícitas; `**` se rechaza siempre.

Este preflight valida configuración y conectividad básica a la base de datos, pero no puede confirmar que haya un worker activo, que SMTP entregue mensajes, que el certificado HTTPS sea válido desde Internet, ni que existan backups restaurables. Esas verificaciones siguen siendo operativas y externas.

## Despliegue reproducible

Desde el directorio de la versión que se va a publicar:

1. Instalar dependencias de producción: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
2. Instalar dependencias frontend y compilar: `npm ci && npm run build`.
3. Confirmar que el archivo `.env`/secretos corresponden al entorno y que los directorios `storage` y `bootstrap/cache` son escribibles por el usuario del proceso PHP.
4. Ejecutar migraciones: `php artisan migrate --force`.
5. Crear el enlace público si aún no existe: `php artisan storage:link`. Los comprobantes de pago permanecen en el disco privado.
6. Generar cachés: `php artisan optimize`.
7. Reiniciar los workers de cola: `php artisan queue:restart`.
8. Ejecutar las comprobaciones de humo descritas abajo antes de retirar el modo mantenimiento.

No ejecutar `db:seed` en cada despliegue como rutina. Los seeders operativos deben ejecutarse deliberadamente y revisarse antes de tocar datos reales.

## Procesos persistentes

- Mantener al menos un worker de cola activo con Supervisor, systemd o el mecanismo equivalente del proveedor. Supervisar reinicios, memoria, jobs fallidos y antigüedad de la cola.
- Mantener el scheduler de Laravel activo mediante cron o el programador del proveedor si se incorporan tareas programadas.
- Configurar rotación y retención de logs. No registrar contraseñas, tokens de pedido, comprobantes ni secretos.
- El endpoint `/up` confirma que Laravel responde; no sustituye una comprobación externa de base de datos, cola, SMTP o almacenamiento.

## Copias de seguridad y recuperación

Antes del lanzamiento debe existir una política automatizada que cubra:
- Base de datos completa y consistente.
- `storage/app/private`, incluidos comprobantes de pago y archivos privados.
- `storage/app/public` y cualquier otro archivo subido que no pueda reconstruirse.
- Configuración y secretos respaldados de forma cifrada y con acceso separado del repositorio.

Definir frecuencia, retención, cifrado, ubicación externa y responsables. Como mínimo, realizar una copia antes de cada despliegue con migraciones. Probar una restauración completa en un entorno aislado y documentar tiempo de recuperación y pérdida máxima de datos aceptables. Una copia creada pero nunca restaurada no se considera verificada.

## Monitoreo y respuesta a incidentes

Configurar alertas externas para:
- Disponibilidad HTTP y errores 5xx.
- Fallos de conexión a base de datos y almacenamiento.
- Jobs fallidos y cola acumulada.
- Espacio libre en disco y crecimiento de logs.
- Fallos de envío SMTP y retrasos de notificaciones.
- Actividad administrativa anómala y múltiples intentos de autenticación.

Documentar quién recibe las alertas, cómo deshabilitar temporalmente el checkout, cómo rotar credenciales y cómo preservar logs/evidencias. Establecer una política de retención y eliminación de comprobantes conforme a las necesidades operativas y legales.

## Comprobaciones de humo tras desplegar

- [ ] La web responde mediante HTTPS y redirige HTTP a HTTPS en el proxy/servidor.
- [ ] La respuesta HTTPS incluye HSTS; una petición HTTP no debe emitir HSTS.
- [ ] `APP_DEBUG=false` y los errores no revelan trazas ni configuración.
- [ ] Un administrador puede acceder a `/admin`; una cuenta de cliente no.
- [ ] Registro, login, recuperación y cierre de sesión funcionan.
- [ ] Catálogo, carrito y checkout funcionan en las monedas y métodos habilitados.
- [ ] Un comprobante Zelle se guarda en almacenamiento privado y solo un administrador autorizado puede descargarlo.
- [ ] Las notificaciones se procesan con SMTP real y worker activo.
- [ ] Los pedidos y sus enlaces privados no se almacenan en cachés públicas.
- [ ] La copia de seguridad reciente existe y la restauración se ha probado.

## Rollback

Mantener disponible la versión anterior del código y sus assets. Si falla una comprobación de humo, detener el tráfico de compra o activar mantenimiento, volver a la versión anterior, reconstruir cachés y reiniciar workers. No revertir migraciones destructivas ni restaurar una base de datos antigua automáticamente: primero evaluar si ya se escribieron pedidos o pagos nuevos. Las migraciones deben diseñarse de forma compatible con la versión anterior siempre que sea posible.

## Pendientes que requieren operación externa

El repositorio no puede verificar por sí solo el dominio, certificado, reglas del proxy, credenciales SMTP, supervisión de workers, backups reales, restauración, monitoreo ni políticas legales. Estos puntos deben marcarse como completados solo después de probarlos en el entorno de despliegue.