# Hoja de ruta — Dubai Shop / Clásico Dubai

**Actualizada:** 7 de noviembre de 2026  
**Base:** Fases 1–16 y POS-1 a POS-17 integrados en main. La implementación funcional del POS está completada; la aceptación de hardware y las pruebas operativas en establecimientos reales siguen pendientes.

La hoja de ruta diferencia trabajo ya integrado, aceptación operativa pendiente y nuevas capacidades que requieren decisiones comerciales. La prioridad inmediata es estabilizar y aceptar el despliegue, no añadir integraciones externas sin proveedor o reglas aprobadas.

## Estado resumido

| Área | Estado | Prioridad |
|---|---|---|
| Núcleo ecommerce, catálogo y checkout | Integrado | — |
| Clientes, direcciones y notificaciones | Integrado; aceptación SMTP pendiente | Alta |
| Envíos y tracking | Integrado; prueba operativa pendiente | Alta |
| Experiencia pública y panel premium | Integrados; QA visual final pendiente | Media |
| Importación Excel/CSV | Integrada; imágenes/variantes excluidas | Media |
| Seguridad, RBAC y preflight | Integrados en esta entrega; configuración externa pendiente | Crítica |
| POS y multi almacén | POS-1 a POS-17 integrados; aceptación operativa de hardware pendiente | Alta |
| Asistente de prompts IA para banners | Integrado, flujo manual gratuito | Media |
| Lanzamiento de producción | No aceptado aún | Crítica |

## Fases completadas

### Fases 1–10 — Base de la tienda y operación comercial
Arquitectura y CI, sistema visual, catálogo, carrito/checkout, pedidos e inventario, administración, contenido configurable, SEO inicial, seguridad/pagos y envíos para Cuba. La operación inicial se definió para Guantánamo, monedas USD/CUP/EUR sin conversión automática, Zelle solo USD y compromiso de entrega de 24 horas.

### Fases 11–13 — Identidad, CMS y clientes
- Identidad Clásico Dubai y configuración operativa inicial.
- Banners, páginas y menús administrables.
- Registro/login de clientes, historial, direcciones y compra como invitado.
- Notificaciones transaccionales, plantillas y colas.

### Fases 14–16 — Logística, experiencia y seguridad
- Seguimiento, repartidores, intentos de entrega y auditoría.
- Búsqueda, filtros, accesibilidad progresiva, mejoras de tarjetas, idiomas español/inglés y navegación móvil.
- Endurecimiento de seguridad, auditorías de dependencias, preflight de producción y política de proxies.

### PR #29–38 — Mejoras adicionales
- Importación de categorías, marcas y productos desde Excel/CSV.
- Refinamiento visual del panel Filament.
- Dashboard administrativo con métricas y centro de atención.
- Banners con imagen de fondo premium.
- Asistente de prompts de imagen con ChatGPT gratuito.
- Control de acceso administrativo por roles, permisos CRUD por recurso, excepciones individuales de permitir/denegar y habilitación individual de usuarios.
- Migración de administradores existentes a Superadministrador y protección de la cuenta/rol de emergencia.

---

## Próximas entregas

### POS-10 — Pagos avanzados (completada)

<badge color="success">Integrada en main</badge>

**Decisiones comerciales aplicadas:** efectivo, Zelle manual y pagos mixtos; Zelle solo USD; una sola moneda por venta y sin conversión automática. Una venta con Zelle pendiente se registra como pendiente, reserva existencias y no autoriza la entrega hasta que un usuario con permiso de revisión apruebe el pago.

**Capacidades entregadas**
1. Reutilización del dominio existente `Payment`, `PaymentEvent`, métodos de pago, sesiones de caja y reservas de inventario.
2. Efectivo recibido, cálculo del cambio en centavos y movimientos de caja por el importe neto de la venta.
3. Zelle manual POS con remitente, referencia, fecha y comprobante privado.
4. Revisión de pagos desde Filament con aprobación/rechazo y registro del usuario validador.
5. Reserva de inventario durante la revisión; consumo al aprobar y liberación al rechazar.
6. Pagos mixtos USD con componentes positivos que deben sumar exactamente el total.
7. En pago mixto rechazado, reversión auditada del componente en efectivo y movimiento de devolución en caja.
8. Protección contra aprobación repetida y validación de que los importes pagados sumen el total de la orden.
9. Pruebas automatizadas para efectivo/cambio, Zelle, reservas, aprobación/rechazo, pagos mixtos, conciliación e idempotencia.

