# Contrato de integración: Dubai Shop → SocialPublicator

**Estado:** contrato v1 implementado y validado mediante CI coordinado; pendiente piloto productivo y procedimiento de recuperación de identidad de instalaciones clonadas  
**Versión del contrato:** v1  
**Fuente de verdad:** Dubai Shop  
**Consumidor y motor de publicación:** SocialPublicator

## 1. Objetivo y límites

Dubai Shop (Clásico Dubai) es la autoridad exclusiva sobre productos, variantes, precios, almacenes y existencias. SocialPublicator consulta el catálogo y recibe eventos para preparar campañas y publicaciones. No se conectarán directamente las bases de datos.

Los endpoints, Transactional Outbox, identidad estable de instalación, firma HMAC v2 y consumidor SocialPublicator ya están implementados. Los ejemplos y reglas de este documento describen el contrato operativo; las pruebas de contrato compartidas entre repositorios validan la forma del evento v1.

La integración no debe modificar los flujos existentes de POS, checkout, inventario ni publicación social. Los cambios se añadirán de forma compatible y gradual.

## 2. Identidad y elegibilidad comercial

- Cada producto, variante y almacén tendrá un identificador externo estable, opaco y no secuencial (UUID). No se expondrán claves internas de base de datos como identificadores públicos.
- Se sincronizarán únicamente productos activos y visibles para el canal público.
- Las variantes inactivas no se ofrecerán. Un producto con variantes se considerará publicable si al menos una variante activa tiene disponibilidad en los almacenes considerados.
- Los productos simples se representarán como un artículo sin variante.
- Los cambios de SKU o nombre no cambiarán el identificador externo.
- No se enviarán costos de adquisición, precio mínimo interno de venta, datos de clientes, pedidos, pagos ni información privada.

## 3. Disponibilidad y almacenes

La disponibilidad por almacén se calculará con la regla existente:

`available_quantity = max(0, quantity_on_hand - quantity_reserved - quantity_blocked)`

Solo se considerarán almacenes activos y habilitados para abastecimiento online.

Cada campaña de SocialPublicator podrá elegir una de estas modalidades:

- `all`: todos los almacenes activos habilitados para abastecimiento online. La incorporación futura de otro almacén elegible se reflejará automáticamente.
- `selected`: solo los almacenes seleccionados para esa campaña. La selección se validará contra los almacenes elegibles.

Dubai Shop enviará disponibilidad desglosada por almacén. SocialPublicator calculará el total para cada campaña según su modalidad. Por tanto, no se anunciará agotamiento en una campaña si queda disponibilidad en cualquiera de los almacenes incluidos en ella, aunque otros almacenes ajenos a esa campaña tengan stock.

## 4. Precio y monedas

- Se utilizará el precio vigente de venta del producto o de la variante cuando tenga precio propio; en caso contrario, se aplicará el precio del producto.
- Los importes se transmitirán como enteros en unidades menores, acompañados de código ISO 4217 (por ejemplo, CUP, USD o EUR). Nunca se usarán valores de punto flotante.
- No se convertirá ni mezclará monedas automáticamente.
- El costo de adquisición y el precio mínimo interno de venta son datos privados y no forman parte del contrato.
- Un cambio de precio generará un evento para que SocialPublicator programe una publicación de actualización inmediata, fuera del ciclo ordinario de publicaciones.

## 5. API de lectura implementada

Base path versionado:

`/api/integrations/social-publicator/v1`

| Método | Ruta | Propósito |
|---|---|---|
| GET | `/items` | Catálogo paginado e incremental de productos y variantes publicables. |
| GET | `/items/{external_id}` | Obtener el estado vigente de un artículo. |
| GET | `/warehouses` | Consultar almacenes elegibles y sus identificadores externos. |

La primera sincronización realizará una lectura completa paginada. Las siguientes utilizarán cursor opaco para continuar desde el último punto confirmado. El consumidor podrá ejecutar una reconciliación completa periódica para reparar eventos perdidos.

### Representación conceptual de un artículo

