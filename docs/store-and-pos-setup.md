# Guía de configuración inicial de la tienda y del POS — Clásico Dubai

**Propósito:** servir como procedimiento de puesta en marcha para una instalación de Dubai Shop. Esta guía describe el orden recomendado, las decisiones operativas conocidas y las pruebas de aceptación. No implica que los valores estén ya cargados en todos los entornos.

> **Importante:** la configuración de código, la configuración guardada en la base de datos y la aceptación en el establecimiento son tres cosas distintas. Después de desplegar una versión, verifica los valores desde Filament y realiza las pruebas de este documento. No copies secretos ni datos reales de clientes a archivos versionados.

## 1. Antes de comenzar

1. Desplegar la versión aprobada de `main` y confirmar que las migraciones terminaron correctamente.
2. Realizar un backup de la base de datos antes de una instalación o actualización que afecte datos operativos.
3. Configurar las variables de entorno y ejecutar:
   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   php artisan app:production-readiness
   ```
4. Crear/confirmar el usuario Superadministrador y comprobar que puede entrar en `/admin`.
5. Revisar [Control de acceso administrativo](admin-access-control.md) y crear usuarios/roles con privilegios mínimos.
6. Confirmar que fecha, hora, zona horaria, moneda y datos de contacto corresponden al establecimiento.

No ejecutes `db:seed` rutinariamente en producción: los seeders son para instalación/desarrollo y pueden introducir datos que no correspondan a la operación real.

## 2. Configuración general de la tienda

Realiza estos pasos desde el área de configuración de Filament. Los nombres exactos de los campos pueden variar según la versión del panel; usa el grupo de configuración de tienda y confirma que cada cambio queda guardado.

### 2.1 Identidad y presentación

- Nombre comercial: **Clásico Dubai**.
- Aplicar el logotipo aprobado para el escaparate y el panel.
- Identidad visual: negro obsidiana `#0B0B0B`, dorado `#C9A227` y dorado claro `#E5C76B`, con tonos marfil.
- Revisar datos de contacto, enlaces sociales, textos legales, políticas de compra, devolución y privacidad antes de publicar.
- Comprobar banners, páginas y menús: imágenes optimizadas, enlaces válidos, fechas de vigencia y visualización en móvil/escritorio.

### 2.2 Idiomas y monedas

- Idiomas iniciales: español e inglés. La estructura admite añadir locales posteriormente, pero las traducciones de contenido comercial deben prepararse.
- Monedas habilitadas en el modelo comercial actual: **CUP, USD y EUR**.
- No existe conversión automática entre monedas. Cada pedido/venta se procesa en una sola moneda; no sumar ni comparar importes de monedas distintas como si fueran equivalentes.
- Zelle está limitado a USD.

### 2.3 Impuestos, pagos y envío

Decisiones operativas iniciales registradas:

| Configuración | Valor inicial acordado | Acción de puesta en marcha |
|---|---|---|
| Cobertura | Provincia de Guantánamo y sus municipios | Revisar zonas/localidades activas |
| Plazo de entrega | Dentro de 24 horas | Confirmar texto y plazo por zona |
| Tarifa de entrega | 500 CUP plana, provisional | Verificar las tarifas guardadas y reemplazarlas cuando se definan importes por dirección |
| Impuestos | No aplicar por ahora | Confirmar que no se añadan cargos fiscales |
| Zelle | Pago manual con comprobante; solo USD | Verificar destinatario y procedimiento de revisión |
| Efectivo contra entrega | Habilitado según la operación | Probar que aparece en checkout |
| Otras pasarelas | No asumir que están configuradas | Incorporar solo tras seleccionar proveedor y aprobar reglas |

Datos de recepción Zelle acordados para la tienda:
- Titular: **René Serrano Pérez**.
- Correo: **renedroyd@gmail.com**.

Comprueba estos datos en el entorno antes de aceptar pagos reales. El comprobante debe permanecer en almacenamiento privado y solo usuarios autorizados deben poder revisarlo. No publiques comprobantes ni los guardes en una carpeta pública.

Para las tarifas, verifica cada combinación de provincia, municipio/localidad, moneda y estado activo. Que exista una tarifa en la base de datos no garantiza que coincida con la dirección elegida en checkout. La tarifa plana de 500 CUP es temporal, no una regla permanente.

