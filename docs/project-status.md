# Dubai Shop — Estado del proyecto

**Corte:** 7 de noviembre de 2026  
**Rama de referencia:** `main`  
**Última entrega integrada:** POS-16B — alertas de inventario y reposición (PR #69, integrado). POS-16C — seguimiento de transferencias e incidencias, en revisión en PR #71.

> Este documento distingue implementación integrada, validación automatizada y aceptación operativa. Que una funcionalidad esté integrada o que CI pase no significa que esté configurada ni aceptada en el entorno real.

## 1. Resumen ejecutivo

Dubai Shop cuenta con el núcleo funcional de una tienda online y su administración: catálogo, importación, carrito, checkout, pedidos, inventario, pagos manuales, tarifas de envío, cuentas de clientes, comunicaciones transaccionales, operación logística, CMS, internacionalización y una interfaz premium negro/dorado.

Las Fases 1–16 y las mejoras PR #29–37 están integradas. La entrega PR #38 añade control de acceso por roles y permisos en Filament. El generador de prompts permite utilizar ChatGPT gratuito para crear imágenes sin integrar la API.

**Situación general:** desarrollo funcional avanzado; falta completar aceptación operativa y cerrar la preparación real para producción. No declarar el sistema listo para producción hasta ejecutar las verificaciones externas de dominio/HTTPS, proxy, correo, colas, backups/restauración, monitoreo y compra de extremo a extremo.

## 2. Plataforma y arquitectura

| Área | Estado |
|---|---|
| Backend | Laravel 13 |
| Runtime CI | PHP 8.4 y 8.5 |
| Administración | Filament 5 |
| Tienda pública | Blade + controladores Laravel |
| Reactividad | Livewire en Filament; no es el framework de vistas del storefront |
| Frontend | Tailwind CSS 4, Vite 8 |
| Persistencia | Eloquent y migraciones |
| CI | GitHub Actions: pruebas PHP, auditorías y build frontend |

## 3. Funcionalidades integradas

### Catálogo y contenido comercial
- Categorías, marcas, productos, variantes e imágenes.
- Listado, detalle, búsqueda, filtros, ordenamiento y paginación.
- Disponibilidad de inventario en tarjetas; distinción entre agotado y producto sin control de stock.
- Compra rápida para productos sin variantes y selección de opciones para productos con variantes.
- Importación de categorías, marcas y productos desde Excel/XLSX y CSV; no incluye importación de imágenes ni variantes.
- CMS de banners, páginas y menús configurables.
- Banners con imagen de fondo, degradado de legibilidad, composición responsive y animación reducida cuando el usuario lo solicita.

### Carrito, checkout, pedidos e inventario
- Carrito para visitantes y clientes autenticados.
- Checkout como invitado; no obliga a crear cuenta.
- Validación de precios y disponibilidad en servidor.
- Pedidos con líneas históricas y snapshots.
- Reservas, liberación y cumplimiento de inventario con operaciones transaccionales.
- Consulta privada de pedido mediante token.

### Pagos y envíos
- Driver de pago manual Zelle con comprobante y validación administrativa.
- Efectivo contra entrega.
- Auditoría de eventos y transiciones de pago.
- Comprobantes en almacenamiento privado, con acceso protegido.
- Tarifas de envío configurables por provincia, municipio y localidad.
- Monedas del modelo actual: USD, CUP y EUR; no hay conversión automática ni mezcla de monedas dentro de un pedido.
- Zelle restringido a USD.
- Cobertura logística inicial limitada a Guantánamo y compromiso de entrega de 24 horas.
- La habilitación real de métodos, destinatario, tarifas y zonas debe comprobarse en el entorno desplegado.

### Cuentas de clientes y comunicación
- Registro, inicio/cierre de sesión y recuperación de contraseña.
- Separación de permisos entre clientes y administradores.
- Historial de pedidos y direcciones guardadas.
- Compra como invitado preservada.
- Notificaciones de pedido recibido, pagos y cambios de estado.
- Plantillas editables/activables en Filament, registro de envíos y reintentos.
- La entrega real de correo requiere SMTP válido y un worker de cola activo.

### Operación logística
- Envío asociado al pedido y código de seguimiento.
- Asignación de repartidor y teléfono desde Filament.
- Despacho controlado.
- Registro auditable de intentos, incidencias y recepción.
- Reintento de despacho.
- Seguimiento privado para el cliente.
- La confirmación de entrega exige pago aprobado y procesa inventario de forma idempotente.
- Se mantiene el alcance comercial de Guantánamo y el compromiso configurado de 24 horas.

### Diseño e internacionalización
- Identidad de Clásico Dubai: negro/obsidiana y dorado champagne.
- Rediseño premium del escaparate público; el tema de Filament también fue refinado sin alterar sus flujos de negocio.
- Idiomas iniciales español e inglés; registro preparado para incorporar más idiomas.
- Preferencias de idioma de tienda y administración independientes.
- El contenido de productos y otros textos comerciales no se traduce automáticamente.

### Administración
- Recursos Filament para catálogo, inventario, pedidos, pagos, envíos, clientes, contenido y configuración.
- Dashboard con métricas, gráficos y centro de atención.
- Importación de catálogo desde Excel/CSV.
- Tema administrativo premium negro/dorado.

### Control de acceso administrativo — PR #38
- Habilitación/deshabilitación individual del acceso al panel.
- Roles múltiples por usuario con permisos combinados y excepciones individuales de permitir/denegar.
- Permisos por recurso y acción, aplicados mediante políticas Laravel y comprobaciones en acciones personalizadas.
- Gestión de usuarios y roles reservada al Superadministrador; protección de la cuenta y rol de emergencia.
- Los administradores existentes se migran a Superadministrador; el resto de usuarios permanece sin acceso al panel por defecto.

### Asistencia de IA para banners — PR #35
- Asistente en Filament para tipo de campaña, estilo, formato y descripción.
- Genera un prompt en español alineado con la marca, con espacio para texto superpuesto y sin texto/logos/watermarks dentro de la imagen.
- Permite copiar el prompt y abrir ChatGPT.
- El usuario genera y descarga la imagen desde su cuenta gratuita y luego la carga en el formulario del banner.
- **No existe llamada desde Laravel a OpenAI API, no se guardan claves y no hay coste de API.** La generación de imagen y la carga final son pasos manuales.

## Estado de POS al 7 de noviembre de 2026

- POS-1 a POS-10 integrados en main.
- POS-11 integrado mediante PR #56: búsqueda por nombre/correo/teléfono guardado, alta rápida, asociación de ventas, historial limitado por almacén y clientes frecuentes.
- POS-1 a POS-16 integrados en main. POS-16C integrado mediante PR #71. POS-16D integrado mediante PR #72. Corrección de límites de efectivo por moneda en implementación.
- POS-14A y POS-14B (PR #65–66), POS-15 (PR #67) y POS-16A/16B (PR #68–69) están integrados en main.
- POS-13 (PR #61–63): autorización por separado para procesar/aprobar devoluciones; reembolsos por los medios originales con distribución proporcional efectivo/Zelle; solo efectivo afecta caja; descuentos respetados; stock devuelto vendible, en cuarentena o desechado; cambios vinculados y diferencia neta; búsqueda de ventas por número, cliente, fecha, terminal y almacén.
- Los PR #61, #62 y #63 superaron PHP 8.4, PHP 8.5 y Frontend assets antes de integrarse.
- El POS es exclusivamente online. POS-10 admite efectivo, Zelle manual USD y pagos mixtos; los pagos Zelle requieren revisión.
- CI del PR #56 en el último commit validado: PHP 8.4, PHP 8.5 y Frontend assets — exitosos.

## 4. Historial de fases e integraciones

| Entrega | Resultado | Estado |
|---|---|---|
| Fases 1–7 | Base, identidad, catálogo, carrito/checkout, administración, contenido y SEO inicial | Integradas |
| Fases 8, 8A y 8B | Seguridad inicial, drivers de pago extensibles y auditoría de pagos | Integradas |
| Fases 9–10 | Envíos para Cuba, Guantánamo, multimoneda y plazo de 24 horas | Integradas |
| Fase 11 | Identidad y configuración operativa de Clásico Dubai | Integrada; verificación de entorno pendiente |
| Fase 12 | CMS de banners, páginas y menús | Integrada |
| Fase 13 | Cuentas de clientes y comunicación transaccional | Integrada; SMTP/worker por aceptar |
| Fase 14 | Operación logística y seguimiento | Integrada; aceptación manual de punta a punta pendiente |
| Fase 15 | Experiencia del escaparate | Integrada; auditoría visual y accesibilidad completa pendiente |
| Fase 16 | Seguridad y preparación de producción | Integrada; operación externa pendiente |
| PR #29 | Importación de categorías, marcas y productos | Integrada |
| PR #30 | Refinamiento visual del panel Filament | Integrada |
| PR #31–33 | Seguridad, preflight de producción y comodín de proxy solo local | Integradas |
| PR #34 | Banners/campañas con imagen de fondo premium | Integrada |
| PR #35 | Asistente de prompts de IA para banners | Integrada |

## 5. Seguridad y CI

La base integrada incluye:
- Acceso administrativo protegido y separación de cuentas de clientes.
- RBAC con roles, permisos por recurso/acción, habilitación individual y políticas Laravel.
- Validación de imágenes y límites de tamaño; comprobantes de pago con validación y almacenamiento privado.
- Tokens aleatorios para consulta pública de pedidos y respuestas privadas/no-store.
- Rate limits en operaciones sensibles.
- Cabeceras de seguridad y HSTS solo sobre HTTPS.
- Proxies inversos explícitos. `TRUSTED_PROXIES=*` se permite exclusivamente en `APP_ENV=local`, como único valor; no se admite en producción.
- `composer audit` y `npm audit --audit-level=high` en CI.
- Comando `php artisan app:production-readiness` para comprobar configuración, conexión a base de datos y permisos de almacenamiento.

La matriz de CI ejecuta PHP 8.4/8.5, pruebas, compilación frontend y comprobaciones de dependencias/cachés según el workflow. Para PR #35, las comprobaciones previas a la integración fueron satisfactorias. En esta revisión no se obtuvo una ejecución posterior al merge asociada al commit final; no se afirma un CI post-merge adicional.

El preflight no puede probar desde GitHub el certificado público, entrega SMTP, worker activo, backups restaurables ni monitoreo real. CSP permanece aplazada para evitar romper scripts de Filament/Livewire antes de definir una política compatible.

## 6. Aceptación operativa pendiente

Antes de recibir tráfico y pedidos reales, completar y registrar:

1. **Dominio y HTTPS:** confirmar `APP_URL` con el dominio público definitivo, redirección HTTP→HTTPS y certificado válido.
2. **Cloudflare/proxy:** configurar solo los proxies/IP/CIDR reales en `TRUSTED_PROXIES`; comprobar que las URLs de Storage se generan con HTTPS y dominio público, no con `localhost`.
3. **Servidor de aplicación:** sustituir `php artisan serve` por un servidor/proceso PHP adecuado para producción, con reinicio, supervisión y manejo de concurrencia.
4. **Base de datos y almacenamiento:** verificar persistencia, permisos, enlace público de archivos y privacidad de comprobantes.
5. **Correo y colas:** configurar SMTP real, worker supervisado, reintentos y prueba de entrega.
6. **Backups:** respaldar base de datos y archivos privados/públicos; probar restauración en entorno aislado.
7. **Monitoreo:** alertas de HTTP/5xx, disco, base de datos, cola, SMTP y jobs fallidos.
8. **Pruebas comerciales:** completar pedidos de prueba por moneda/método, validación de comprobantes, cancelación, reserva/liberación de stock, despacho e intento de entrega.
9. **Configuración del negocio:** verificar tarifas activas, destinatario Zelle, métodos habilitados, datos de contacto y políticas.
10. **QA visual:** revisar móvil, tablet y escritorio; accesibilidad de teclado/contraste; pantallas de login, formularios y tablas del panel.

Se informó que el entorno actual utiliza Cloudflare Tunnel y `php artisan serve`. Esa combinación puede servir para pruebas o acceso temporal, pero no debe considerarse una arquitectura de producción adecuada para una tienda que reciba tráfico y pedidos reales.

## 7. Funcionalidades no implementadas o condicionadas

- Cupones y descuentos automáticos: requieren definir reglas comerciales, límites, acumulación y vigencia.
- Pasarela de pago externa: requiere seleccionar proveedor y definir contrato, conciliación, webhooks y reembolsos.
- Conversión de divisas: no existe; USD, CUP y EUR se manejan por separado.
- Importación de imágenes y variantes desde Excel: fuera del alcance de la importación actual.
- Reseñas/valoraciones de clientes: no presentar las estrellas visuales vacías como reseñas reales.
- CSP: aplazada hasta una implementación compatible y probada.
- Más idiomas: la infraestructura permite agregarlos, pero cada catálogo de traducción debe prepararse.
- Automatización de generación de imágenes mediante API: no implementada ni necesaria para el flujo gratuito actual.

## 8. Criterio de finalización

El proyecto puede considerarse listo para lanzamiento solo cuando:
- [ ] `app:production-readiness` finaliza sin errores en el entorno de producción.
- [ ] HTTPS, dominio, proxy y URLs de Storage están comprobados externamente.
- [ ] No se utiliza `php artisan serve` como servidor de producción.
- [ ] SMTP y worker entregan notificaciones reales.
- [ ] Backups y restauración se han probado.
- [ ] Monitoreo y procedimiento de rollback están activos.
- [ ] Checkout, pagos, stock y logística se han aceptado de extremo a extremo.
- [ ] Se verificaron tarifas, zonas, métodos y datos comerciales.
- [ ] QA responsive, accesibilidad y panel administrativo fue completado.

## 9. Reglas de trabajo

Cada nueva fase se desarrolla en una rama independiente. Antes de implementar, revisar `main`, CI y documentación; después ejecutar pruebas, auditorías y build. Abrir PR con alcance, riesgos y resultados. **No integrar en `main` sin aprobación explícita.**


### POS-14A — recibos térmicos y códigos de barras (PR #65)
- Recibos de 58/80 mm y 1–3 copias por terminal.
- Códigos de barras únicos en productos y variantes; lector físico tipo teclado.
- CI validado: PHP 8.4, PHP 8.5 y Frontend assets.

### POS-14B — QZ Tray (en desarrollo)
- Rama: `feature/pos-14b-qz-tray-cash-drawer`.
- Configuración de impresora y gaveta por terminal; impresión térmica ESC/POS; apertura automática solo tras una venta con efectivo cobrado y prueba manual con permiso de caja.
- QZ Tray requiere instalación local por computadora. La impresión no firmada puede mostrar confirmaciones de confianza; no se expone una clave privada ni se incluye una integración de firma silenciosa de pago.


### POS-15 — dashboard e informes avanzados (en desarrollo)
- Rama: `feature/pos-15-advanced-reports-dashboard`.
- Filtros por fecha, almacén, terminal, cajero y moneda con alcance restringido a `reports.view`.
- Desgloses de ventas por almacén, terminal, cajero, producto/variante, categoría, marca y método de pago.
- Reembolsos descontados de los métodos de pago dentro del período; diferencias de caja y comparativa con el período anterior.
- Margen bruto con costo histórico por línea y descuento global; si faltan costos históricos, se informa como no disponible.
- Exportación CSV filtrada y limitada a los almacenes autorizados. No se convierten ni mezclan monedas.


### POS-16A — turnos y control de caja (en desarrollo)
- Rama: `feature/pos-16a-shifts-and-cash-control`.
- Asignación de cajeros por terminal y etiqueta de turno en cada sesión.
- Límite de efectivo por terminal aplicado al fondo inicial, entradas manuales y ventas con efectivo; Zelle puro queda excluido.
- Cierre supervisado opcional: el cajero solicita el cierre, la sesión queda bloqueada y un supervisor distinto con permiso específico aprueba.
- Registro de solicitante/aprobador y diferencias de arqueo.

### POS-16B — alertas y reposición (pendiente)
- Umbrales de inventario configurables por almacén y producto/variante.
- Alertas de stock bajo y solicitudes de reposición integradas con transferencias multi almacén.


### POS-16B — alertas y reposición (en desarrollo)
- Rama: `feature/pos-16b-stock-alerts-replenishment`.
- Umbral mínimo configurable por existencia/almacén, con valor predeterminado de 5 unidades.
- Alertas calculadas sobre disponible real (existencia menos reservado y bloqueado).
- Acción de solicitar reposición desde otra ubicación/almacén, usando transferencias existentes tipo `replenishment` y sus aprobaciones.
- La solicitud no altera el stock; el movimiento ocurre en el despacho aprobado.


### POS-14 — periféricos (PR #65–66)
- Recibos térmicos 58/80 mm, copias por terminal, códigos de barras de productos/variantes y lector tipo teclado.
- QZ Tray 2.3.0 para impresión directa y apertura de gaveta ESC/POS. Requiere instalación local en cada puesto y aceptación física de impresora/gaveta; la conexión sin firma puede mostrar confirmaciones.

### POS-15 — informes avanzados (PR #67)
- Filtros por fechas, almacén, terminal, cajero y moneda; desgloses por producto, categoría, marca, terminal y método de pago.
- Margen estimado con costo histórico; diferencias de caja, reembolsos, comparación de períodos y exportación CSV.

### POS-16 — operación avanzada (PR #68–69)
- Cajeros asignados a terminales y etiquetas de turno; límites de efectivo y cierres supervisados con aprobación de otra persona.
- Umbral mínimo configurable por inventario/almacén; alertas basadas en disponible tras reservas/bloqueos.
- Solicitudes de reposición enlazadas a transferencias y aprobaciones existentes; crear la solicitud no mueve stock.

### Aceptación operativa POS pendiente
- Instalar y probar QZ Tray, impresoras térmicas y gavetas en cada computadora de caja.
- Verificar asignaciones reales de cajeros, límites de efectivo, cierres supervisados y permisos de aprobación.
- Probar umbrales, ubicaciones de recepción, solicitudes de reposición, aprobaciones, despacho y recepción en los almacenes reales.
- La integración y el CI no sustituyen la aceptación física ni la Fase 17 de preparación de producción.


### POS-16C — seguimiento de transferencias e incidencias (en revisión)
- Dashboard de estados, transferencias activas/en tránsito, actividad reciente y últimas incidencias.
- Conteos de transferencias entrantes/salientes por almacén.
- Alcance limitado a transferencias donde el usuario tiene `reports.view` en ambos almacenes, además del permiso `pos_reports.view`.
- Reutiliza eventos existentes de rechazo y daños; no altera stock, aprobación, despacho ni recepción.
- PR #71: `feature/pos-16c-transfer-monitoring`; CI de PHP 8.4, PHP 8.5 y Frontend assets validado en el head inicial. Requiere sincronizar con `main` y repetir CI antes de integrar.


## POS-16C — seguimiento de transferencias e incidencias (integrado)

PR #71 integrado en `main`. Dashboard de estados, actividad, incidencias y conteos por almacén, con autorización por `pos_reports.view` y `reports.view` en ambos almacenes.

## POS-16D — indicadores por establecimiento (integrado mediante PR #72)

- Resumen de ventas y pedidos por moneda, sin mezclar ni convertir CUP/USD/EUR.
- Reembolsos netos por moneda, terminales activos, cajas abiertas y cierres pendientes.
- Sesiones de caja dentro del período seleccionado.
- Visibilidad limitada a establecimientos que tengan terminales asociados a almacenes autorizados para `reports.view`.


## Corrección POS — límites de efectivo por moneda (integrada mediante PR #73)

- Límites independientes por terminal para CUP, USD y EUR.
- Apertura, entradas manuales y ventas en efectivo aplican exclusivamente el límite de la moneda de la sesión.
- Zelle puro no altera ni consume el límite de efectivo.
- La migración conserva el límite histórico en CUP, moneda predeterminada inicial del POS; debe revisarse la configuración de terminales si un límite anterior se había usado para otra moneda.

## POS-17A — libro de fidelización y acumulación inicial (integrado mediante PR #75)

- Configuración por moneda para CUP, USD y EUR; acumulación inicial: 1 punto por cada 100 CUP, 1 punto por cada 1 USD y 1 punto por cada 1 EUR. Los importes se almacenan en unidades menores (CUP: 10 000; USD/EUR: 100). Los puntos vencen a los 12 meses.
- Carteras separadas por cliente y moneda, con libro de movimientos e identificador único de idempotencia.
- Acumulación calculada sobre el total POS neto de descuentos y únicamente para clientes con cuenta.
- Ventas en efectivo acumulan al cobrarse; Zelle acumula después de la aprobación manual. Los pagos pendientes y ventas de invitado no generan puntos.
- Pruebas automatizadas para venta pagada, exclusión de invitado, idempotencia y aprobación Zelle.
- El canje, reversión por devoluciones, ajustes autorizados, avisos de vencimiento e interfaz administrativa/cliente quedan para los siguientes subbloques POS-17.

## POS-17B — canje de puntos en el terminal POS (integrado mediante PR #76)

- Canje vinculado a una cuenta de cliente y al saldo de la moneda de la sesión; nunca convierte ni mezcla CUP, USD y EUR.
- Valor inicial de canje: 1 punto = 1 CUP, 0.01 USD o 0.01 EUR.
- Canje mínimo de 100 puntos y descuento máximo del 20 % del subtotal elegible después de promociones.
- El descuento por puntos se incorpora al total y al descuento de la orden; los importes de pago, efectivo recibido y cambio se calculan sobre el total neto.
- Los puntos se consumen por lotes conservando su fecha de vencimiento. Si se rechaza el pago Zelle, se restituyen los puntos reservados con su vencimiento original.
- El siguiente subbloque POS-17C concilia acumulaciones y canjes cuando se procesan devoluciones.

## POS-17C — conciliación de puntos en devoluciones (integrado mediante PR #77)

- Las devoluciones parciales revierten proporcionalmente los puntos ganados y restituyen proporcionalmente los puntos canjeados.
- La proporción se calcula sobre el importe neto efectivamente pagado por la orden; al completar la devolución se revierte/restaura el 100 %.
- Cada movimiento de devolución conserva la referencia a su movimiento original, el aprobador, la devolución y el vencimiento de los puntos.
- Si los puntos ganados ya fueron consumidos antes de la devolución, el ledger registra el saldo negativo correspondiente; las futuras acumulaciones compensan ese saldo y el terminal no permite canjear un saldo negativo.
- Los reembolsos monetarios siguen distribuyéndose entre los medios de pago originales y no se modifican por la conciliación de puntos.

## POS-17D — administración y consulta del programa de fidelización (integrado mediante PR #78)

- Configuración Filament por moneda: acumulación, valor de canje, mínimo, porcentaje máximo y vencimiento.
- Permisos separados para consultar/editar las reglas y para realizar ajustes manuales.
- Ajustes positivos y negativos con motivo obligatorio, actor identificado y movimientos inmutables; los ajustes negativos se distribuyen por lotes sin perder fechas de vencimiento.
- El panel de cliente muestra saldos independientes, movimientos recientes, deuda por compensar y puntos que vencen en los próximos 30 días.
- El terminal POS también advierte al cajero cuando el cliente seleccionado tiene puntos próximos a vencer.
- Los ajustes negativos manuales no pueden reducir un saldo disponible por debajo de cero.

## Estado final de POS-17

POS-17A, POS-17B, POS-17C y POS-17D están integrados en main mediante PR #75–78. La implementación funcional de fidelización está completada: acumulación, canje, devoluciones, configuración, ajustes auditados, consulta de cliente y avisos visibles. Cada PR pasó CI en PHP 8.4, PHP 8.5 y Frontend assets antes de integrarse.
