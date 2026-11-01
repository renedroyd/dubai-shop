# Catálogo de Dubai Shop

## Alcance implementado

La fase de catálogo incorpora categorías jerárquicas, marcas, productos, galería de imágenes y variantes. Los registros pueden gestionarse desde el panel Filament existente, bajo el grupo **Catálogo**.

### Productos

- Nombre, slug único y SKU único.
- Categoría y marca opcionales.
- Descripción corta y descripción completa.
- Precio y precio comparativo como enteros en unidades menores (por ejemplo, 1299 representa 12.99).
- Código de moneda ISO de tres caracteres.
- Estado: borrador, publicado o archivado.
- Visibilidad y destacado.
- Metadatos SEO.
- Galería de imágenes con texto alternativo, orden e indicador de imagen principal.
- Variantes con SKU, opciones estructuradas y precio opcional.

### Categorías y marcas

Las categorías admiten jerarquía, imagen, estado y orden. Las marcas admiten descripción, logo y estado. Se usa borrado lógico para categorías, marcas y productos; las imágenes y variantes se eliminan al eliminar definitivamente su producto.

### Tienda pública

- /productos: listado paginado de productos publicados y visibles.
- Búsqueda por nombre, SKU y descripción corta.
- Filtros por categoría y marca.
- Orden por fecha, nombre y precio ascendente/descendente.
- /productos/{slug}: detalle con galería, descripción, variantes y productos relacionados.

Los borradores, archivados y productos ocultos nunca se muestran en las rutas públicas.

## Decisiones y límites

- No se guarda dinero en columnas float. Los importes se almacenan en unidades menores.
- La moneda se guarda por producto; no se realiza conversión automática.
- Las variantes aún no tienen inventario: existencias, reservas y movimientos pertenecen a la fase de inventario.
- El carrito y checkout no forman parte de esta fase.
- Las tarjetas de categorías de la portada seguirán siendo contenido editorial provisional hasta conectar el CMS y las categorías reales.
- El formulario administrativo utiliza el almacenamiento público de Laravel para imágenes; en despliegue se debe configurar el disco y ejecutar php artisan storage:link.