### 2.4 Clientes, pedidos y comunicación

- Mantener habilitada la compra como invitado.
- Si se usan cuentas, comprobar registro, inicio de sesión, recuperación, historial de pedidos y direcciones guardadas.
- Revisar los estados de pedido y los métodos de pago disponibles.
- Configurar SMTP y un worker de colas supervisado para que las notificaciones salgan realmente; guardar una plantilla no prueba que el correo se entregue.
- Hacer un pedido de prueba y confirmar recepción, notificación, comprobante, estado de pago y consulta privada del pedido.

## 3. Preparación de inventario y almacenes para el POS

Configura la estructura física antes de crear terminales o registrar existencias.

1. Crear cada almacén real con nombre, código, dirección y estado correcto.
2. Designar el almacén principal cuando corresponda. El canal web utiliza el almacén principal en el alcance actual.
3. Crear ubicaciones internas según la organización física: áreas, pasillos, estanterías y posiciones. La estructura es flexible.
4. Crear/validar productos y variantes, SKU y códigos de barras.
5. Cargar o ajustar existencias por producto/variante y almacén mediante los flujos autorizados. Cada ajuste debe dejar su movimiento auditable.
6. Registrar el **costo de adquisición por producto/variante y por almacén**. No asumir un costo único global cuando el costo de compra difiere entre almacenes.
7. Configurar umbrales de stock bajo por artículo/almacén. El valor predeterminado documentado es 5 unidades; revisarlo según rotación y plazo de reposición.
8. Comprobar existencias, reservas y unidades bloqueadas. El disponible no equivale necesariamente al stock físico.

### Transferencias entre almacenes

- Configurar si las transferencias requieren aprobación, según la política definida para el negocio.
- La distribución entre ubicaciones es independiente del saldo agregado del almacén.
- Una solicitud de reposición no mueve existencias por sí sola. El movimiento se produce en el despacho aprobado y debe completarse con recepción.
- Probar transferencia normal, solicitud de reposición, aprobación/rechazo, despacho, recepción e incidencia.
- No registrar la misma entrada o salida manualmente además del movimiento generado por la transferencia.

## 4. Configuración de terminales y usuarios del POS

Repite el procedimiento para cada puesto físico de venta.

1. Crear un terminal con identificador/nombre inequívoco y asociarlo al almacén desde el que venderá.
2. Marcarlo como activo. Un terminal inactivo no debe usarse para abrir una sesión.
3. Asignar los cajeros autorizados a ese terminal. Verifica que el usuario esté habilitado y tenga los permisos necesarios.
4. Configurar el límite de efectivo del terminal, si aplica. El límite se controla en la moneda correspondiente y contempla fondo inicial, entradas manuales y cobros en efectivo; un cobro exclusivamente Zelle no incrementa el efectivo de caja.
5. Definir si el cierre de caja necesita supervisión. Cuando está activado, el cajero solicita el cierre y otro supervisor autorizado lo aprueba; no debe aprobarlo la misma persona que lo solicitó.
6. Configurar la etiqueta de turno y las reglas de arqueo/diferencias.
7. Confirmar que el usuario puede seleccionar ese terminal y que el sistema lo reconoce como autorizado y activo.

**Si aparece “Selecciona un terminal autorizado y activo”:** revisar en este orden el estado activo del terminal, la asignación del usuario al terminal, el almacén relacionado, la sesión/turno y los permisos del usuario. Estar activo no basta si el cajero no está asignado o carece de autorización.

## 5. Caja: apertura, ventas y cierre

### 5.1 Apertura de sesión

- El cajero inicia sesión con su propia cuenta.
- Selecciona un terminal activo y autorizado.
- Registra el fondo inicial por moneda y confirma el turno.
- Verifica que no exista otra sesión abierta incompatible para ese terminal/cajero.
- Comprueba que el efectivo inicial respeta el límite configurado.

No compartas cuentas entre cajeros: la trazabilidad de ventas, movimientos y cierres depende del usuario autenticado.

### 5.2 Registrar una venta