**Integración y CI**
- PR #51: efectivo recibido y cambio.
- PR #52: Zelle manual y reserva de inventario.
- PR #53: pagos mixtos y devolución de efectivo al rechazar Zelle.
- PR #54: auditoría de revisión, conciliación e idempotencia.
- En los PR #52, #53 y #54, los trabajos PHP 8.4, PHP 8.5 y Frontend assets finalizaron correctamente.

**Fuera del alcance POS-10:** pasarelas automáticas de tarjeta, modo offline, escáner físico/cámara y diseño de impresión térmica; se mantienen para fases posteriores según la hoja de ruta.

---

### Fase 17 — Aceptación operativa y estabilización de producción
<badge color="warning">Pausada</badge> <badge color="info">Validación externa pendiente</badge>

La ejecución de esta fase queda pausada temporalmente por decisión del usuario para priorizar la experiencia de venta POS. No se han modificado servicios ni configuración de producción.

Objetivo: convertir la configuración desplegada en una operación verificable y segura.

**Trabajo**
1. Confirmar servidor de aplicación de producción y proceso PHP supervisado; no usar `php artisan serve` para tráfico real.
2. Verificar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS y `APP_KEY` persistente.
3. Configurar proxy/Cloudflare Tunnel con IP/CIDR confiables exactas; validar esquema, host y URLs generadas por Storage.
4. Ejecutar `php artisan app:production-readiness` en el servidor.
5. Configurar SMTP y worker de colas supervisado; probar entrega y reintentos.
6. Configurar backups de base de datos y archivos; ejecutar restauración real.
7. Activar monitoreo, alertas, rotación de logs y procedimiento de rollback.
8. Ejecutar pruebas de compra, pago, cancelación, stock, entrega y notificaciones.
9. Verificar tarifas, destinatario Zelle, métodos, contactos y políticas.
10. Realizar QA responsive, accesibilidad y validación de Filament.

**Criterios de salida**
- Checklist de producción completado con evidencias.
- Prueba de restauración exitosa.
- Compra de extremo a extremo aceptada.
- No quedan errores críticos de seguridad, pagos, inventario o privacidad.

### Fase 18 — Promociones y reglas comerciales
<badge color="info">Pendiente de definición</badge>

Objetivo: implementar promociones reales sin introducir ambigüedades contables.

**Alcance propuesto**
- Cupones porcentuales y de importe fijo.
- Fechas de vigencia, límites globales y por cliente.
- Mínimo de compra y máximo de descuento.
- Restricciones por producto, categoría o marca, si se aprueban.
- Reglas de acumulación y prioridad entre promociones.
- Registro de redenciones asociado a pedidos.
- Presentación del descuento en carrito, checkout, pedido y panel.

**Bloqueo:** antes de desarrollar, aprobar reglas de acumulación, moneda, redondeo, devoluciones/cancelaciones y quién puede crear promociones.

### Fase 19 — Integraciones de pago y conciliación
<badge color="info">Condicionada a proveedor</badge>

- Elegir proveedor y países/monedas admitidos.
- Definir contratos/adaptadores sin acoplar el dominio a un proveedor.
- Webhooks firmados, idempotencia, reintentos y auditoría.
- Conciliación, reembolsos y manejo de pagos pendientes/fallidos.
- Mantener Zelle manual y efectivo mientras no se autorice una sustitución.

**Bloqueo:** no iniciar implementación hasta seleccionar proveedor, confirmar disponibilidad en los países objetivo y aprobar costes/comisiones.

### Fase 20 — SEO, accesibilidad y rendimiento avanzado
<badge color="info">Pendiente</badge>

- Auditoría de metadatos, canonical, sitemap, robots y datos estructurados.
- Revisión de indexabilidad y redirecciones de slugs.
- Optimización de imágenes y formatos modernos.
- Medición de Core Web Vitals en páginas representativas.
- Pruebas de accesibilidad con teclado, lector de pantalla, contraste y foco.
- Revisión de rendimiento de consultas, cachés y paginación en catálogo.
- Pruebas de carga proporcionales al volumen esperado.

