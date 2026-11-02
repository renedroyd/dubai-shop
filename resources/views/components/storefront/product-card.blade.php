@props(['product'])

<article class="store-product-card">
    <a href="{{ route('products.show', $product) }}" class="store-product-card__content">
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
        @if ($product->short_description)<p>{{ $product->short_description }}</p>@endif
        <strong>{{ $product->formatted_price }}</strong>
    </a>
    <div class="store-product-card__rating" aria-label="Sin valoraciones registradas">
        <span class="store-product-card__stars" aria-hidden="true">☆☆☆☆☆</span>
        <span class="store-product-card__rating-label">Sin valoraciones</span>
    </div>
    <div class="store-product-card__actions">
        @if ($product->variants->isNotEmpty())
            <a href="{{ route('products.show', $product) }}">Elegir opciones <span aria-hidden="true">↗</span></a>
        @else
            <form method="POST" action="{{ route('cart.items.store') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="button">Agregar al carrito <span aria-hidden="true">↗</span></button>
            </form>
        @endif
    </div>
</article>
