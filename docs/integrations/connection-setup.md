# Puesta en marcha: Dubai Shop ↔ SocialPublicator

Esta guía describe cómo conectar ambas aplicaciones en producción y habilitar la integración gradualmente. Completar estos pasos no activa automáticamente la publicación social.

> **Seguridad:** nunca guardes tokens, secretos HMAC ni credenciales de bots en Git, issues, logs compartidos o chats. Usa el gestor de secretos del despliegue.

## 1. Arquitectura y responsabilidades

- Dubai Shop es la fuente de verdad del catálogo publicado, variantes, precios de venta, almacenes y disponibilidad.
- SocialPublicator consume la API y mantiene un espejo en sus tablas de catálogo externo. No modifica el inventario local ni recibe costos de adquisición.
- La consulta de catálogo usa HTTPS y un Bearer token dedicado con capacidad catalog:read.
- Los cambios se envían mediante webhooks HMAC-SHA256. Dubai Shop conserva eventos en un outbox transaccional; SocialPublicator los acepta en un inbox idempotente por event_id.
- Las publicaciones se crean mediante el flujo y los bots existentes de SocialPublicator.

## 2. Requisitos previos

- Ambas aplicaciones desplegadas y accesibles mediante URLs públicas HTTPS con certificados válidos.
- Migraciones aplicadas y acceso al gestor de secretos de ambos entornos.
- Scheduler y workers de Laravel persistentes en ambas aplicaciones.
- Bots y destinos sociales configurados y probados por separado.
- Si se utiliza Cloudflare Tunnel, usar los hostnames HTTPS públicos; nunca localhost, IP privada o el servidor de desarrollo como URL remota.
- No utilizar php artisan serve como servidor de producción.

## 3. Emitir el token API en Dubai Shop

En el servidor de Dubai Shop, desde la raíz del proyecto:

~~~bash
php artisan social-publicator:token --name="SocialPublicator" --expires=365
~~~

El comando muestra el token en claro una sola vez. Cópialo directamente al gestor de secretos de SocialPublicator como DUBAI_SHOP_API_TOKEN.

- Debe tener únicamente la capacidad catalog:read.
- No reutilices tokens de usuarios ni de bots.
- No guardes el valor en archivos versionados o logs.
- Programa su renovación antes de los 365 días.
- Si se compromete, emite otro token y revoca el anterior mediante el campo revoked_at de su registro en integration_api_tokens.

## 4. Configurar SocialPublicator

En el entorno de SocialPublicator:

~~~dotenv
DUBAI_SHOP_API_BASE_URL=https://tu-dominio-publico-dubai-shop
DUBAI_SHOP_API_TOKEN=<TOKEN_GENERADO_EN_DUBAI_SHOP>
DUBAI_SHOP_WEBHOOK_KEYS='{"key-2026-01":"<SECRETO_HMAC_ALEATORIO_LARGO>"}'
DUBAI_SHOP_WEBHOOK_TOLERANCE_SECONDS=300
DUBAI_SHOP_TIMEOUT=15
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false
~~~

DUBAI_SHOP_API_BASE_URL es el origen de Dubai Shop, sin /api al final. DUBAI_SHOP_WEBHOOK_KEYS es un mapa JSON de key ID a secreto. El ID y el secreto deben coincidir exactamente con los configurados en Dubai Shop. Genera un secreto aleatorio criptográficamente seguro y suficientemente largo. Mantén el flag de campañas en false durante la configuración.

## 5. Configurar Dubai Shop

En el entorno de Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_URL=https://tu-dominio-publico-socialpublicator/api/integrations/dubai-shop/v1/events
SOCIAL_PUBLICATOR_WEBHOOK_KEY_ID=key-2026-01
SOCIAL_PUBLICATOR_WEBHOOK_SECRET=<EL_MISMO_SECRETO_HMAC_DE_SOCIALPUBLICATOR>
SOCIAL_PUBLICATOR_WEBHOOK_TIMEOUT=15
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false
~~~

El endpoint receptor es POST /api/integrations/dubai-shop/v1/events. La firma HMAC-SHA256 se calcula sobre {timestamp}.{raw-request-body}. El receptor valida los encabezados X-DubaiShop-Key-Id, X-DubaiShop-Timestamp y X-DubaiShop-Signature. No transformes el cuerpo JSON en un proxy intermedio.

Mantén ambos interruptores de envío en false hasta terminar las pruebas.

## 6. Aplicar configuración

Después de guardar las variables en cada aplicación, ejecutar en ambas:

~~~bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
~~~

No imprimas valores secretos al comprobar la configuración. Reinicia los procesos persistentes según el gestor de procesos del servidor.

## 7. Sincronizar el catálogo

En SocialPublicator:

~~~bash
php artisan social-publicator:sync-dubai-catalog
~~~

Verifica en Filament o en la base de datos:

- Productos publicados y visibles esperados.
- Variantes activas.
- Precios y códigos de moneda.
- Imágenes externas accesibles.
- Almacenes activos y habilitados para fulfillment online.
- Productos retirados del catálogo remoto marcados inactivos en el espejo.

