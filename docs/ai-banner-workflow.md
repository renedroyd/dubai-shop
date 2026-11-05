# Asistente de IA para banners — flujo gratuito

## Objetivo

Ayudar al administrador a preparar imágenes para banners y campañas de Clásico Dubai utilizando una cuenta gratuita de ChatGPT, sin integrar una API de pago en Laravel.

## Dónde se encuentra

En Filament, abre **Tienda → Banners y campañas** y utiliza la sección **Crear imagen con IA** al crear o editar un banner.

## Flujo de trabajo

1. Seleccionar el tipo de campaña: promociones, combos, productos, remesas, temporada o marca.
2. Seleccionar estilo visual: lujo, comercial realista, minimalista o cinematográfico.
3. Seleccionar formato: panorámico 2.4:1, 16:9, cuadrado o vertical.
4. Escribir una descripción breve de la escena, producto o concepto.
5. Generar el prompt en español.
6. Copiar el prompt y abrir ChatGPT.
7. En ChatGPT, pegar el prompt y generar la imagen.
8. Descargar la imagen resultante.
9. Volver al formulario de Filament, cargar la imagen en el campo de imagen y guardar el banner.

## Reglas visuales incluidas en el prompt

- Identidad premium Clásico Dubai: negro obsidiana y dorado champagne.
- Composición pensada para un banner comercial.
- Espacio visual libre a la izquierda para el texto y botón que renderiza el sitio.
- Elemento visual principal centrado o hacia la derecha.
- No incluir palabras, letras, logotipos ni marcas de agua en la imagen.
- Formato y proporción según la selección del administrador.

El texto y botón del banner se administran en los campos normales del CMS; no deben generarse incrustados dentro de la imagen.

## Costes y privacidad

- La aplicación no realiza llamadas a OpenAI.
- No requiere API key, suscripción API ni configuración de facturación.
- El administrador utiliza su propia sesión de ChatGPT en el navegador.
- La imagen se genera fuera de Laravel y se carga manualmente.
- No se envía automáticamente información privada de pedidos, clientes o pagos al servicio de IA. La descripción escrita por el administrador sí se utiliza para componer el prompt que este decide copiar.

## Límites actuales

- El asistente genera el prompt, no la imagen desde el servidor.
- No descarga ni importa automáticamente imágenes desde ChatGPT.
- No genera automáticamente título, descripción, eyebrow o texto del botón.
- No gestiona una biblioteca de prompts ni historial de generaciones.
- No hay proveedor/API de IA configurado.

## Posibles mejoras futuras

La Fase 21 de la hoja de ruta contempla sugerencias de copy para el banner, presets de campaña, mejor vista previa/recorte y, solo con aprobación explícita, una posible integración por API. La generación manual gratuita sigue siendo el flujo vigente.