### Fase 21 — Evolución de contenido y herramientas de marketing
<badge color="info">Opcional</badge>

- Ampliar el asistente de banners para sugerir eyebrow, título, descripción y CTA.
- Biblioteca de prompts por campaña y presets de marca.
- Mejorar selección, recorte, vista previa y validación de imágenes.
- Evaluar integración por API solo si se aprueba presupuesto, proveedor y gestión de claves.
- No hacer llamadas de API ni activar costes de IA sin autorización explícita.

### Fase 22 — Expansión comercial y logística
<badge color="info">Requiere decisión</badge>

- Ampliar cobertura geográfica y tarifas solo con zonas, costes y tiempos confirmados.
- Definir calendario real de entrega, horarios de corte y días no laborables.
- Evaluar transportistas, mensajería y notificaciones adicionales.
- Definir devoluciones, reembolsos y cambios antes de automatizar esos flujos.

---

## Registro de decisiones y restricciones vigentes

- Marca: Clásico Dubai, negro obsidiana y dorado champagne.
- Idiomas iniciales: español e inglés; arquitectura extensible.
- Storefront: Blade y controladores; Livewire se utiliza dentro de Filament, no asumir migración pública a Livewire.
- Monedas: USD, CUP y EUR separadas, sin conversión automática.
- Pagos actuales: Zelle manual solo USD y efectivo contra entrega según configuración.
- Cobertura logística inicial: Guantánamo; entrega configurada en 24 horas.
- IA para banners: flujo manual con ChatGPT gratuito; sin API ni coste de API.
- Importación Excel: categorías, marcas y productos; no imágenes ni variantes.
- No añadir proveedores, reglas de descuentos, nuevas zonas o comisiones sin aprobación.

## POS y multi almacén — entregas independientes

<badge color="success">POS-1 a POS-17 integrados</badge>

El POS funcionará siempre conectado al servidor. No se desarrollará modo offline. Decisiones aprobadas: ubicaciones internas flexibles (área/pasillo/estantería/posición), existencias independientes por almacén y aprobación configurable de transferencias.

