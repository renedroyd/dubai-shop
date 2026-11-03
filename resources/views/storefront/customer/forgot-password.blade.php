<x-storefront.layout :title="__('Recuperar contraseña | Dubai Shop')" :description="__('Solicita un enlace para recuperar el acceso a tu cuenta.')" robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>{{ __('Recupera tu') }} <em>{{ __('acceso.') }}</em></h1><p>{{ __('Te enviaremos instrucciones al correo asociado a tu cuenta.') }}</p></div></section>
    <section class="catalog-section page-container"><div class="checkout-panel" style="max-width:620px;margin:auto">
        @if (session('status'))<div class="cart-notice" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('customer.password.email') }}" class="checkout-fields">@csrf
            <label class="checkout-fields__wide">{{ __('Correo electrónico') }}<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
            <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ __('Enviar enlace de recuperación') }}</button></div>
        </form>
        <p style="margin-top:1.5rem"><a href="{{ route('customer.login') }}">{{ __('Volver a iniciar sesión') }}</a></p>
    </div></section>
</x-storefront.layout>