```json
{
  "contract_version": "v1",
  "external_id": "UUID",
  "item_type": "variant",
  "product_external_id": "UUID",
  "sku": "SKU-001",
  "name": "Nombre comercial",
  "description": "Descripción pública",
  "image_url": "https://store.example.test/storage/catalog/item.jpg",
  "active": true,
  "visible": true,
  "price": {
    "amount_minor": 125000,
    "currency": "CUP"
  },
  "availability": {
    "warehouses": [
      {
        "warehouse_external_id": "UUID",
        "available_quantity": 8
      }
    ]
  },
  "revision": 12,
  "updated_at": "2026-11-09T12:00:00Z"
}
```

El ejemplo es ilustrativo. La serialización, paginación y disponibilidad por almacén se validan mediante pruebas de integración del API.

## 6. Eventos salientes

Dubai Shop publicará eventos hacia un endpoint configurado de SocialPublicator. El mecanismo de entrega utilizará Transactional Outbox para guardar el cambio de negocio y el evento en la misma transacción; un worker enviará el evento con reintentos.

Eventos v1:

- `catalog.item_published`
- `catalog.item_unpublished`
- `catalog.item_updated`
- `inventory.changed`
- `price.changed`
- `warehouse.updated`

El evento `inventory.changed` contendrá la disponibilidad por almacén. SocialPublicator derivará las transiciones de agotado y reposición para cada campaña, según sus almacenes seleccionados. Así se evitan falsos agotamientos cuando hay stock en un almacén excluido de la campaña y falsos avisos de reposición.

### Envoltorio de evento

```json
{
  "event_id": "UUID",
  "event_type": "inventory.changed",
  "contract_version": "v1",
  "aggregate_external_id": "UUID",
  "revision": 13,
  "occurred_at": "2026-11-09T12:01:00Z",
  "data": {
    "item_external_id": "UUID",
    "availability": {
      "warehouses": [
        {
          "warehouse_external_id": "UUID",
          "available_quantity": 0
        }
      ]
    }
  }
}
```

Los eventos se identificarán por `event_id`; `revision` permitirá descartar eventos antiguos que lleguen fuera de orden. Un reintento conservará el mismo identificador y contenido.

## 7. Seguridad

- HTTPS obligatorio.
- API de lectura protegida con token Bearer exclusivo para esta integración, con permisos mínimos de lectura de catálogo, precios y disponibilidad.
- Los tokens se almacenarán hasheados en Dubai Shop; nunca se enviarán en URL ni se versionarán.
- Los webhooks utilizarán una clave HMAC independiente, firma SHA-256 del timestamp y el cuerpo HTTP original, identificador de clave y ventana de tolerancia contra replay.
- SocialPublicator validará firma, timestamp, tamaño del payload y duplicados antes de aceptar un evento.
- Se aplicarán límites de frecuencia, registro de auditoría y rotación de secretos.
- Las credenciales de WhatsApp/Facebook permanecerán en SocialPublicator y sus bots.

## 8. Fiabilidad y consistencia

- Outbox transaccional en Dubai Shop.
- Reintentos con backoff y registro de último error.
- Confirmación HTTP después de aceptar el evento para procesamiento; la recepción no equivale a publicación social completada.
- Idempotencia por `event_id` y revisión por artículo.
- Reconciliación completa periódica entre catálogo de origen y espejo externo.
- Comprobación del estado actual del artículo y su disponibilidad justo antes de publicar.
- Si un artículo se agota después de programarse, se excluirá o actualizará el grupo antes de enviarlo.

## 9. Reglas editoriales acordadas

- Reposición: publicar automáticamente la notificación de reposición y reincorporar el artículo a las campañas habituales.
- Cambio de precio: generar una publicación de actualización inmediata.
- Presentación: agrupar artículos por campaña y destino; dividir grupos cuando los límites de la plataforma lo requieran.
- Agotamiento: publicar una notificación por transición efectiva a disponibilidad cero dentro de cada campaña; excluir el artículo de los grupos de disponibilidad.
- Los eventos rápidos del mismo artículo se consolidarán para evitar mensajes obsoletos o repetidos. La política de agrupación y sus límites se configurarán en SocialPublicator.

## 10. Responsabilidades por aplicación

### Dubai Shop

- Exponer catálogo público autorizado, precios de venta y disponibilidad por almacén.
- Mantener identificadores externos estables.
- Registrar y entregar eventos desde Outbox.
- Gestionar tokens, claves, auditoría y estado de entrega.
- No gestionar campañas ni credenciales sociales.

