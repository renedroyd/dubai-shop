# CMS de tienda — Fase 12

## Administración en Filament

Dentro de **Tienda** se incorporan tres recursos:

- **Banners y campañas:** imagen, texto alternativo, título, descripción, enlace, ubicación, orden, activación y ventana de vigencia.
- **Páginas:** título, slug, contenido de texto plano, estado borrador/publicada, fecha de publicación y metadatos SEO.
- **Menús:** enlaces para cabecera y pie, orden, estado, apertura en pestaña nueva y relación padre/hijo.

Los recursos se descubren mediante la configuración existente de Filament. No se modifica el diseño ni la autenticación del panel.

## Comportamiento público

- La portada muestra únicamente banners activos cuya ventana de vigencia incluye el momento actual.
- Las páginas se sirven en `/paginas/{slug}`; solo se resuelven páginas publicadas cuya fecha de publicación no sea futura.
- El contenido de páginas se renderiza como texto escapado con saltos de línea; no se interpreta HTML arbitrario.
- Cuando hay enlaces activos configurados para cabecera o pie, sustituyen la navegación predeterminada correspondiente. Si no hay enlaces, se conserva la navegación existente.
- Los enlaces de menú admiten rutas internas, anclas y URLs HTTP/HTTPS. Los enlaces en pestaña nueva incluyen `noopener noreferrer`.

## Límites deliberados

- No se introducen cupones, descuentos automáticos ni cambios de precios. El catálogo ya dispone de precio comparativo y marca de producto destacado; las reglas de promociones económicas requieren definición comercial adicional.
- Los banners de esta entrega se muestran en la portada.
- Las páginas usan contenido de texto plano, no un editor HTML enriquecido.
- El sistema permite un nivel de subelemento en la estructura de datos; la presentación visual de menús desplegables avanzados queda fuera de esta entrega.

## Almacenamiento

Las imágenes se guardan en el disco público, en `storefront/banners`. El despliegue debe tener configurado el enlace de almacenamiento público de Laravel.
