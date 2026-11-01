# Sistema visual de Dubai Shop

## Dirección de arte

La tienda pública utiliza una identidad premium contemporánea: base negra, superficies oscuras, acentos dorados y tipografía editorial. La interfaz administrativa de Filament queda fuera de este sistema y no debe modificarse durante las fases de diseño público.

## Paleta

| Token | Valor | Uso |
| --- | --- | --- |
| Obsidian | `#0B0B0B` | Fondo principal |
| Charcoal | `#141414` | Secciones y superficies secundarias |
| Surface | `#1C1C1C` | Tarjetas y elementos elevados |
| Gold | `#C9A227` | Acciones principales y acentos |
| Gold light | `#E5C76B` | Detalles, enlaces y énfasis |
| Ivory | `#F5F2EA` | Texto principal |
| Muted | `#A6A6A6` | Texto secundario |

Los tokens están definidos en `resources/css/app.css` como variables CSS y como tokens de tema de Tailwind.

## Tipografía

- **Cormorant Garamond:** titulares, encabezados editoriales y nombres destacados.
- **Montserrat:** etiquetas, navegación y microtexto en mayúsculas.
- **Inter:** texto de lectura, descripciones y controles.

Las fuentes se cargan desde Google Fonts en el layout público. Si el proyecto requiere funcionamiento sin conexión externa, se podrán autoalojar en una fase de optimización.

## Estructura de interfaz

- `x-storefront.layout`: documento HTML, metadatos, anuncio superior, cabecera, navegación responsive y pie.
- `x-storefront.icon`: iconografía SVG compartida.
- `x-storefront.section-heading`: encabezados de sección con etiqueta, título y descripción.
- `x-storefront.category-card`: tarjeta editorial de categoría.
- `x-storefront.benefit-card`: bloque de beneficios.

La página de inicio vive en `resources/views/storefront/home.blade.php` y la ruta raíz se define con nombre `home`.

## Responsividad y accesibilidad

- Navegación de escritorio y menú móvil nativo basado en `details/summary`.
- Rejillas adaptables para categorías y beneficios.
- Enlace para saltar al contenido, etiquetas accesibles y foco visible.
- Soporte para `prefers-reduced-motion`.
- Estados hover y transiciones sin depender de JavaScript.

## Alcance de esta fase

La página contiene contenido editorial y categorías demostrativas para establecer el lenguaje visual. Todavía no representa un catálogo conectado a base de datos, carrito ni checkout. Los datos de catálogo se conectarán en la fase correspondiente; los textos, menús, banners, colores y demás opciones administrables se integrarán progresivamente con Filament en las fases de contenido y configuración.

Livewire 4 está presente de forma transitiva por Filament en el lockfile, pero no se añade todavía como dependencia directa ni se crean componentes interactivos de tienda en esta fase. Se declarará como dependencia directa antes de implementar las interacciones públicas que lo requieran.
