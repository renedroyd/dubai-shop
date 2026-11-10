# Contrato de integración: Dubai Shop → SocialPublicator

**Estado:** contrato técnico aprobado para implementación por fases  
**Versión del contrato:** v1  
**Fuente de verdad:** Dubai Shop  
**Consumidor y motor de publicación:** SocialPublicator

## 1. Objetivo y límites

Dubai Shop (Clásico Dubai) es la autoridad exclusiva sobre productos, variantes, precios, almacenes y existencias. SocialPublicator consulta el catálogo y recibe eventos para preparar campañas y publicaciones. No se conectarán directamente las bases de datos.

Este documento define el contrato previsto; no implica que los endpoints o las tablas de integración ya estén implementados.

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

## 5. API de lectura propuesta

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

El ejemplo es ilustrativo. Los campos opcionales, paginación y serialización final se cubrirán con pruebas de contrato en la fase de API.

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

## 11. Secuencia de implementación

1. Contrato y documentación (esta fase).
2. API de lectura, credenciales e identificadores externos en Dubai Shop.
3. Receptor de eventos, catálogo externo y reconciliación en SocialPublicator.
4. Configuración de campañas y almacenes.
5. Agrupación y selección inteligente.
6. Eventos de agotamiento, reposición y precio inmediato.
7. Entrega por los destinos y bots existentes.
8. Pruebas integrales y activación gradual.

Cada fase deberá incluir pruebas automatizadas y validación de CI antes de considerar su integración.
