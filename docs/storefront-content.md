# Contenido configurable de la tienda — Fase 6

## Panel
En Filament > Tienda > Contenido y textos se administran:
- Identidad de la tienda, barra de anuncio y descripción del footer.
- Títulos, textos y botones del hero de inicio.
- Introducción, colecciones, filosofía, beneficios y cierre de la página principal.
- Títulos y descripciones SEO de la página de inicio.

Cada registro contiene una clave, sección, etiqueta, valor, orden y estado público. Las claves existentes vienen precargadas y pueden editarse. También se pueden añadir nuevas claves para futuras vistas que consuman StorefrontSettings.

## Categorías
La sección de colecciones muestra automáticamente las primeras cuatro categorías activas según su orden. Se administran desde Catálogo > Categorías. Si no hay categorías activas, la página muestra un estado vacío con acceso al catálogo.

## Implementación
- Los valores públicos se leen mediante App\Services\Storefront\StorefrontSettings.
- Se utiliza caché de diez minutos, invalidada al guardar o eliminar ajustes desde Eloquent.
- Los valores se renderizan escapados por Blade.
- La configuración de contenido no cambia la apariencia del panel Filament.