1. **POS-1 — Núcleo multi almacén:** almacenes, jerarquía de ubicaciones, asignación de inventario existente al almacén principal y compatibilidad del checkout. Integrado en PR #39.
2. **POS-2 — Administración de almacenes:** recursos Filament para gestionar almacenes y ubicaciones, tipos, jerarquía y estados. Integrado en PR #40.
3. **POS-3 — Existencias por ubicación:** saldos por posición, ajustes, conciliación y auditoría. Integrado en PR #41.
4. **POS-4 — Transferencias:** reglas por almacén, tipo, cantidad y roles; aprobaciones múltiples, despacho, tránsito, recepción parcial y diferencias. Integrado en PR #42.
5. **POS-5 — Abastecimiento web:** almacenes permitidos, prioridad, reservas, preferencia por un único almacén y distribución de respaldo. Integrado en PR #43.
6. **POS-6 — Terminales:** establecimientos, terminales y asignación de almacén. Integrado en PR #44.
7. **POS-7 — Operación POS:** ventas integradas con Orders/Payments, sesiones de caja, arqueos, recibos, devoluciones parciales/totales y reposición auditada de inventario. Integrado en PR #45.
8. **POS-8 — Seguridad y aceptación:** asignación de permisos por usuario/almacén, restricciones en servicios y recursos, informes por almacén y pruebas de contención de inventario entre web y POS. Integrado en PR #47.
9. **POS-9 — Interfaz profesional de ventas:** pantalla táctil independiente, catálogo por almacén/moneda, variantes, carrito, cobro y operaciones de caja reutilizando los servicios POS existentes. Integrado en PR #49; corrección de selección de terminal en PR #50.
10. **POS-10 — Pagos avanzados:** efectivo con cambio, Zelle manual USD, pagos mixtos, comprobantes privados, revisión, reservas, devoluciones de efectivo y conciliación. Integrado en PR #51–55.
11. **POS-11 — Clientes y fidelización:** búsqueda por nombre/teléfono/correo, alta rápida, asociación de ventas, historial, clientes frecuentes y preparación para puntos futuros. Integrado en PR #56.
12. **POS-12 — Promociones, descuentos y precios:** reglas porcentuales/fijas por producto, categoría, marca o venta; precios especiales por cliente; ámbito por almacén/moneda; autorización, auditoría y protección de costo/precio mínimo. Integrado en PR #58–59.
13. **POS-13 — Devoluciones y cambios:** integrado en PR #61–63. Incluye autorización por dos usuarios, reembolsos distribuidos entre los medios originales, respeto de descuentos, cuarentena/descarte de artículos y registro vinculado de cambios con diferencia neta.
14. **POS-14 — Impresión y periféricos:** integrado en PR #65–66. Recibos térmicos 58/80 mm, 1–3 copias, códigos de barras, lector tipo teclado e impresión USB/red y gaveta ESC/POS mediante QZ Tray.
15. **POS-15 — Dashboard e informes avanzados:** integrado en PR #67. Filtros por fecha/almacén/terminal/cajero/moneda; ventas por dimensión; pagos netos por método; margen con costo histórico; diferencias de caja; reembolsos; comparativas y exportación CSV.
16. **POS-16 — Operación comercial avanzada:** POS-16A/B integrados en PR #68–69; POS-16C integrado en PR #71: seguimiento de transferencias e incidencias. POS-16D integrado mediante PR #72: indicadores operativos por establecimiento.
17. **POS-17 — Fidelización:** POS-17A integrado en PR #75 (ledger y acumulación); POS-17B en PR #76 (canje, mínimo de 100 puntos y límite del 20 %); POS-17C en PR #77 (reversión proporcional por devoluciones); POS-17D en PR #78 (configuración Filament, ajustes auditados, saldos e historial del cliente y avisos de vencimiento).

El POS permanece exclusivamente online, sin modo offline ni sincronización diferida. Las ventas usan el almacén asignado al terminal. El flujo de pagos integrado admite efectivo, Zelle manual en USD y pagos mixtos; cada entrega mantiene la autorización por almacén, integridad transaccional, auditoría y pruebas automatizadas. Cada bloque se desarrolla en una rama propia.

## Cómo se acepta una fase

1. Inspeccionar estado de `main`, documentación, CI y código existente.
2. Proponer alcance y riesgos si hay decisiones comerciales abiertas.
3. Crear una rama específica; nunca desarrollar directamente sobre `main`.
4. Implementar y añadir pruebas automatizadas.
5. Ejecutar pruebas, auditorías y build; corregir fallos.
6. Abrir PR con resumen, pruebas y pendientes manuales.
7. Integrar únicamente después de aprobación explícita del responsable.


### POS-10 — Pagos avanzados (completada)

Integrada en PR #51–55. Admite efectivo con cálculo de cambio, Zelle manual en USD, pagos mixtos, comprobantes privados, aprobación/rechazo, reserva/liberación de inventario y conciliación de caja. La caja registra únicamente el efectivo real; Zelle nunca se contabiliza como efectivo.

### POS-11 — Clientes y fidelización (completada)

- Buscar clientes registrados por nombre, teléfono guardado o correo.
- Registrar rápidamente una cuenta de cliente desde la pantalla POS, sin eliminar el flujo de consumidor ocasional.
- Asociar ventas a la cuenta existente y conservar snapshots históricos de nombre, teléfono y correo.
- Consultar las últimas compras que el cajero está autorizado a ver.
- Identificar clientes frecuentes de los últimos 90 días en el almacén de la caja.
- Preparar la evolución de fidelización/puntos; no acumular ni canjear puntos hasta definir reglas de conversión, vigencia y reversión.

### POS-12 — Promociones, descuentos y precios

- Descuentos fijos o porcentuales por artículo y por venta.
- Precios especiales por cliente y reglas por categoría/marca.
- Permisos de aplicación y autorización, con registro de quién aplicó y quién aprobó.
- Precio mínimo y controles de margen para evitar descuentos no rentables.
- Reflejar los descuentos en la orden, recibo, caja e informes sin alterar la trazabilidad del precio original.

