# Dubai Shop — Estado del proyecto y hoja de ruta

**Corte:** 2 de noviembre de 2026  
**Rama de referencia:** `main`  
**Última integración:** Fase 13 — cuentas de clientes y comunicación transaccional (PR #20); Fase 14 en desarrollo en `feature/fase-14-operacion-logistica`  
**Commit de integración:** `1d730d0df874aacfa00087d2fc9171c8fa494e41`  
**PR integrada:** [#19 — Fase 12](https://github.com/renedroyd/dubai-shop/pull/19)

Este documento separa lo que está implementado y probado en el repositorio de la configuración comercial que aún debe cargarse, y de las capacidades que siguen en la hoja de ruta. La integración de una fase no significa que la tienda esté lista para producción.

## 1. Resumen ejecutivo

Dubai Shop tiene implementado el núcleo de una tienda online: catálogo público, búsqueda y filtros, carrito de invitado, checkout, pedidos, reservas de inventario, pagos manuales auditados, administración con Filament y tarifas de envío configurables. La Fase 10 restringió la operación inicial a Guantánamo, estableció el compromiso de entrega dentro de 24 horas, reforzó el tratamiento de importes de envío y formalizó el uso de varias monedas sin mezclarlas.

**Estado actual:** fases 1–13 integradas; Fase 14 en desarrollo. La Fase 13 incluye cuentas de clientes y comunicación transaccional. La validación operativa de SMTP y worker de colas sigue pendiente. Falta verificar la configuración real del negocio, ejecutar pruebas de aceptación y cerrar requisitos de producción.

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

- [PR #17 — Fase 11](https://github.com/renedroyd/dubai-shop/pull/17): integrada.
- [PR #18 — Normalización de municipios](https://github.com/renedroyd/dubai-shop/pull/18): integrada.
- [PR #19 — Fase 12, CMS](https://github.com/renedroyd/dubai-shop/pull/19): integrada mediante squash.
- Commit base de Fase 13: `7bb812d685848234f60d808af7ced9e16ea089ca`.
- [PR #20 — Fase 13](https://github.com/renedroyd/dubai-shop/pull/20): abierta como borrador, sin merge.
- CI de la revisión `cc71e487c25feec902014a3ba00d942a44353382`, ejecución [41449500599](https://github.com/renedroyd/dubai-shop/actions/runs/41449500599): **success**.
- Validaciones: 62 pruebas y 271 assertions correctas; PHP 8.4, PHP 8.5, compilación frontend y cachés de producción.

La CI valida pruebas automatizadas y compilación, pero no sustituye las pruebas de aceptación con SMTP real ni la verificación de la configuración comercial en el entorno de despliegue.

## 6. Trabajo pendiente organizado por fases

### Fase 11 — Identidad de marca y configuración operativa
**Estado: integrada en PR #17 · validación operativa pendiente**

**Implementado e integrado:**
- Seeder idempotente para configurar el nombre público Clásico Dubai.
- Efectivo contra entrega habilitado.
- Tarifas temporales activas de 500 CUP para los diez municipios de Guantánamo.
- Zelle habilitado por el seeder únicamente cuando el nombre y correo del destinatario se configuran mediante variables privadas válidas.
- Logotipo vectorial horizontal y favicon SVG integrados en el storefront y en Filament.
- Pruebas automatizadas de la configuración inicial y las diez tarifas.

**Pendiente de validación operativa:** comprobar el logo en Filament (incluida la pantalla de login), verificar datos Zelle en el entorno desplegado y probar checkout real por moneda/método. El SVG es una reconstrucción vectorial, no una optimización píxel a píxel del archivo original.

- Revisar el logotipo y sustituirlo por la versión maestra optimizada si se facilita el archivo fuente.
- Verificar las diez tarifas, los destinos reales y el compromiso de entrega de 24 horas.
- Revisar teléfono, correo, moneda, textos legales y políticas.
- Ejecutar compras de prueba con Zelle USD y efectivo contra entrega CUP/EUR.

**Criterio de salida operativa:** identidad visible, tarifas confirmadas, pagos verificados y compra de extremo a extremo aceptada.

### Fase 12 — Administración comercial y CMS
**Estado: integrada en PR #19**

- Banners administrables con imagen, texto, enlace, orden y vigencia.
- Páginas administrables con borrador/publicación y metadatos SEO.
- Menús configurables para cabecera y pie de página.
- El contenido se escapa y los enlaces se validan; no se admite HTML arbitrario.
- Cupones y descuentos automáticos quedan pendientes hasta definir reglas comerciales.

### Fase 13 — Clientes y comunicación
**Estado: implementada en la rama de trabajo · PR #20 abierta como borrador**

- Registro, inicio y cierre de sesión de clientes; recuperación y restablecimiento de contraseña mediante el broker de Laravel.
- Las cuentas de clientes no acceden al panel Filament y las cuentas administrativas no reciben enlaces de recuperación desde el storefront.
- Historial de pedidos y gestión de direcciones guardadas, incluida edición y selección predeterminada.
- El checkout mantiene la compra como invitado y permite a clientes autenticados precargar/guardar direcciones.
- Notificaciones de pedido recibido, pago aprobado/rechazado y cambios de estado hasta la entrega/cancelación.
- Plantillas de correo editables y activables desde Filament.
- Envíos mediante colas Laravel con tres intentos y backoff.
- Registro de destinatarios, eventos, estados, intentos y errores; reintento manual desde Filament.
- CI verde: 62 pruebas y 271 assertions, PHP 8.4/8.5, frontend y cachés de producción.
- Pendiente operativo: configurar SMTP y worker activo en el entorno de despliegue, y ejecutar una prueba de aceptación de correo real.

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
