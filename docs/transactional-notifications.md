# Notificaciones transaccionales

## Eventos disponibles

Las notificaciones se disparan después de confirmar la transacción que modifica el pedido:

| Evento | Clave |
|---|---|
| Pedido recibido | `order_received` |
| Pago aprobado | `payment_approved` |
| Pago rechazado | `payment_rejected` |
| Pedido confirmado | `order_confirmed` |
| Pedido en preparación | `order_processing` |
| Pedido enviado | `order_shipped` |
| Pedido entregado | `order_delivered` |
| Pedido cancelado | `order_cancelled` |

## Plantillas

Las plantillas iniciales se crean con:

```bash
php artisan db:seed --class=NotificationTemplateSeeder
```

También se incluyen en `php artisan db:seed`. El seeder es no destructivo: crea las plantillas que faltan y no sobrescribe los textos o estados que el administrador haya personalizado.

En Filament, abre **Comunicación → Plantillas de correo** para editar el nombre interno, asunto, cuerpo y estado de cada plantilla. El contenido se envía como texto plano.

Variables admitidas:

- `{order_number}`
- `{customer_name}`
- `{order_status}`
- `{payment_status}`
- `{order_total}`
- `{currency}`
- `{store_name}`

## Configuración de correo y colas

Configura el transporte de Laravel en el entorno privado de despliegue. Las credenciales SMTP no deben añadirse al repositorio. Las variables estándar incluyen `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`.

La conexión de cola predeterminada es `database`. Asegúrate de aplicar las migraciones y mantener un worker activo:

```bash
php artisan migrate --force
php artisan queue:work database --queue=default --tries=3 --backoff=60
```

En producción, ejecuta el worker mediante un supervisor de procesos y reinícialo después de desplegar código nuevo:

```bash
php artisan queue:restart
```

En desarrollo, `MAIL_MAILER=log` escribe los correos en el log de Laravel; no los entrega a una bandeja real.

## Seguimiento y reintentos

En Filament, abre **Comunicación → Registro de correos**. Se guardan destinatario, evento, asunto, estado, número de intentos, fecha de envío y mensaje de error cuando el transporte falla.

Los trabajos se reintentan automáticamente hasta tres veces. Un administrador también puede volver a encolar un registro fallido desde la tabla. Los trabajos que agoten sus intentos permanecen en `failed_jobs`, desde donde se pueden inspeccionar y reintentar con los comandos estándar de Laravel.

Los registros con estado **omitido** indican que la plantilla no existe o está desactivada; no se envían deliberadamente.
