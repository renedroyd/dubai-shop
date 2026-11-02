# Experiencia del escaparate — Fase 15

## Criterios de arquitectura

El escaparate público mantiene Blade como sistema de renderizado y los controladores/servicios Laravel como fuente de verdad. No se incorpora Livewire en esta fase inicial: el catálogo, carrito y checkout ya disponen de endpoints HTTP con validación y lógica de negocio en el servidor. Introducir un segundo flujo de estado para esas operaciones duplicaría responsabilidades sin aportar una mejora proporcional.

La interactividad ligera se implementa como mejora progresiva en `resources/js/app.js`. Las funciones esenciales siguen disponibles mediante formularios HTML y navegación del servidor cuando JavaScript no está disponible.

Filament permanece fuera del alcance visual y funcional de esta fase.

## Mejoras incluidas

- Búsqueda de catálogo con debounce de 350 ms.
- Aplicación automática de filtros de categoría, marca y ordenamiento.
- Etiquetas explícitas para controles del catálogo y región de resultados anunciada mediante `aria-live`.
- Estados de foco visibles para navegación por teclado.
- Corrección del estilo del enlace de checkout en el resumen del carrito.

## Límites y decisiones

- No se modifica la lógica de precios, inventario, pagos, tarifas de envío ni pedidos.
- No se cambia la cobertura de entrega ni el compromiso de 24 horas.
- No se modifica el panel Filament.
- Livewire podrá evaluarse en una fase posterior si aparecen interacciones que requieran estado reactivo complejo, validación en vivo o componentes con actualizaciones parciales frecuentes. Cualquier incorporación debe reutilizar los servicios existentes y evitar trasladar reglas comerciales al frontend.

## Validación manual pendiente

Antes de cerrar la fase, comprobar en móvil, tableta y escritorio:

1. Buscar por nombre, SKU y descripción; verificar que Enter también envía el formulario.
2. Combinar búsqueda, categoría, marca y ordenamiento; verificar que la paginación conserva los filtros.
3. Navegar el catálogo y los controles usando solo teclado; comprobar foco visible.
4. Usar el carrito con JavaScript activado y desactivado; cambiar cantidades, eliminar artículos y continuar al checkout.
5. Completar checkout como invitado y como cliente autenticado, con dirección nueva y guardada.
6. Revisar registro, inicio de sesión, historial, direcciones y seguimiento de pedidos.
7. Verificar que el diseño de Filament no ha cambiado.

## Pendiente de aceptación operativa

Las pruebas automatizadas de CI no sustituyen la comprobación visual en dispositivos y navegadores reales. Registrar cualquier incidencia de responsive, accesibilidad o rendimiento antes de aprobar el cierre de la fase.
