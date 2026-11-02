# Dubai Shop — Estado del proyecto y hoja de ruta

**Corte:** 2 de noviembre de 2026  
**Rama de referencia:** `main`  
**Última integración:** Documentación Fase 10 (PR #16); Fase 11 en desarrollo en rama independiente  
**Commit de integración:** `e802dc67239eb0f0cc77a59a6ca50ca2a560084b`  
**PR integrada:** [#15 — Fase 10](https://github.com/renedroyd/dubai-shop/pull/15)

Este documento separa lo que está implementado y probado en el repositorio de la configuración comercial que aún debe cargarse, y de las capacidades que siguen en la hoja de ruta. La integración de una fase no significa que la tienda esté lista para producción.

## 1. Resumen ejecutivo

Dubai Shop tiene implementado el núcleo de una tienda online: catálogo público, búsqueda y filtros, carrito de invitado, checkout, pedidos, reservas de inventario, pagos manuales auditados, administración con Filament y tarifas de envío configurables. La Fase 10 restringió la operación inicial a Guantánamo, estableció el compromiso de entrega dentro de 24 horas, reforzó el tratamiento de importes de envío y formalizó el uso de varias monedas sin mezclarlas.

**Estado actual:** base funcional integrada; falta completar la configuración real del negocio, probar la operación completa con datos reales, terminar la identidad visual de Clásico Dubai y cerrar requisitos de producción.

La tienda no debe considerarse lista para producción solo porque CI esté verde. Aún es necesario cargar tarifas, configurar el destinatario Zelle en la administración, verificar los métodos activos y realizar pruebas de aceptación en un entorno real.

## 2. Plataforma y arquitectura

| Área | Estado |
|---|---|
| Backend | Laravel 13 |
| PHP | 8.4.1 o superior |
| Administración | Filament 5 |
| Vistas públicas | Blade |
| Estilos | Tailwind CSS 4 |
| Assets | Vite 8 |
| Persistencia | Eloquent y migraciones Laravel |
| Calidad continua | GitHub Actions: pruebas PHP 8.4/8.5 y build frontend |

Las rutas públicas inspeccionadas utilizan controladores y vistas Blade. Livewire forma parte de Filament, pero no está declarado como dependencia directa del proyecto ni se ha verificado una integración directa en el storefront. No describir el frontend público como una aplicación Livewire hasta que esa fase se implemente y valide.

## 3. Funcionalidades implementadas

### 3.1 Catálogo y tienda pública
- Página de inicio.
- Listado, detalle y búsqueda de productos.
- Filtros por categoría y marca, ordenamiento y paginación.
- Imágenes, variantes y productos relacionados.
- Estado y visibilidad de productos.
- Precios y moneda asociados al producto.

### 3.2 Carrito y checkout
- Carrito para visitantes asociado a sesión.
- Gestión de artículos, variantes y cantidades.
- Checkout de invitado.
- Validación de precios, disponibilidad y cantidades en servidor.
- Creación transaccional de pedido y líneas históricas.
- Consulta de pedido mediante identificador/token público.
- Reserva de inventario al comprar, liberación al cancelar y actualización al completar la operación, conforme a los servicios existentes.

### 3.3 Pedidos e inventario
- Panel Filament para pedidos, inventario y movimientos.
- Estados operativos del pedido.
- Registro de datos históricos del producto y del envío en el pedido.
- Fases de reserva, cancelación y cumplimiento de inventario.

### 3.4 Pagos
- Driver Zelle manual con comprobante y revisión administrativa.
- Efectivo contra entrega con confirmación administrativa.
- Registro dinámico de drivers.
- Auditoría de eventos y transiciones de estado del pago.
- Comprobantes almacenados en área privada.
- No hay pasarela bancaria externa ni webhooks de proveedores.

Regla comercial de Fase 10: Zelle solo debe utilizarse para pedidos en USD. CUP y EUR no deben enviarse a Zelle; se utiliza efectivo contra entrega mientras no se integre otro método compatible.

### 3.5 Envíos — Fases 9 y 10
- Tarifas configurables por provincia, municipio y localidad.
- Precedencia de coincidencia: localidad > municipio > provincia.
- Cotización de envío y revalidación en servidor al confirmar pedido.
- El pedido conserva una instantánea del importe y zona aplicada.
- Cobertura inicial restringida a la provincia de Guantánamo.
- Compromiso de entrega de 24 horas guardado en el pedido y mostrado al cliente y al administrador.
- No se aceptan pedidos si no existe una tarifa activa coincidente.
- Impuestos en cero.

### 3.6 Monedas
- Monedas admitidas por el modelo actual: USD, CUP y EUR.
- Un carrito y un pedido conservan una sola moneda.
- No se mezclan monedas ni se hace conversión automática.
- El coste de envío debe coincidir con la moneda del carrito.
- La disponibilidad de Zelle se restringe a USD.

## 4. Decisiones comerciales vigentes

| Decisión | Definición |
|---|---|
| Nombre comercial solicitado | Clásico Dubai |
| Cobertura inicial | Provincia de Guantánamo |
| Plazo de entrega anunciado | Dentro de 24 horas |
| Tarifas | Estructura mixta: provincia, municipio y localidad |
| Tarifa temporal solicitada | 500 CUP para todos los destinos cubiertos; pendiente de carga y verificación administrativa |
| Monedas | USD, CUP y EUR, sin mezcla ni conversión automática |
| Pagos | Zelle manual (solo USD) y efectivo contra entrega |
| Datos de destinatario Zelle | El responsable ya proporcionó los datos; falta aplicarlos y verificarlos en la configuración real |
| Impuestos | No se aplican; total = subtotal + envío |

Los datos del destinatario Zelle y la imagen de marca fueron facilitados por el responsable, pero no se consideran configurados en el sistema hasta que se verifiquen en el entorno de la tienda. Por privacidad, no se copian datos personales de pago en esta documentación pública.

## 5. Estado de CI e integración

- [PR #15 — Fase 10](https://github.com/renedroyd/dubai-shop/pull/15): integrada mediante squash.
- Commit de integración: `e802dc67239eb0f0cc77a59a6ca50ca2a560084b`.
- [CI de la PR #15](https://github.com/renedroyd/dubai-shop/actions/runs/41423708944): **success**.
- Trabajos completados: PHP 8.4 Laravel tests, PHP 8.5 Laravel tests y frontend assets.
- Tras la integración se inició una ejecución de CI sobre `main`; debe comprobarse su resultado antes de publicar/desplegar.

La CI prueba la suite automatizada y la compilación. No reemplaza pruebas de aceptación con tarifas, credenciales, destinatario de pago y configuración reales.

## 6. Trabajo pendiente organizado por fases

### Fase 11 — Identidad de marca y configuración operativa
**Prioridad: crítica · en desarrollo**

**Implementado en la rama de trabajo (pendiente de CI y revisión):**
- Seeder idempotente para fijar el nombre público Clásico Dubai.
- Habilitación de efectivo contra entrega.
- Tarifas temporales activas de 500 CUP para los diez municipios de Guantánamo.
- Zelle solo se habilita desde el seeder cuando `ZELLE_RECIPIENT_NAME` y `ZELLE_RECIPIENT_EMAIL` están definidos y el correo es válido; los valores reales deben configurarse como variables privadas del entorno o desde Filament.
- Logotipo vectorial horizontal y favicon SVG; integración en el storefront y en el encabezado de Filament.
- Prueba automatizada de la configuración inicial y las diez tarifas.

**Pendiente de confirmar antes de cerrar la fase:** ejecutar CI, comprobar render del logo en Filament (incluida pantalla de login), probar checkout real por moneda/método y verificar los datos Zelle en el entorno desplegado. El logo SVG actual es una reconstrucción vectorial a partir de la descripción visual disponible; no es una optimización píxel a píxel del archivo original.

- Configurar nombre comercial Clásico Dubai en los ajustes reales de tienda.
- Revisar el logotipo vectorial incorporado y sustituirlo por la versión maestra original optimizada si se vuelve a facilitar el archivo fuente.
- En la barra de navegación de Filament: isotipo a la izquierda y nombre a la derecha, respetando el ancho disponible.
- En la pantalla de login de Filament: composición completa del logotipo como en el original.
- Completar y verificar los datos de destinatario Zelle en la configuración de pago, sin exponerlos en el repositorio.
- Habilitar Zelle solo después de validar esos datos y confirmar que aplica exclusivamente a USD.
- Verificar que el seeder haya creado las diez tarifas temporales de 500 CUP y que los destinos/localidades concretos coincidan con la cobertura real.
- Confirmar que efectivo contra entrega está habilitado.
- Revisar teléfono, correo de contacto, moneda, textos legales, políticas y contenido comercial.
- Ejecutar compra de prueba con Zelle USD y efectivo contra entrega CUP/EUR.
- Confirmar que el plazo de 24 horas se comunica correctamente y que el equipo puede cumplirlo.

**Criterio de salida:** identidad visible en tienda y panel, configuración real guardada, tarifas activas, pagos verificados y compra de extremo a extremo aceptada.

### Fase 12 — Administración comercial y CMS
**Prioridad: alta**
- Completar administración de banners, promociones visuales, menús y bloques de portada.
- Definir páginas administrables: acerca de, contacto, preguntas frecuentes, políticas de compra, privacidad y devoluciones.
- Mejorar la gestión de ofertas y productos destacados.
- Implementar cupones y reglas promocionales si se confirman como requisito.
- Validar permisos y auditoría de cambios administrativos.

### Fase 13 — Clientes y comunicación
**Prioridad: alta**
- Decidir si se habilitarán cuentas de clientes o se mantendrá el modelo de invitado como opción.
- Si se aprueban cuentas: registro, autenticación, historial de pedidos y direcciones guardadas.
- Correos transaccionales: pedido recibido, pago aprobado/rechazado, cambios de estado y entrega.
- Plantillas de notificación configurables y registro de envíos fallidos.

### Fase 14 — Operación logística
**Prioridad: alta**
- Gestión operativa de preparación, despacho, entrega e incidencias.
- Asignación de responsable/repartidor si el negocio lo necesita.
- Registro de intentos de entrega y confirmación de recepción.
- Seguimiento visible al cliente y notificaciones.
- Ampliar cobertura fuera de Guantánamo solo cuando se autorice y existan tarifas reales.

### Fase 15 — Experiencia de compra y frontend
**Prioridad: media**
- Decidir el alcance de Livewire en la tienda pública e incorporarlo explícitamente si sigue siendo requisito.
- Mejorar interacciones del catálogo, filtros, carrito y checkout.
- Auditoría responsive en móviles/tablets/escritorio.
- Accesibilidad: teclado, etiquetas, contraste y lectores de pantalla.
- SEO técnico: metadatos, canonical, datos estructurados, sitemap y páginas indexables.
- Optimización de imágenes y Core Web Vitals.

### Fase 16 — Seguridad y preparación de producción
**Prioridad: crítica antes del lanzamiento**
- Auditoría de autenticación, autorización y roles de Filament.
- Verificar acceso privado a comprobantes y documentos.
- Revisar validación de archivos, límites de solicitudes, sesiones, CSRF y cabeceras.
- Configurar HTTPS, secretos, correo, colas y cachés de producción.
- Backups automáticos y prueba documentada de restauración.
- Logs, monitoreo, alertas y procedimiento de respuesta a incidentes.
- Pruebas de carga razonables y revisión de dependencias/vulnerabilidades.
- Guía reproducible de despliegue y rollback.

### Fase 17 — Integraciones externas
**Prioridad: condicionada a decisiones comerciales**
- Seleccionar proveedor de pagos antes de implementar una pasarela.
- Webhooks firmados, idempotencia, reintentos y auditoría.
- Conciliación de pagos.
- Integraciones logísticas o de mensajería si se contratan.
- No implementar integraciones basadas en proveedores no elegidos.

## 7. Riesgos y decisiones que requieren confirmación

1. **Monedas:** confirmar si USD, CUP y EUR deben estar disponibles simultáneamente en catálogo o si se seleccionará una moneda de operación por tienda. La implementación actual no convierte monedas.
2. **Tarifa temporal:** 500 CUP es un valor provisional solicitado; debe aplicarse a cada zona real de Guantánamo y revisarse antes de aceptar pedidos.
3. **Entrega 24 horas:** definir horario de corte, días laborables, fines de semana y tratamiento de pedidos recibidos fuera de horario. El plazo almacenado actualmente es un compromiso de 24 horas desde la creación del pedido, no un calendario comercial inteligente.
4. **Zelle:** los datos ya fueron entregados, pero falta verificar que estén guardados correctamente y que el driver esté configurado.
5. **Marca:** el logotipo adjunto no figura todavía como asset versionado en el repositorio inspeccionado; su optimización e integración son trabajo pendiente de la Fase 11.

## 8. Criterio de preparación para producción

No declarar la tienda lista hasta cumplir:
- [ ] Configuración de marca y contacto verificada.
- [ ] Tarifas reales o temporales autorizadas cargadas para cada zona activa.
- [ ] Datos Zelle verificados y método habilitado conscientemente.
- [ ] Compra completa probada para cada método/moneda activa.
- [ ] Cancelación, reservas, liberación y entrega verificadas.
- [ ] Autorización del panel y privacidad de comprobantes revisadas.
- [ ] HTTPS, secretos, backups y restauración probados.
- [ ] Responsive, accesibilidad y navegadores objetivo revisados.
- [ ] Políticas publicadas y canal de soporte operativo definido.
- [ ] Monitoreo y procedimiento de recuperación disponibles.

## 9. Regla de trabajo del repositorio

Cada fase se trabaja en una rama independiente. Antes de implementar se revisa el estado del repositorio y CI; después se ejecutan pruebas y se abre PR. No se integra a `main` sin aprobación explícita del responsable.