### POS-13 — Devoluciones y cambios

- Búsqueda de ventas por número, cliente, fecha y terminal.
- Devoluciones parciales/totales y cambios vinculados a la venta original.
- Reposición condicionada al estado y destino del artículo devuelto.
- Reembolsos mediante los métodos permitidos y controles de caja correspondientes.
- Autorización especial cuando corresponda, prevención de cantidades/importes superiores a lo vendido y auditoría completa.

### POS-14 — Impresión y periféricos

- Recibos térmicos configurables de 58 mm y 80 mm, con opción de copias/duplicados.
- Lectores de códigos de barras físicos tipo teclado y captura mediante cámara.
- Apertura de gaveta de efectivo solo desde operaciones autorizadas.
- Impresoras USB o de red mediante un puente/servicio local compatible con navegador.
- El POS seguirá conectado al servidor; los periféricos no introducen modo offline ni sincronización local de ventas.

### POS-15 — Dashboard e informes avanzados

- Ventas por establecimiento, terminal, cajero, producto, categoría, marca, moneda y método de pago.
- Margen bruto/rentabilidad, diferencias de caja, devoluciones y descuentos.
- Comparativas por períodos y exportación de resultados.
- Indicadores y alertas operativas filtrados por los permisos globales y de almacén del usuario.

### POS-16 — Operación comercial avanzada

- Gestión de turnos y cajeros; asignación de terminales y almacenes.
- Arqueos y cierres supervisados, con límites configurables de efectivo por caja.
- Alertas de existencias bajas y solicitudes de reposición al almacén.
- Seguimiento del ciclo de transferencias y sus incidencias.
- Indicadores operativos por establecimiento.
- Mantener aprobaciones configurables de transferencias según origen/destino, usuario/rol, cantidad, valor y tipo; conservar recepción parcial, diferencias, daños, rechazo y cancelación.

**Requisitos transversales de POS-11 a POS-16:** permisos globales y por almacén, protección ante ventas simultáneas, integridad de pagos/caja, auditoría, pruebas automatizadas, CI y documentación. No se introducirán objetivos numéricos, reglas comerciales ni proveedores externos sin aprobación.


### POS-14A — Recibos térmicos y códigos de barras (integrado)

PR #65 integrado en `main`. Los terminales configuran ancho 58/80 mm y 1–3 copias; productos y variantes admiten códigos de barras; el POS captura lectores físicos tipo teclado. CI validado en PHP 8.4, PHP 8.5 y Frontend assets.

### POS-14B — Impresión local y gaveta (integrado en PR #66)

Rama `feature/pos-14b-qz-tray-cash-drawer`. Se adopta QZ Tray, aplicación local gratuita y de código abierto. Se integra selección de impresora por terminal, impresión ESC/POS directa del recibo, prueba de impresora y apertura de gaveta condicionada a una venta con efectivo. La conexión no firmada puede mostrar diálogos de confianza; no se incluye ni se expone ninguna clave privada. La configuración y prueba física de impresoras/gavetas queda como aceptación operativa por computadora.


### POS-15 — Dashboard e informes avanzados (integrado en PR #67)

Rama `feature/pos-15-advanced-reports-dashboard`. Se amplía el informe existente con filtros autorizados por almacén, terminal, cajero, moneda y rango de fechas; ventas por almacén, terminal, cajero, producto/variante, categoría y marca; importes cobrados por método con reembolsos del período descontados; devoluciones, descuentos, arqueos/diferencias de caja y comparación con el período inmediatamente anterior. El margen bruto se calcula con el costo histórico de cada línea y el descuento global del pedido; si faltan costos históricos, el total de margen se marca como no disponible. Se incorpora exportación CSV con alcance limitado a los almacenes autorizados. Los importes se mantienen separados por moneda y no se convierten.


### POS-16A — Turnos y control de caja (integrado en PR #68)

Rama `feature/pos-16a-shifts-and-cash-control`. Asignación opcional de cajeros por terminal; etiqueta de turno en cada sesión; límite de efectivo físico por terminal aplicado al fondo inicial, entradas manuales y ventas en efectivo (sin bloquear Zelle puro); cierre supervisado con solicitud del cajero, bloqueo inmediato de movimientos/ventas y aprobación obligatoria de un supervisor distinto con permiso específico. Se conserva el cajero solicitante y el supervisor aprobador en auditoría.