La sincronización no debe alterar productos ni inventario locales de SocialPublicator. Si falla, revisa URL base, token, capacidad catalog:read, HTTPS y logs antes de continuar.

## 8. Scheduler y workers persistentes

En ambos servidores, configura el scheduler de Laravel. Con cron:

~~~cron
* * * * * cd /ruta/a/la-aplicacion && php artisan schedule:run >> /dev/null 2>&1
~~~

Ejecuta workers persistentes bajo Supervisor, systemd u otro gestor de procesos:

~~~bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
~~~

Adapta los parámetros al driver y a la duración real de los trabajos. El worker debe reiniciarse automáticamente y no depender de una sesión SSH. Verifica scheduler, workers, logs y failed jobs. En SocialPublicator, la reconciliación del catálogo se programa cada hora cuando la URL y el token están configurados.

## 9. Preparar una campaña de prueba

En Filament de SocialPublicator, abre **Integraciones → Campañas sociales**:

1. Crea una campaña y déjala inactiva.
2. Selecciona pocos productos o variantes del catálogo externo.
3. Selecciona un único almacén elegible.
4. Configura formato agrupado o individual, límites, intervalo y ventana horaria.
5. Define preferencias de agotado, reposición y cambios de precio.
6. Selecciona un único grupo/destino de prueba que controles.

Con la campaña inactiva y el flag de despacho desactivado no deben crearse publicaciones para redes sociales.

## 10. Activación gradual

### Etapa A: catálogo

Mantén ambos flags en false. Ejecuta y valida la sincronización inicial.

### Etapa B: eventos firmados

Cuando el catálogo y la conectividad estén correctos, habilita en Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=true
~~~

Confirma que los eventos llegan al inbox de SocialPublicator, que las firmas se validan y que cada event_id se procesa una sola vez. HTTP 202 confirma aceptación en el inbox, no que una publicación social haya sido enviada.

### Etapa C: una campaña controlada

Sólo después de validar la etapa B, habilita en SocialPublicator:

~~~dotenv
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=true
~~~

Activa únicamente la campaña de prueba. Observa la revalidación del producto, precio y disponibilidad en Dubai Shop, la publicación creada y la ejecución del bot por destino. Amplía productos, almacenes y destinos gradualmente.

## 11. Monitoreo y diagnóstico

**Dubai Shop**
- integration_event_outbox: pendientes, intentos, último error y fallos terminales (failed_at).
- Logs de Laravel, failed jobs, scheduler y worker.

**SocialPublicator**
- integration_event_inbox: eventos recibidos/procesados y errores.
- Espejo de catálogo y almacenes externos.
- Triggers, grupos de selección, publicaciones y ejecuciones de bots.
- Logs, failed jobs, scheduler y workers.

Un evento aceptado, trigger creado o selección planificada no equivale a una publicación exitosa. Confirma el estado de la ejecución del bot y, cuando exista, el identificador externo.

Para reencolar un evento en estado terminal de fallo en Dubai Shop, después de corregir la causa:

~~~bash
php artisan social-publicator:requeue-outbox <event-uuid>
php artisan social-publicator:dispatch-outbox
~~~

## 12. Pausa de emergencia

Para detener nuevas entregas de eventos, configura en Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false
~~~

Para detener selección y despacho de campañas, configura en SocialPublicator:

~~~dotenv
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false
~~~

Aplica la configuración y limpia la caché. Si un bot ya ejecuta una publicación, detén también el worker/bot mediante su mecanismo operativo. Los flags no eliminan eventos, triggers, selecciones o publicaciones pendientes, ni garantizan cancelar una entrega ya iniciada.

## 13. Rotación de credenciales

**HMAC:** agrega primero un nuevo key ID y secreto a DUBAI_SHOP_WEBHOOK_KEYS en SocialPublicator, conserva temporalmente la clave anterior, configura el nuevo ID/secreto en Dubai Shop, verifica entregas y retira la clave anterior cuando ya no se use.

**Token API:** emite un token nuevo en Dubai Shop, actualiza DUBAI_SHOP_API_TOKEN en SocialPublicator, ejecuta una sincronización de prueba y revoca el token anterior.

## 14. Checklist de producción

- [ ] Ambas URLs públicas responden por HTTPS.
- [ ] Token dedicado con catalog:read configurado.
- [ ] Key ID y secreto HMAC idénticos en ambos entornos.
- [ ] Flags inicialmente desactivados.
- [ ] Configuración cacheada y workers reiniciados.
- [ ] Reconciliación inicial validada.
- [ ] Scheduler y workers persistentes activos en ambas apps.
- [ ] Campaña de prueba inactiva con un almacén y destino controlados.
- [ ] Webhooks recibidos y procesados idempotentemente.
- [ ] Publicación y ejecución del bot de prueba verificadas.
- [ ] Procedimiento de pausa conocido.
- [ ] Secretos fuera del repositorio y de los logs.

## Referencias

- [Contrato API v1 de Dubai Shop](./social-publicator-api-v1.md)
- [Guía operativa de SocialPublicator](https://github.com/renedroyd/SocialPublicator/blob/main/docs/integrations/dubai-shop-operations.md)
