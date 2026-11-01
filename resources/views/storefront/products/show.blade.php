<x-storefront.layout
    :title="$product->meta_title ?: $product->name.' | Dubai Shop'"
    :description="$product->meta_description ?: ($product->short_description ?: 'Descubre '.$product->name.' en Dubai Shop.')"
>
    <section class="product-detail page-container">
        <nav class="product-breadcrumb" aria-label="Ruta de navegación">
            <a href="{{ route('home') }}">Inicio</a><span>/</span><a href="{{ route('products.index') }}">Productos</a>
            @if ($product->category)<span>/</span><a href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>@endif
            <span>/</span><span>{{ $product->name }}</span>
        </nav>
        <div class="product-detail__grid">
            <div class="product-gallery">
                @forelse ($product->images as $image)
                    <figure><img src="{{ \Illuminate\Support\Facades\Storage::url($image->path) }}" alt="{{ $image->alt_text ?: $product->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}"></figure>
                @empty
                    <div class="product-gallery__placeholder">D.</div>
                @endforelse
            </div>
            <div class="product-detail__copy">
                <span class="eyebrow eyebrow--light"><span></span> {{ $product->brand?->name ?? 'DUBAI SHOP' }}</span>
                <h1>{{ $product->name }}</h1>
                <span class="product-detail__sku">SKU: {{ $product->sku }}</span>
                <div class="product-detail__price">{{ $product->formatted_price }}</div>
                @if ($product->short_description)<p class="product-detail__lead">{{ $product->short_description }}</p>@endif
                @if ($product->description)<div class="product-detail__description">{!! nl2br(e($product->description)) !!}</div>@endif
                @if ($product->variants->isNotEmpty())
                    <div class="product-detail__variants">
                        <h2>Opciones disponibles</h2>
                        @foreach ($product->variants as $variant)
                            <span>{{ $variant->name }} @if ($variant->price_amount) · {{ number_format($variant->price_amount / 100, 2) }} {{ $product->currency }} @endif</span>
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('products.index') }}" class="button button--gold">Seguir explorando <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>

    @if ($relatedProducts->isNotEmpty())
        <section class="catalog-related page-container">
            <div class="section-heading"><span class="eyebrow eyebrow--light"><span></span> TAMBIÉN TE PUEDE INTERESAR</span><h2>Continúa descubriendo.</h2></div>
            <div class="store-product-grid">
                @foreach ($relatedProducts as $related)
                    <a href="{{ route('products.show', $related) }}" class="store-product-card">
                        <div class="store-product-card__image">
                            @if ($related->images->isNotEmpty())
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($related->images->first()->path) }}" alt="{{ $related->images->first()->alt_text ?: $related->name }}" loading="lazy">
                            @else
                                <span class="store-product-card__placeholder">D.</span>
                            @endif
                        </div>
                        <div class="store-product-card__meta"><span>{{ $related->brand?->name ?? 'Dubai Shop' }}</span><span>{{ $related->sku }}</span></div>
                        <h2>{{ $related->name }}</h2>
                        <strong>{{ $related->formatted_price }}</strong>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-storefront.layout>
