<x-storefront.layout title="Finalizar compra | Dubai Shop" description="Completa tus datos y selecciona cómo deseas pagar tu pedido." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light"><span></span> CHECKOUT SEGURO</span><h1>Finaliza tu <em>pedido.</em></h1><p>Completa tus datos de entrega y elige tu método de pago.</p></div></section>
    <section class="catalog-section page-container">
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form class="checkout-layout" method="POST" action="{{ route('checkout.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="checkout-main">
                <section class="checkout-panel"><span class="eyebrow eyebrow--light">01 / TUS DATOS</span><h2>Información de contacto</h2><div class="checkout-fields">
                    <label>Nombre completo<input name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required></label>
                    <label>Correo electrónico<input type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required></label>
                    <label>Teléfono<input name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" required></label>
                </div></section>
                <section class="checkout-panel"><span class="eyebrow eyebrow--light">02 / ENTREGA</span><h2>Dirección de entrega</h2><div class="checkout-fields">
                    <label class="checkout-fields__wide">Dirección<input name="shipping_address" value="{{ old('shipping_address') }}" autocomplete="address-line1" required></label>
                    <label class="checkout-fields__wide">Apartamento, piso o referencia (opcional)<input name="shipping_address_line_2" value="{{ old('shipping_address_line_2') }}" autocomplete="address-line2"></label>
                    <label>Ciudad<input name="shipping_city" value="{{ old('shipping_city') }}" autocomplete="address-level2" required></label>
                    <label>Provincia / Estado<input name="shipping_region" value="{{ old('shipping_region') }}" autocomplete="address-level1"></label>
                    <label>Código postal<input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" autocomplete="postal-code"></label>
                    <label>País (código ISO de 2 letras)<input name="shipping_country" value="{{ old('shipping_country', 'US') }}" minlength="2" maxlength="2" autocomplete="country" required></label>
                    <label class="checkout-fields__wide">Nota para la entrega<textarea name="customer_note" rows="3">{{ old('customer_note') }}</textarea></label>
                </div></section>
                <section class="checkout-panel"><span class="eyebrow eyebrow--light">03 / PAGO</span><h2>Selecciona cómo pagar</h2><div class="payment-options">
                    @forelse ($paymentMethods as $option)
                        <label class="payment-option"><input type="radio" name="payment_method_id" value="{{ $option['method']->id }}" @checked(old('payment_method_id') == $option['method']->id || (!old('payment_method_id') && $loop->first)) required><span><strong>{{ $option['method']->name }}</strong><small>{{ $option['method']->description }}</small></span></label>
                        @if ($option['method']->driver === 'zelle_manual')
                            <div class="payment-details"><p>{{ $option['method']->instructions }}</p>
                                @foreach ($option['instructions'] as $key => $value) @if (filled($value))<p><strong>{{ str($key)->replace('_', ' ')->title() }}:</strong> {{ $value }}</p>@endif @endforeach
                                <label>Referencia de la transferencia (opcional)<input name="payment_reference" value="{{ old('payment_reference') }}"></label>
                                <label>Comprobante de pago (JPG, PNG o PDF; máximo 5 MB)<input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf"></label>
                                <label>Nota para la revisión (opcional)<textarea name="payment_note" rows="2">{{ old('payment_note') }}</textarea></label>
                            </div>
                        @endif
                    @empty <p>No hay métodos de pago disponibles en este momento.</p> @endforelse
                </div></section>
            </div>
            <aside class="cart-summary checkout-summary"><span class="eyebrow eyebrow--light">RESUMEN</span><h2>Tu pedido</h2>
                @foreach ($cart->items as $item)<div><span>{{ $item->quantity }} × {{ $item->product?->name }}</span><strong>{{ number_format($item->unit_price_amount * $item->quantity / 100, 2) }} {{ $item->currency }}</strong></div>@endforeach
                <div><span>Subtotal</span><strong>{{ number_format($cart->subtotal_amount / 100, 2) }} {{ $cart->currency }}</strong></div>
                <div><span>Envío</span><strong>Por confirmar</strong></div>
                <p>El importe mostrado no incluye gastos de envío ni impuestos. Se confirmarán antes de procesar la entrega.</p>
                <button class="button button--gold" type="submit" @disabled($paymentMethods->isEmpty())>Confirmar pedido</button><a href="{{ route('cart.index') }}">Volver al carrito</a>
            </aside>
        </form>
    </section>
</x-storefront.layout>