1. Buscar productos por nombre, SKU o código de barras; el lector compatible funciona como dispositivo de entrada tipo teclado.
2. Seleccionar la variante correcta, cantidad y comprobar precio/disponibilidad.
3. Asociar un cliente existente o utilizar el alta rápida cuando corresponda; la venta puede continuar sin cliente si las reglas lo permiten.
4. Confirmar almacén, terminal, cajero, moneda y total antes de cobrar.
5. Seleccionar el método de pago:
   - **Efectivo:** introducir importe recibido; comprobar cambio y movimiento neto en caja.
   - **Zelle:** registrar los datos/referencia y comprobante; queda pendiente de revisión y no debe considerarse pagado hasta la aprobación autorizada.
   - **Mixto:** los componentes deben sumar exactamente el total y respetar las restricciones de moneda. Zelle solo admite USD.
6. Confirmar la venta una sola vez y verificar número de venta, estado, existencias y recibo.

El POS es **online**; no planifiques operar sin conexión ni asumir sincronización posterior.

### 5.3 Cierre y arqueo

- Contar el efectivo físico por moneda y compararlo con el esperado.
- Registrar la diferencia y su explicación, sin alterar movimientos históricos.
- Si hay cierre supervisado, solicitarlo y esperar la aprobación de un supervisor distinto.
- Revisar el resumen de ventas, entradas, salidas, reembolsos y diferencias antes de dar el turno por cerrado.

## 6. Reembolsos, devoluciones y cambios

Política acordada: **el reembolso monetario se realiza por los mismos medios de pago originales**, con la autorización requerida.

1. Localizar la venta por número, cliente, fecha, terminal o almacén.
2. Revisar las líneas, cantidades, descuentos y medios de pago originales.
3. Solicitar la autorización independiente requerida para procesar/aprobar la devolución.
4. Registrar cantidades y condición de cada artículo: vendible, cuarentena o desechado.
5. Para pagos mixtos, distribuir el reembolso proporcionalmente entre los medios originales; solo el componente en efectivo afecta el movimiento de caja.
6. Registrar cambio de producto y cualquier diferencia neta de forma vinculada y auditable.
7. Comprobar que una misma devolución no pueda procesarse dos veces.

No reembolsar por un medio distinto por conveniencia operativa, salvo que se cambie formalmente la política y se implemente/autorice ese comportamiento.

## 7. Fidelización y puntos

La fidelización mantiene saldos separados por moneda; CUP, USD y EUR no se convierten entre sí.

- Configurar y publicar las reglas vigentes antes de anunciar el programa.
- El canje requiere un mínimo de **100 puntos**.
- El descuento por puntos no puede superar el **20 % del subtotal elegible**.
- Los puntos se mantienen por moneda; no usar puntos generados en una moneda para descontar una venta en otra.
- En devoluciones parciales se revierte/restaura la parte proporcional de puntos asociada a las líneas devueltas.
- Verificar en el historial/ledger cada acumulación, canje y reversión.

## 8. Impresión, cajón y periféricos

Para cada computadora de caja:

1. Instalar y probar QZ Tray en el equipo local.
2. Conectar la impresora térmica y seleccionar el ancho configurado: 58 u 80 mm.
3. Configurar el número de copias (1–3) por terminal.
4. Conectar la gaveta de efectivo mediante la impresora/interfaz compatible y probar la secuencia ESC/POS.
5. Autorizar la comunicación de QZ Tray según el procedimiento del equipo. La impresión no firmada puede mostrar confirmaciones de confianza; no instalar ni compartir claves privadas improvisadas.
6. Probar apertura de gaveta únicamente en las operaciones autorizadas: venta con efectivo cobrado o prueba manual con permiso de caja.
7. Escanear códigos de barras de productos y variantes y comprobar que el artículo/variante correcto se agrega.
8. Imprimir recibos de venta, devolución y reimpresión; comprobar márgenes, caracteres, totales y copias.

La presencia del código en el repositorio no sustituye la instalación local ni la prueba física de cada impresora, gaveta y lector.

## 9. Informes y control diario

Al cierre del día, revisar los informes con filtros coherentes de fecha, almacén, terminal, cajero y moneda:

