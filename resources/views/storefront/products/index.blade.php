<x-storefront.layout
    title="Productos | Dubai Shop"
    description="Explora el catálogo de Dubai Shop y encuentra productos seleccionados para ti."
>
    <section class="catalog-hero">
        <div class="page-container">
            <span class="eyebrow eyebrow--light"><span></span> CATÁLOGO DUBAI SHOP</span>
            <h1>Descubre algo <em>especial.</em></h1>
            <p>Una selección para encontrar justo lo que estás buscando.</p>
        </div>
    </section>

    <section class="catalog-section page-container">
        <form class="catalog-toolbar" method="GET" action="{{ route('products.index') }}">
            <label class="catalog-search">
                <span class="sr-only">Buscar productos</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="¿Qué estás buscando?">
                <button type="submit" aria-label="Buscar">⌕</button>
            </label>
            <select name="category" aria-label="Filtrar por categoría" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="brand" aria-label="Filtrar por marca" onchange="this.form.submit()">
                <option value="">Todas las marcas</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->slug }}" @selected(($filters['brand'] ?? '') === $brand->slug)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <select name="sort" aria-label="Ordenar productos" onchange="this.form.submit()">
                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Más recientes</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Precio: menor a mayor</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Precio: mayor a menor</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nombre</option>
            </select>
        </form>

        <div class="catalog-results-heading">
            <span>{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</span>
            @if (filled($filters['q'] ?? null))
                <span>Resultados para “{{ $filters['q'] }}”</span>
            @endif
        </div>

        @if ($products->isNotEmpty())
            <div class="store-product-grid">
                @foreach ($products as $product)
                    <a href="{{ route('products.show', $product) }}" class="store-product-card">
                        <div class="store-product-card__image">
                            @if ($product->images->isNotEmpty())
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($product->images->first()->path) }}" alt="{{ $product->images->first()->alt_text ?: $product->name }}" loading="lazy">
                            @else
                                <span class="store-product-card__placeholder">D.</span>
                            @endif
                            @if ($product->is_featured)<span class="store-product-card__badge">Destacado</span>@endif
                        </div>
                        <div class="store-product-card__meta">
                            <span>{{ $product->brand?->name ?? $product->category?->name ?? 'Dubai Shop' }}</span>
                            <span>{{ $product->sku }}</span>
                        </div>
                        <h2>{{ $product->name }}</h2>
                        <p>{{ $product->short_description }}</p>
                        <strong>{{ $product->formatted_price }}</strong>
                    </a>
                @endforeach
            </div>
            <div class="catalog-pagination">{{ $products->links() }}</div>
        @else
            <div class="catalog-empty">
                <span>✦</span>
                <h2>No encontramos productos</h2>
                <p>Prueba con otra búsqueda o elimina alguno de los filtros.</p>
                <a class="button button--outline" href="{{ route('products.index') }}">Ver todo el catálogo</a>
            </div>
        @endif
    </section>
</x-storefront.layout>
