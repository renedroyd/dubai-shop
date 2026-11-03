<x-storefront.layout title="Mis direcciones | Dubai Shop" description="Gestiona tus direcciones de entrega." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>{{ __('Mis') }} <em>{{ __('direcciones.') }}</em></h1><p>{{ __('Guarda tus destinos habituales para agilizar tus próximas compras.') }}</p></div></section>
    <section class="catalog-section page-container">
        @if (session('status'))<div class="cart-notice" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @foreach ($addresses as $address)
            <article class="checkout-panel" style="margin:1rem 0"><strong>{{ $address->label }} @if($address->is_default) · {{ __('Predeterminada') }} @endif</strong><p>{{ $address->recipient_name }} · {{ $address->phone }}</p><p>{{ $address->address }} {{ $address->address_line_2 }} · {{ $address->city }}, {{ $address->region }} · Cuba</p>
                @unless($address->is_default)<form method="POST" action="{{ route('customer.addresses.default', $address) }}" style="display:inline">@csrf @method('PATCH')<button class="button button--outline">{{ __('Usar como predeterminada') }}</button></form>@endunless
                <details style="margin-top:1rem"><summary style="cursor:pointer">{{ __('Editar dirección') }}</summary>
                    <form method="POST" action="{{ route('customer.addresses.update', $address) }}" class="checkout-fields" style="margin-top:1rem">@csrf @method('PUT')
                        <label>{{ __('Nombre de la dirección') }}<input name="label" value="{{ $address->label }}" required></label>
                        <label>{{ __('Nombre del destinatario') }}<input name="recipient_name" value="{{ $address->recipient_name }}" required></label>
                        <label>{{ __('Teléfono') }}<input name="phone" value="{{ $address->phone }}" required></label>
                        <label class="checkout-fields__wide">{{ __('Dirección exacta') }}<input name="address" value="{{ $address->address }}" required></label>
                        <label class="checkout-fields__wide">{{ __('Complemento') }}<input name="address_line_2" value="{{ $address->address_line_2 }}"></label>
                        <label>{{ __('Provincia') }}<select name="region" required>@foreach(\App\Services\Shipping\ShippingRateService::provinces() as $key => $label)<option value="{{ $key }}" @selected($address->region === $key)>{{ $label }}</option>@endforeach</select></label>
                        <label>{{ __('Municipio') }}<input name="city" value="{{ $address->city }}" required></label>
                        <label>{{ __('Localidad') }}<input name="locality" value="{{ $address->locality }}"></label>
                        <label>{{ __('Código postal') }}<input name="postal_code" value="{{ $address->postal_code }}"></label>
                        <input type="hidden" name="country" value="CU">
                        <label class="checkout-fields__wide"><input type="checkbox" name="is_default" value="1" @checked($address->is_default)> {{ __('Establecer como predeterminada') }}</label>
                        <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ __('Guardar cambios') }}</button></div>
                    </form>
                </details>
                <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}" style="display:inline">@csrf @method('DELETE')<button class="button button--outline" onclick="return confirm('{{ __('¿Eliminar esta dirección?') }}')">Eliminar</button></form>
            </article>
        @endforeach
        <section class="checkout-panel" style="margin-top:2rem"><h2>{{ __('Agregar dirección') }}</h2>
            <form method="POST" action="{{ route('customer.addresses.store') }}" class="checkout-fields">@csrf
                <label>{{ __('Nombre de la dirección') }}<input name="label" value="{{ old('label', 'Casa') }}" required></label>
                <label>{{ __('Nombre del destinatario') }}<input name="recipient_name" value="{{ old('recipient_name', auth()->user()->name) }}" required></label>
                <label>{{ __('Teléfono') }}<input name="phone" required></label>
                <label class="checkout-fields__wide">{{ __('Dirección exacta') }}<input name="address" required></label>
                <label class="checkout-fields__wide">{{ __('Complemento') }}<input name="address_line_2"></label>
                <label>{{ __('Provincia') }}<select name="region" required>@foreach(\App\Services\Shipping\ShippingRateService::provinces() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label>{{ __('Municipio') }}<input name="city" required></label>
                <label>{{ __('Localidad') }}<input name="locality"></label>
                <label>{{ __('Código postal') }}<input name="postal_code"></label>
                <input type="hidden" name="country" value="CU">
                <label class="checkout-fields__wide"><input type="checkbox" name="is_default" value="1"> {{ __('Establecer como predeterminada') }}</label>
                <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ __('Guardar dirección') }}</button><a href="{{ route('customer.dashboard') }}">{{ __('Volver a mi cuenta') }}</a></div>
            </form>
        </section>
    </section>
</x-storefront.layout>
