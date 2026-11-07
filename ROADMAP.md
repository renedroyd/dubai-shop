# Hoja de ruta — Dubai Shop / Clásico Dubai

**Actualizada:** 6 de noviembre de 2026  
**Base:** Fases 1–16 y PR #29–37 integradas; PR #38 incorpora RBAC administrativo.

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
| POS y multi almacén | POS-1, POS-2 y POS-3 integrados; POS-4 en desarrollo; sin ventas POS habilitadas | Alta |
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

### Fase 17 — Aceptación operativa y estabilización de producción
<badge color="warning">Prioridad crítica</badge> <badge color="info">Pendiente</badge>

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

<badge color="success">POS-1, POS-2 y POS-3 integrados</badge> <badge color="info">POS-4 en desarrollo</badge>

El POS funcionará siempre conectado al servidor. No se desarrollará modo offline. Decisiones aprobadas: ubicaciones internas flexibles (área/pasillo/estantería/posición), existencias independientes por almacén y aprobación configurable de transferencias.

1. **POS-1 — Núcleo multi almacén:** almacenes, jerarquía de ubicaciones, asignación de inventario existente al almacén principal y compatibilidad del checkout. Integrado en PR #39.
2. **POS-2 — Administración de almacenes:** recursos Filament para gestionar almacenes y ubicaciones, tipos, jerarquía y estados. Integrado en PR #40.
3. **POS-3 — Existencias por ubicación:** saldos por posición, ajustes, conciliación y auditoría. Integrado en PR #41.
4. **POS-4 — Transferencias:** reglas por almacén, tipo, cantidad y roles; aprobaciones múltiples, despacho, tránsito, recepción parcial y diferencias. En desarrollo.
5. **POS-5 — Abastecimiento web:** almacenes permitidos, prioridad, reservas y fallback.
6. **POS-6 — Terminales:** establecimientos, terminales y asignación de almacén.
7. **POS-7 — Operación POS:** ventas integradas con Orders/Payments, cajas, arqueos, recibos y devoluciones.
8. **POS-8 — Seguridad y aceptación:** permisos por almacén, informes y pruebas concurrentes web/POS.

No habilitar ventas POS ni asignación de stock web a almacenes secundarios hasta completar las reglas de asignación, reservas y seguridad. Cada entrega se desarrolla en rama propia y se integra solo con aprobación explícita.

## Cómo se acepta una fase

1. Inspeccionar estado de `main`, documentación, CI y código existente.
2. Proponer alcance y riesgos si hay decisiones comerciales abiertas.
3. Crear una rama específica; nunca desarrollar directamente sobre `main`.
4. Implementar y añadir pruebas automatizadas.
5. Ejecutar pruebas, auditorías y build; corregir fallos.
6. Abrir PR con resumen, pruebas y pendientes manuales.
7. Integrar únicamente después de aprobación explícita del responsable.
