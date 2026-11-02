# Dubai Shop — Estado actual del proyecto

**Fecha de corte:** 2 de noviembre de 2026  
**Rama de referencia:** `main`  
**Última integración verificada:** Fase 9 — zonas y tarifas de envío para Cuba  
**Commit de integración:** `af15e68977b7b6099db1988e03d8ea3b86ff4b6e`  
**Pull request:** [#13 — Fase 9: zonas y tarifas de envío para Cuba](https://github.com/renedroyd/dubai-shop/pull/13)

Este documento describe el estado comprobado del código y de la integración continua. Distingue las funciones implementadas, las decisiones comerciales ya fijadas y los trabajos que siguen pendientes. No implica que el sistema esté listo para producción.

## 1. Resumen ejecutivo

Dubai Shop ya dispone de una base funcional de tienda online: catálogo público, navegación de productos, carrito de invitado, checkout, creación y consulta pública protegida de pedidos, inventario con reservas, métodos de pago manuales y administración mediante Filament. La Fase 9 incorporó tarifas de entrega administrables para Cuba y su cálculo seguro durante el checkout.

La integración de la Fase 9 está completada en `main`. La ejecución de CI asociada al commit de la rama de la PR terminó correctamente en los tres trabajos: pruebas Laravel sobre PHP 8.4, pruebas Laravel sobre PHP 8.5 y compilación de frontend.

**Evaluación general:** el núcleo de compra y operación básica está implementado; el siguiente esfuerzo debe centrarse en completar las capacidades comerciales y de administración, cerrar brechas de producción y probar los flujos de extremo a extremo con configuración real.

## 2. Plataforma y arquitectura

| Área | Estado comprobado |
| --- | --- |
| Backend | Laravel 13 |
| PHP | Requisito del proyecto: PHP 8.4.1 o superior |
| Administración | Filament 5 |
| Frontend | Blade, Tailwind CSS 4 y Vite 8 |
| JavaScript / assets | Compilación con Vite |
| Persistencia | Eloquent y migraciones Laravel |
| Calidad | GitHub Actions: pruebas PHP en 8.4/8.5 y build frontend |

La arquitectura documentada propone un monolito modular. Las reglas de negocio deben residir en servicios/casos de uso y no en componentes de presentación. Los importes del catálogo y pedidos se manejan principalmente en unidades menores enteras.

**Observación de arquitectura:** aunque Livewire forma parte de la experiencia de Filament, `composer.json` no declara Livewire como dependencia directa y las rutas públicas inspeccionadas utilizan controladores que devuelven vistas Blade. Por tanto, la interfaz pública no debe describirse todavía como una aplicación Livewire implementada. Si se mantiene Livewire como requisito del producto, debe planificarse su incorporación explícita y la migración de los componentes públicos que realmente necesiten reactividad.

## 3. Funcionalidades implementadas

### 3.1 Catálogo y tienda pública

- Página principal pública.
- Listado de productos publicados.
- Búsqueda por texto y filtros por categoría y marca.
- Ordenamiento por fecha, precio y nombre.
- Paginación del catálogo.
- Detalle de producto, imágenes, variantes activas y productos relacionados.
- Visibilidad y estado de publicación del producto.
- Precios y moneda asociados al producto.

### 3.2 Carrito

- Carrito asociado a una clave de sesión para visitantes.
- Agregar productos y variantes.
- Actualizar cantidades y eliminar artículos.
- Validación de cantidades y pertenencia del artículo al carrito de sesión.

### 3.3 Checkout y pedidos

- Checkout de invitado con datos de contacto y dirección.
- Validación del carrito y disponibilidad de productos/variantes en servidor.
- Revisión de precios desde la base de datos, sin confiar en importes enviados por el navegador.
- Creación transaccional del pedido y de sus líneas históricas.
- Número público de pedido y token para consultar la confirmación sin crear una cuenta.
- Reserva de inventario al crear el pedido.
- Liberación de reserva al cancelar y procesamiento de inventario al completar la entrega, según el flujo documentado.
- Estados operativos de pedido: pendiente, confirmado, en proceso, enviado, entregado y cancelado.

### 3.4 Pagos

- Zelle con comprobante y validación manual por administración.
- Efectivo contra entrega con confirmación manual del cobro.
- Registro dinámico de drivers de pago mediante contrato/administrador de drivers.
- Eventos de auditoría del ciclo de vida del pago y transiciones centralizadas.
- Almacenamiento privado de comprobantes; descarga desde el panel autenticado.
- No se almacenan datos de tarjetas.

**Pendiente:** no hay una pasarela externa conectada ni procesamiento de webhooks de proveedores. La extensibilidad por drivers no equivale a tener una integración bancaria o de tarjeta lista.

### 3.5 Envíos — Fase 9 integrada

- Destino comercial limitado a Cuba (ISO `CU`).
- Selector de 16 provincias, incluida Isla de la Juventud.
- Municipio y localidad/reparto como criterios opcionales de precisión.
- Recurso Filament para administrar zonas y tarifas por provincia, municipio y localidad.
- Prioridad de coincidencia: localidad > municipio > provincia.
- Cotización previa del envío desde el checkout.
- Revalidación de la tarifa en servidor al crear el pedido.
- Bloqueo del pedido si no hay tarifa activa o la moneda no coincide con la del carrito.
- Instantánea del nombre de la zona y del importe aplicado dentro del pedido.
- Impuestos en cero; total calculado como subtotal + envío.

No se introdujeron tarifas ficticias. La operación comercial requiere que el administrador cree las tarifas reales en Filament antes de aceptar pedidos para una dirección.

## 4. Decisiones comerciales confirmadas

| Decisión | Regla vigente |
| --- | --- |
| País de entrega | Cuba únicamente |
| Impuestos | No se aplican; importe cero |
| Envío | Configurable por dirección mediante zonas y tarifas |
| Tarifas | Deben ser introducidas por administración; no hay importes predeterminados inventados |
| Monedas disponibles para tarifas | USD, CUP y EUR, sujetas a coincidencia con la moneda del carrito |
| Pago | Zelle manual y efectivo contra entrega disponibles como drivers |
| Comprobantes | Almacenamiento privado y revisión administrativa |

Las tarifas deben cargarse y verificarse antes de abrir el checkout al público. También deben probarse direcciones límite: coincidencia por provincia, por municipio, por localidad, zona inactiva y moneda incompatible.

## 5. CI y estado de integración

La PR #13 fue integrada mediante squash en `main`.

- PR: [#13](https://github.com/renedroyd/dubai-shop/pull/13)
- Commit de la rama validada: `1c2c1dc9d7dfdd38d0be44a37321571081703e76`
- Commit de integración: `af15e68977b7b6099db1988e03d8ea3b86ff4b6e`
- Workflow **Quality**: ejecución `41374566977`, resultado `success`.
- [Ver ejecución de CI](https://github.com/renedroyd/dubai-shop/actions/runs/41374566977)

Trabajos completados:
1. **PHP 8.4 · Laravel tests** — correcto.
2. **PHP 8.5 · Laravel tests** — correcto.
3. **Frontend assets** — correcto.

La suite ejecutó satisfactoriamente en ambas versiones de PHP y Vite produjo los assets de frontend. Esta validación confirma el estado del commit de la PR; no sustituye pruebas de aceptación en un entorno desplegado con tarifas, destinatarios de pago y credenciales reales.

## 6. Brechas y trabajo pendiente

Las siguientes áreas se consideran pendientes o requieren una revisión específica antes de dar el producto por completo:

### Prioridad alta — preparación operativa y producción

1. **Configuración real de la tienda:** completar y verificar identidad, contacto, moneda comercial, contenido de inicio, políticas y datos de Zelle.
2. **Tarifas de envío reales:** cargar zonas y precios acordados, validar todas las monedas utilizadas y probar direcciones reales de entrega en Cuba.
3. **Pruebas de aceptación integrales:** recorrer compra completa con Zelle y efectivo contra entrega; verificar aprobación/rechazo, cancelación, reservas, entrega y consistencia del total.
4. **Seguridad y despliegue:** revisar autenticación/autorización del panel, protección de comprobantes, límites de solicitudes, sesiones, backups, logs, secretos, HTTPS y configuración de producción.
5. **Precisión monetaria:** revisar de forma transversal las conversiones y cálculos de dinero. En el servicio de checkout de envío inspeccionado, el importe decimal de la tarifa se convierte a unidades menores mediante conversión a `float`; conviene sustituirla por una conversión decimal exacta o almacenar el importe directamente como entero en unidades menores.

### Prioridad media — completar capacidades comerciales

1. **Promociones y cupones:** no se verificó un flujo funcional completo de reglas, aplicación y redención de cupones.
2. **Contenido administrable:** ampliar y verificar la gestión de páginas, banners, menús y bloques de inicio; la página principal ya consume configuración de tienda, pero eso no acredita un CMS completo.
3. **Clientes y cuentas:** definir si se ofrecerán registro, inicio de sesión, historial de pedidos y libreta de direcciones. Actualmente el flujo de compra admite invitados.
4. **Notificaciones:** implementar correo transaccional para confirmación de pedido, cambios de estado y revisión del pago.
5. **Envíos operativos:** evaluar estados de despacho, transportista, seguimiento y notificaciones de entrega, más allá del cálculo de tarifas.
6. **Reseñas y valoraciones:** implementar moderación y reglas de compra verificada si forman parte del alcance comercial.

### Prioridad media — experiencia técnica y calidad

1. **Livewire en storefront:** decidir alcance e incorporar la dependencia directa si se confirma como requisito para la interfaz pública.
2. **Accesibilidad y responsive:** auditar teclado, lectores de pantalla, contraste, formularios y tamaños móviles en todas las páginas.
3. **Rendimiento y SEO:** medir Core Web Vitals, optimizar imágenes, metadatos, canonical, datos estructurados, sitemap y páginas indexables.
4. **Observabilidad:** definir seguimiento de errores, auditoría de acciones administrativas y alertas operativas.

### Integraciones futuras

- Pasarelas externas y webhooks firmados, idempotentes y auditables.
- Conciliación de pagos con proveedores.
- Integraciones de logística, si se contratan.
- Automatización de comunicaciones.

## 7. Secuencia recomendada de próximas fases

| Orden | Fase propuesta | Resultado esperado |
| --- | --- | --- |
| 1 | Configuración operativa y aceptación | Tarifas reales, datos de pago verificados y checklist de compra completo |
| 2 | Administración comercial | Completar contenido, promociones, clientes y ajustes de tienda |
| 3 | Notificaciones y seguimiento | Correos transaccionales y ciclo de entrega más completo |
| 4 | Experiencia pública | Decisión e implementación de Livewire, accesibilidad y responsive |
| 5 | Producción | Seguridad, rendimiento, observabilidad, backups y despliegue repetible |
| 6 | Integraciones externas | Pasarela y webhooks únicamente cuando se elija proveedor |

Cada fase debe desarrollarse en una rama independiente, con pruebas y CI; la integración a `main` debe realizarse después de revisión y aprobación.

## 8. Criterio para declarar la tienda lista para producción

No declarar la tienda lista hasta cumplir, como mínimo, lo siguiente:

- [ ] Tarifas reales cargadas para las zonas donde se venderá.
- [ ] Datos e instrucciones de Zelle verificados y método habilitado conscientemente.
- [ ] Pruebas de compra completas para todos los métodos de pago activos.
- [ ] Pruebas de cancelación, devolución de reservas y entrega de pedidos.
- [ ] Revisión de autorización del panel y acceso a comprobantes privados.
- [ ] HTTPS, secretos, backups y restauración comprobados.
- [ ] Pruebas de móvil, accesibilidad y navegadores objetivo.
- [ ] Políticas comerciales y de privacidad publicadas.
- [ ] Monitoreo de errores y procedimiento de soporte operativo.

## 9. Fuentes internas

- [Arquitectura técnica](architecture.md)
- [Modelo de dominio](domain-model.md)
- [Checkout, pedidos y pagos](checkout-orders-payments.md)
- [Pull request de la Fase 9](https://github.com/renedroyd/dubai-shop/pull/13)
- [Ejecución de CI de la Fase 9](https://github.com/renedroyd/dubai-shop/actions/runs/41374566977)
