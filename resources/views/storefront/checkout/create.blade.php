 <x-storefront.layout title="Finalizar compra | Dubai Shop" description="Completa tus datos y selecciona cómo deseas pagar tu pedido." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light"><span></span> CHECKOUT SEGURO</span><h1>Finaliza tu <em>pedido.</em></h1><p>Entregas disponibles únicamente en Cuba.</p></div></section>
    <section class="catalog-section page-container">
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form class="checkout-layout" method="POST" action="{{ route('checkout.store') }}" enctype="multipart/form-data" id="checkout-form">
            @csrf
            <div class="checkout-main">
                <section class="checkout-panel"><span class="eyebrow eyebrow--light">01 / TUS DATOS</span><h2>Información de contacto</h2><div class="checkout-fields">
                    <label>Nombre completo<input name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required></label>
                    <label>Correo electrónico<input type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required></label>
                    <label>Teléfono<input name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" required></label>
                </div></section>
                <section class="checkout-panel"><span class="eyebrow eyebrow--light">02 / ENTREGA EN CUBA</span><h2>Dirección de entrega</h2><div class="checkout-fields">
                    <label class="checkout-fields__wide">Dirección exacta<input name="shipping_address" value="{{ old('shipping_address') }}" autocomplete="address-line1" required></label>
                    <label class="checkout-fields__wide">Apartamento, piso o referencia (opcional)<input name="shipping_address_line_2" value="{{ old('shipping_address_line_2') }}" autocomplete="address-line2"></label>
                    <label>Provincia<select name="shipping_region" id="shipping-region" required><option value="">Selecciona una provincia</option>@foreach ($provinces as $value => $label)<option value="{{ $value }}" @selected(old('shipping_region') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label>Municipio<input name="shipping_city" id="shipping-city" value="{{ old('shipping_city') }}" autocomplete="address-level2" required></label>
                    <label class="checkout-fields__wide">Localidad / reparto (opcional)<input name="shipping_locality" id="shipping-locality" value="{{ old('shipping_locality') }}" placeholder="Ej. Vedado, Miramar, reparto..."></label>
                    <input type="hidden" name="shipping_country" value="CU">
                    <label>Código postal (opcional)<input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" autocomplete="postal-code"></label>
                    <label class="checkout-fields__wide">Nota para la entrega<textarea name="customer_note" rows="3">{{ old('customer_note') }}</textarea></label>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button class="button button--outline" type="button" id="quote-shipping">Calcular costo de envío</button>
                    <p id="shipping-quote-message" role="status" aria-live="polite">Completa provincia y municipio para calcular el envío.</p>
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
                <div><span>Envío</span><strong id="shipping-quote-amount">Pendiente de cálculo</strong></div>
                <div><span>Impuestos</span><strong>0.00 {{ $cart->currency }}</strong></div>
                <div><span>Total</span><strong id="checkout-total">{{ number_format($cart->subtotal_amount / 100, 2) }} {{ $cart->currency }} + envío</strong></div>
                <p>El envío se calcula según la provincia, municipio y localidad. Dubai Shop no aplica impuestos.</p>
                <button class="button button--gold" type="submit" id="checkout-submit" @disabled($paymentMethods->isEmpty())>Confirmar pedido</button><a href="{{ route('cart.index') }}">Volver al carrito</a>
            </aside>
        </form>
    </section>
    <script>
        (() => {
            const form = document.getElementById('checkout-form');
            const button = document.getElementById('quote-shipping');
            const submit = document.getElementById('checkout-submit');
            const message = document.getElementById('shipping-quote-message');
            const amount = document.getElementById('shipping-quote-amount');
            const total = document.getElementById('checkout-total');
            const subtotal = {{ $cart->subtotal_amount / 100 }};
            const currency = @json($cart->currency);
            let quotedKey = null;
            const fields = ['shipping-region', 'shipping-city', 'shipping-locality'];
            const currentKey = () => fields.map(id => document.getElementById(id).value.trim()).join('|');
            const invalidate = () => { quotedKey = null; submit.disabled = true; amount.textContent = 'Pendiente de cálculo'; total.textContent = subtotal.toFixed(2) + ' ' + currency + ' + envío'; };
            fields.forEach(id => { document.getElementById(id).addEventListener('input', invalidate); document.getElementById(id).addEventListener('change', invalidate); });
            button.addEventListener('click', async () => {
                const province = document.getElementById('shipping-region').value;
                const city = document.getElementById('shipping-city').value.trim();
                if (!province || !city) { message.textContent = 'Selecciona la provincia e indica el municipio.'; return; }
                button.disabled = true;
                const requestKey = currentKey();
                message.textContent = 'Calculando tarifa...';
                invalidate();
                try {
                    const params = new URLSearchParams({
                        shipping_region: province,
                        shipping_city: city,
                        shipping_locality: document.getElementById('shipping-locality').value.trim(),
                        shipping_country: 'CU'
                    });
                    const response = await fetch(@json(route('checkout.shipping-quote')) + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'No fue posible calcular el envío para esa dirección.');
                    if (currentKey() !== requestKey) throw new Error('La dirección cambió durante el cálculo. Vuelve a calcular el envío.');
                    quotedKey = requestKey;
                    amount.textContent = Number(data.amount).toFixed(2) + ' ' + data.currency;
                    total.textContent = (subtotal + Number(data.amount)).toFixed(2) + ' ' + data.currency;
                    message.textContent = 'Tarifa aplicada: ' + data.zone + '.';
                    submit.disabled = false;
                } catch (error) {
                    message.textContent = error.message;
                } finally {
                    button.disabled = false;
                }
            });
            form.addEventListener('submit', event => {
                if (!quotedKey || quotedKey !== currentKey()) {
                    event.preventDefault();
                    message.textContent = 'Calcula y revisa el costo de envío antes de confirmar el pedido.';
                    submit.disabled = true;
                }
            });
            submit.disabled = true;
        })();
    </script>
</x-storefront.layout>