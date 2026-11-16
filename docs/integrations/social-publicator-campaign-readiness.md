# Preparación de catálogo y campañas para SocialPublicator

## Relación de integración

La relación soportada es **uno a uno: una instalación de Dubai-Shop ↔ un workspace de SocialPublicator**.

- Una instalación de Dubai-Shop se vincula a un único workspace.
- Un workspace puede vincular como máximo una instalación de Dubai-Shop.
- Una instalación no debe distribuir catálogo, inventario ni eventos a varios workspaces.
- Varias tiendas requieren instalaciones independientes de Dubai-Shop y workspaces separados.
- La conexión es opcional: SocialPublicator puede funcionar de forma independiente si no se configura una integración.

La identidad de instalación debe ser estable y verificable en el contrato de conexión y en los eventos firmados. No debe inferirse únicamente a partir de la URL pública.

## Fuente de verdad y responsabilidades

Dubai-Shop sigue siendo la fuente de verdad del catálogo, variantes, precios de venta, publicación/visibilidad, almacenes elegibles y disponibilidad. SocialPublicator mantiene una representación externa del catálogo para seleccionar productos en campañas, pero no debe modificar el inventario de Dubai-Shop.

- Dubai-Shop controla productos publicados, variantes activas, precios de venta, almacenes habilitados para pedidos online y cantidades disponibles.
- SocialPublicator controla campañas, selección de artículos, subconjunto de almacenes, frecuencia, formato, cuentas sociales y destinos.
- WBot ejecuta las entregas asignadas a WhatsApp y Facebook Groups mediante las sesiones configuradas.
- Las publicaciones oficiales en Facebook Pages se gestionan desde SocialPublicator mediante Meta OAuth / Graph API cuando los permisos lo permiten.
- La selección de almacenes de una campaña nunca modifica el inventario de Dubai-Shop.
- Los productos locales de SocialPublicator y sus existencias permanecen independientes.

## Catálogo y almacenes

Para que un producto pueda sincronizarse hacia SocialPublicator debe estar publicado (estado activo) y visible en la tienda. Las variantes activas se exponen como artículos independientes. Los precios publicados son precios de venta; los costos de adquisición y precios mínimos internos no se exponen.

En **Inventario → Almacenes**, habilita **Habilitado para pedidos online** en cada almacén que pueda abastecer campañas. Los almacenes deben estar activos. SocialPublicator puede seleccionar uno o varios almacenes elegibles por campaña, sin alterar las prioridades de abastecimiento ni las existencias locales.

## Flujo operativo esperado

1. Configurar la conexión de Dubai-Shop en el workspace asignado.
2. Verificar credenciales y firma HMAC.
3. Sincronizar el catálogo completo y los almacenes elegibles.
4. Revisar productos, precios, disponibilidad y destinos sociales.
5. Habilitar explícitamente el despacho de campañas.
6. Supervisar sincronizaciones, eventos, ejecuciones y errores desde las herramientas operativas.

La configuración de campañas no debe activar publicaciones por sí sola. Mantén el despacho deshabilitado hasta completar la sincronización, la validación de destinos y las pruebas integrales entre Dubai-Shop, SocialPublicator y WBot.

## Requisitos antes de producción

- Identidad de instalación única vinculada a un solo workspace.
- Autenticación y HMAC vinculados a esa pareja.
- Eventos idempotentes y resistentes a duplicados/reintentos.
- Eventos de ciclo de vida de catálogo y snapshots/versiones de disponibilidad para inventario.
- Recuperación automática de eventos pendientes.
- Validación de destinos conectados, permisos y estado de revocación antes de crear ejecuciones.
- Pruebas de extremo a extremo y trazabilidad de cada publicación por destino.