### SocialPublicator

- Mantener un espejo separado del catálogo externo.
- No copiar artículos externos al modelo local `Product` ni modificar su stock, costos o precios locales.
- Administrar campañas, almacenes incluidos, selección y agrupación.
- Derivar agotamiento/reposición por campaña.
- Revalidar precios y disponibilidad antes de publicar.
- Utilizar el sistema existente de destinos, bots y ejecuciones idempotentes.

## 11. Secuencia de implementación y estado

1. Contrato y documentación (esta fase).
2. API de lectura, credenciales e identificadores externos en Dubai Shop.
3. Receptor de eventos, catálogo externo y reconciliación en SocialPublicator.
4. Configuración de campañas y almacenes.
5. Agrupación y selección inteligente.
6. Eventos de agotamiento, reposición y precio inmediato.
7. Entrega por los destinos y bots existentes.
8. Pruebas integrales y activación gradual.

Cada fase deberá incluir pruebas automatizadas y validación de CI antes de considerar su integración.


## 12. Validación de contrato entre repositorios

La suite de pruebas usa un fixture determinista de evento v1 de inventario en Dubai Shop y SocialPublicator. Dubai Shop comprueba que el Transactional Outbox emite el mismo sobre; SocialPublicator comprueba la autenticación HMAC v2, la persistencia del inbox y la aplicación de la instantánea de existencias por almacén. WBot dispone de un fixture de publicación v1 que también es verificado por el endpoint Bot API de SocialPublicator.

Cada repositorio ejecuta sus pruebas en su propio CI. La validación no requiere credenciales reales de redes sociales ni envía publicaciones a grupos o páginas reales.

## 13. Cloning and disaster-recovery policy

The installation UUID is intentionally persistent. Normal application startup, deployments, migrations, and ordinary database restores must never rotate it automatically.

### A. Clone that will become a separate installation

Run this only on the clone, after ensuring it is isolated from the source installation:

```bash
php artisan social-publicator:rotate-installation-identity --as-new-installation --confirm
```

The command performs one transaction that:

1. Generates a new installation UUID.
2. Sets pairing status to `pending`, which blocks all outbound event dispatch and delivery.
3. Revokes every existing Dubai Shop integration API token and creates a new read-only token with a 365-day expiry.
4. Generates a new webhook key ID and HMAC secret; the secret is encrypted at rest in the installation identity row.
5. Marks every not-yet-delivered outbox event as suppressed/terminal so historical events from the clone are not replayed into a new workspace.
6. Prints the new UUID, API token, webhook key ID, and webhook secret once. Store these securely; the plaintext credentials are not persisted or logged.

Create a **new SocialPublicator workspace** and configure its Dubai Shop connection using the new API token and the new webhook key ID/secret. Verify that the authenticated identity endpoint returns the new UUID and that the new workspace is paired to it. Do not reuse the original workspace or old credentials.

Only after those checks succeed, enable event delivery on the clone:

```bash
php artisan social-publicator:complete-installation-pairing <NEW_INSTALLATION_UUID> --confirm
```

The command requires the exact current UUID and explicit confirmation. Until then, the dispatcher leaves events unclaimed and queued delivery jobs do not send or consume retry attempts. New business events can accumulate in the outbox while pairing is pending.

The clone command is intentionally not automatic and cannot be run a second time while pairing is already pending. It must not be run on the original installation.

### B. Disaster-recovery restore

A disaster-recovery restore that is replacing the original installation must preserve the original UUID and integration credentials. Before bringing the restored system online, ensure the original installation is offline and cannot send events. Do not run the clone-rotation command. This preserves the existing SocialPublicator workspace relationship and avoids creating a second installation identity.

Never run the original and restored database copies concurrently with the same identity and credentials.

### C. Normal boot and deployment

Normal boot, cache clearing, migrations, worker restarts, and deployments never rotate the installation UUID or integration credentials. There is no automatic clone detection; the operator must explicitly choose between a new installation and a disaster-recovery replacement.

The pairing status is stored in the installation identity table. Existing installations migrate as `paired` to preserve their current integration behavior; a clone becomes `pending` only after the explicit rotation command. The webhook secret generated during rotation is encrypted using the Laravel application key, so protect and back up that key securely.

