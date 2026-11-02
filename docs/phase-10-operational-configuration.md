# Fase 10 — Operación inicial en Guantánamo

## Decisiones comerciales confirmadas

- Cobertura inicial: provincia de Guantánamo.
- Plazo de entrega comunicado al cliente: dentro de las 24 horas.
- Tarifas: estructura mixta por provincia, municipio y localidad; se conserva la precedencia de la coincidencia más específica.
- Monedas: USD, CUP y EUR. Cada producto se mantiene en una moneda y el carrito/pedido conserva una sola moneda; no se mezclan monedas ni se aplica conversión automática.
- Pagos habilitados para la operación: Zelle con comprobante y revisión manual, y efectivo contra entrega con confirmación administrativa.
- Impuestos: cero, según la decisión vigente del proyecto.

## Implementación técnica

1. Limitar el selector público, la validación del checkout y el resolvedor de tarifas a Guantánamo. La validación del servidor es obligatoria y no depende del navegador.
2. Mantener el modelo mixto de tarifas. La administración solo ofrecerá Guantánamo para crear nuevas tarifas durante esta etapa.
3. Registrar en cada pedido el compromiso de entrega (24 horas) y la fecha/hora límite calculada al crear el pedido. La instantánea no cambiará si posteriormente cambia la política.
4. Mostrar la política al cliente durante el checkout y en la confirmación, y la fecha límite al personal administrativo.
5. Convertir el importe decimal de envío a unidades menores sin operaciones de punto flotante.
6. Verificar la selección de moneda por producto, la consistencia de moneda de carrito/pedido/envío/pago y el rechazo de carritos con monedas mezcladas.
7. Mantener Zelle y efectivo contra entrega como drivers existentes, verificando su disponibilidad desde Filament y sus transiciones auditadas.

## Configuración operativa pendiente de cargar por administración

- Tarifas reales para los municipios/localidades de Guantánamo, con la moneda correspondiente.
- Datos del destinatario Zelle y las instrucciones definitivas.
- Verificación de que ambos métodos de pago estén habilitados en Filament.

No se incluyen tarifas, destinatarios ni datos bancarios inventados. La tienda no debe aceptar pedidos para direcciones sin tarifa activa coincidente.

## Criterios de aceptación

- Una dirección de Guantánamo se cotiza por localidad, municipio o provincia según la tarifa activa más específica.
- Una dirección de otra provincia se rechaza tanto al cotizar como al intentar crear el pedido.
- Un pedido nuevo conserva el plazo de 24 horas y su fecha límite.
- El coste de envío se almacena en unidades menores con conversión decimal exacta.
- Los pedidos conservan una única moneda; no hay conversión ni mezcla de importes.
- Zelle exige comprobante privado y sigue pendiente de revisión hasta aprobación/rechazo.
- Efectivo contra entrega queda pendiente de cobro y solo pasa a pagado mediante confirmación administrativa.
