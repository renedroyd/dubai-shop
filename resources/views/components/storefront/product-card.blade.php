@props(['product'])

@php
    $activeVariants = $product->variants->where('is_active', true);
    $hasVariants = $activeVariants->isNotEmpty();
    $stockIsTracked = $hasVariants
        ? $activeVariants->every(fn ($variant) => $variant->inventory !== null)
        : $product->inventory !== null;
    $availableStock = $hasVariants
        ? $activeVariants->sum(fn ($variant) => $variant->inventory?->available_quantity ?? 0)
        : $product->inventory?->available_quantity;
@endphp

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
    <div class="store-product-card__stock {{ $stockIsTracked && $availableStock === 0 ? 'store-product-card__stock--empty' : '' }}" aria-live="polite">
        @if (! $stockIsTracked)
            <span class="store-product-card__stock-label">Consultar disponibilidad</span>
        @elseif ($availableStock > 0)
            <span class="store-product-card__stock-quantity">{{ number_format($availableStock) }}</span>
            <span class="store-product-card__stock-label">{{ $availableStock === 1 ? 'disponible' : 'disponibles' }}</span>
        @else
            <span class="store-product-card__stock-label">Agotado</span>
        @endif
    </div>
    <div class="store-product-card__rating" aria-label="Sin valoraciones registradas">
        <span class="store-product-card__stars" aria-hidden="true">☆☆☆☆☆</span>
        <span class="store-product-card__rating-label">Sin valoraciones</span>
    </div>
    <div class="store-product-card__actions">
        @if ($hasVariants)
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
