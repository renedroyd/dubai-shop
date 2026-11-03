<x-storefront.layout
    title="Carrito | Dubai Shop"
    description="Revisa los productos seleccionados antes de continuar."
    robots="noindex,nofollow"
>
    <section class="catalog-hero">
        <div class="page-container">
            <span class="eyebrow eyebrow--light"><span></span> {{ __('TU SELECCIÓN') }}</span>
            <h1>{{ __('Tu') }} <em>{{ __('carrito.') }}</em></h1>
            <p>{{ __('Revisa las cantidades y los productos que has elegido.') }}</p>
        </div>
    </section>

    <section class="catalog-section page-container">
        @if (session('status'))
            <p role="status" class="cart-notice">{{ session('status') }}</p>
        @endif

        @if ($cart->items->isEmpty())
            <div class="catalog-empty">
                <span>✦</span>
                <h2>{{ __('Tu carrito está vacío') }}</h2>
                <p>{{ __('Explora el catálogo y agrega tus productos favoritos.') }}</p>
                <a class="button button--gold" href="{{ route('products.index') }}">{{ __('Explorar productos') }}</a>
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
                                <h2>{{ $item->product?->name ?? '{{ __('Producto no disponible') }}' }}</h2>
                                @if ($item->variant)<p>{{ $item->variant->name }}</p>@endif
                                <span>{{ number_format($item->unit_price_amount / 100, 2) }} {{ $item->currency }} / {{ __('unidad') }}</span>
                                <div class="cart-item__actions">
                                    <form method="POST" action="{{ route('cart.items.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <label>
                                            <span class="sr-only">Cantidad</span>
                                            <input type="number" name="quantity" min="1" max="99" value="{{ $item->quantity }}">
                                        </label>
                                        <button type="submit" class="button button--outline">{{ __('Actualizar') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cart-remove">{{ __('Eliminar') }}</button>
                                    </form>
                                </div>
                            </div>
                            <strong class="cart-item__total">{{ number_format(($item->unit_price_amount * $item->quantity) / 100, 2) }} {{ $item->currency }}</strong>
                        </article>
                    @endforeach
                </div>

                <aside class="cart-summary">
                    <span class="eyebrow eyebrow--light">{{ __('RESUMEN') }}</span>
                    <h2>{{ __('Tu pedido') }}</h2>
                    <div><span>{{ __('Subtotal') }}</span><strong>{{ number_format($cart->subtotal_amount / 100, 2) }} {{ $cart->currency }}</strong></div>
                    <p>{{ __('Los gastos de envío, impuestos y disponibilidad final se calcularán durante el checkout.') }}</p>
                    <a class="button button--gold" href="{{ route("checkout.create") }}">{{ __('Continuar al checkout') }}</a>
                    <a href="{{ route('products.index') }}">{{ __('Seguir comprando') }}</a>
                </aside>
            </div>
        @endif
    </section>
</x-storefront.layout>
