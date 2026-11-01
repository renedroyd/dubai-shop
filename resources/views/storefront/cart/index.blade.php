<x-storefront.layout
    title="Carrito | Dubai Shop"
    description="Revisa los productos seleccionados antes de continuar."
>
    <section class="catalog-hero">
        <div class="page-container">
            <span class="eyebrow eyebrow--light"><span></span> TU SELECCIÓN</span>
            <h1>Tu <em>carrito.</em></h1>
            <p>Revisa las cantidades y los productos que has elegido.</p>
        </div>
    </section>

    <section class="catalog-section page-container">
        @if (session('status'))
            <p role="status" class="cart-notice">{{ session('status') }}</p>
        @endif

        @if ($cart->items->isEmpty())
            <div class="catalog-empty">
                <span>✦</span>
                <h2>Tu carrito está vacío</h2>
                <p>Explora el catálogo y agrega tus productos favoritos.</p>
                <a class="button button--gold" href="{{ route('products.index') }}">Explorar productos</a>
            </div>
        @else
            <div class="cart-layout">
                <div class="cart-items">
                    @foreach ($cart->items as $item)
                        <article class="cart-item">
                            <div class="cart-item__image">
                                @if ($item->product?->images?->isNotEmpty())
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($item->product->images->first()->path) }}" alt="{{ $item->product->name }}">
                                @else
                                    <span>D.</span>
                                @endif
                            </div>
                            <div class="cart-item__details">
                                <h2>{{ $item->product?->name ?? 'Producto no disponible' }}</h2>
                                @if ($item->variant)<p>{{ $item->variant->name }}</p>@endif
                                <span>{{ number_format($item->unit_price_amount / 100, 2) }} {{ $item->currency }} / unidad</span>
                                <div class="cart-item__actions">
                                    <form method="POST" action="{{ route('cart.items.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <label>
                                            <span class="sr-only">Cantidad</span>
                                            <input type="number" name="quantity" min="1" max="99" value="{{ $item->quantity }}">
                                        </label>
                                        <button type="submit" class="button button--outline">Actualizar</button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cart-remove">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                            <strong class="cart-item__total">{{ number_format(($item->unit_price_amount * $item->quantity) / 100, 2) }} {{ $item->currency }}</strong>
                        </article>
                    @endforeach
                </div>

                <aside class="cart-summary">
                    <span class="eyebrow eyebrow--light">RESUMEN</span>
                    <h2>Tu pedido</h2>
                    <div><span>Subtotal</span><strong>{{ number_format($cart->subtotal_amount / 100, 2) }} {{ $cart->currency }}</strong></div>
                    <p>Los gastos de envío, impuestos y disponibilidad final se calcularán durante el checkout.</p>
                    <button class="button button--gold" type="button" disabled>Continuar al checkout próximamente</button>
                    <a href="{{ route('products.index') }}">Seguir comprando</a>
                </aside>
            </div>
        @endif
    </section>
</x-storefront.layout>