- Ventas brutas/netas y desglose por método de pago.
- Reembolsos y su distribución por método original.
- Diferencias de caja y sesiones cerradas.
- Ventas por producto/variante, categoría, marca, terminal y cajero.
- Margen bruto estimado a partir del costo histórico. Si faltan costos históricos, tratar el margen como no disponible, no como cero.
- Stock bajo, bloqueos, reservas y transferencias pendientes.
- Exportaciones CSV solo para almacenes a los que el usuario tiene acceso.

Nunca mezclar monedas en un total comparativo sin una política de conversión aprobada.

## 10. Roles y permisos recomendados

Usa **Seguridad → Acceso de usuarios** y **Seguridad → Roles y permisos** (ver [control de acceso administrativo](admin-access-control.md)).

Como punto de partida:
- Superadministrador: configuración global, usuarios, roles y operaciones críticas.
- Administrador: operación general sin gestionar cuentas/roles de emergencia.
- Cajero: acceso al POS, terminales asignados, apertura/cierre y ventas según permisos.
- Supervisor de caja: autorizaciones de cierre, arqueos y operaciones de caja que se le asignen.
- Inventario/almacén: existencias, movimientos y transferencias autorizadas.
- Responsable de devoluciones: permisos específicos para procesar/aprobar devoluciones.
- Informes: acceso de solo lectura a informes permitidos.

Los roles son perfiles editables: antes de entregar credenciales, probar con una cuenta de cada rol que pueda realizar sus tareas y que no pueda ejecutar acciones no autorizadas. Ocultar una opción en el menú no reemplaza la autorización del servidor.

## 11. Pruebas de aceptación antes de abrir al público

### Tienda online

- [ ] Idiomas español e inglés visibles y seleccionables.
- [ ] CUP, USD y EUR disponibles según la política; no hay conversión automática.
- [ ] Dirección válida de Guantánamo obtiene la tarifa esperada; dirección fuera de cobertura se rechaza o informa correctamente.
- [ ] El plazo de 24 horas y la tarifa provisional de 500 CUP son correctos donde correspondan.
- [ ] Zelle solo se ofrece en USD, guarda comprobante privado y requiere aprobación.
- [ ] Efectivo contra entrega funciona.
- [ ] Compra de invitado, cuenta de cliente, notificaciones y seguimiento privado funcionan.
- [ ] El stock se reserva, consume o libera según el resultado del pedido.

### POS

- [ ] Cajero asignado puede abrir sesión en el terminal activo correcto.
- [ ] Usuario no autorizado o terminal inactivo no puede operar.
- [ ] Venta en efectivo calcula cambio y caja correctamente.
- [ ] Zelle queda pendiente hasta su aprobación.
- [ ] Pago mixto concilia exactamente el total.
- [ ] Venta descuenta existencias del almacén correcto.
- [ ] Devolución requiere autorización y devuelve por los medios originales.
- [ ] Cierre normal y supervisado registran arqueo y responsables.
- [ ] Transferencias, reposición, recepción y alertas de stock funcionan.
- [ ] Puntos y reversiones parciales respetan moneda, mínimo y límite.
- [ ] Impresora, gaveta, lector y QZ Tray funcionan físicamente en cada puesto.
- [ ] Informes y exportaciones respetan filtros, moneda y permisos.

Registrar fecha, versión desplegada, terminal, usuario de prueba, resultado y evidencia de cada aceptación. No usar pagos, datos personales ni comprobantes reales para pruebas.

## 12. Límites conocidos

- El POS requiere conexión; no dispone de modo offline.
- No hay conversión automática de monedas.
- Zelle es manual y requiere revisión del comprobante.
- La generación de imágenes de banners con ChatGPT es un flujo manual: el panel genera el prompt; la persona crea/descarga la imagen y la carga.
- QZ Tray y los periféricos requieren instalación y aceptación en cada computadora.
- La aceptación del hardware, backups/restauración, SMTP, workers, HTTPS y monitoreo es operativa; no queda demostrada por un CI verde.

## Documentación relacionada

- [Estado del proyecto](project-status.md)
- [Hoja de ruta](../ROADMAP.md)
- [Arquitectura técnica](architecture.md)
- [Modelo de dominio](domain-model.md)
- [Preparación para producción](production-readiness.md)
- [Control de acceso administrativo](admin-access-control.md)
- [Operación logística y seguimiento](logistics-and-tracking.md)