### POS-16B — Alertas y reposición (integrado en PR #69)

Se implementaron umbrales mínimos configurables por producto/variante y almacén, alertas de existencias bajas y solicitudes de reposición entre almacenes integradas con el flujo de transferencias existente.


### POS-16B — Alertas y reposición (detalle de la entrega integrada en PR #69)

Rama `feature/pos-16b-stock-alerts-replenishment`. Cada existencia por almacén puede guardar un umbral mínimo propio; si no se configura, se mantiene el valor predeterminado de 5 unidades. Las alertas consideran existencia disponible después de reservas y bloqueos. Desde una existencia con stock bajo se puede solicitar reposición desde otro almacén, creando una transferencia de tipo `replenishment` que utiliza las reglas y aprobaciones existentes; solicitar no mueve ni reserva stock, que solo se mueve durante el despacho aprobado.


### Estado final POS-1 a POS-17 — integrado

Todos los bloques funcionales POS-1 a POS-17 están integrados en main mediante ramas y PR independientes. Los detalles de POS-17A–D y los resultados de CI se registran al final de este documento. La aceptación física pendiente incluye instalación y prueba de QZ Tray, impresoras térmicas y gavetas en cada puesto, así como pruebas operativas de cajeros, arqueos, límites y reposición en los almacenes reales. Esto no equivale a aceptación de producción de la tienda completa; la Fase 17 de estabilización general continúa separada y pendiente.


### POS-16C — Seguimiento de transferencias e incidencias (integrado en PR #71)

PR #71 / rama `feature/pos-16c-transfer-monitoring`. Dashboard Filament con conteos por estado, transferencias activas/en tránsito, últimas 30 transferencias, actor/fecha del último evento, incidencias de rechazos/daños y conteos entrantes/salientes por almacén. El acceso exige `pos_reports.view` y limita las consultas a transferencias donde el usuario tiene `reports.view` en ambos almacenes. Reutiliza estados/eventos existentes sin cambiar reglas de aprobación ni movimientos de inventario.


### POS-16D — Indicadores operativos por establecimiento (integrado en PR #72)

Rama `feature/pos-16d-location-metrics`. Resumen por establecimiento de ventas/pedidos y reembolsos separados por moneda, terminales activos, cajas abiertas, cierres pendientes de aprobación y sesiones del período. Solo incluye establecimientos con terminales asociados a almacenes para los que el usuario posee `reports.view`; requiere `pos_reports.view`. No realiza conversiones monetarias ni altera ventas o caja.


### Corrección POS — límites de efectivo por moneda (integrada en PR #73)

Rama `fix/pos-cash-limits-by-currency`. Cada terminal mantiene límites independientes en CUP, USD y EUR; la apertura de caja, entradas manuales y ventas en efectivo solo comparan el límite de la moneda de la sesión. La migración conserva los límites históricos bajo CUP, moneda inicial predeterminada del POS, para evitar aplicar importes nominales a monedas distintas.

### POS-17 — Fidelización y puntos (integrado en PR #75–78)

- **17A:** ledger por cliente y moneda; acumula solo ventas POS pagadas y clientes con cuenta; Zelle genera puntos tras aprobación; idempotencia y vencimiento a 12 meses.
- **17B:** canje mínimo de 100 puntos, máximo del 20 % del subtotal elegible y valor por moneda: 1 punto = 1 CUP, 0.01 USD o 0.01 EUR. Las unidades menores se manejan sin conversión entre monedas.
- **17C:** reversión proporcional de puntos ganados y restitución proporcional de puntos canjeados en devoluciones parciales/totales; los reembolsos monetarios mantienen los medios de pago originales.
- **17D:** configuración Filament, permisos, ajustes manuales con motivo/actor, historial y saldos en la cuenta del cliente y avisos visibles de vencimiento en 30 días.
- **CI de PR #75, #76, #77 y #78:** PHP 8.4, PHP 8.5 y Frontend assets aprobados en cada integración.
