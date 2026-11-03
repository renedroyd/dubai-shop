<x-storefront.layout :title="$mode === 'register' ? __('Crear cuenta').' | Dubai Shop' : __('Iniciar sesión').' | Dubai Shop'" description="Accede a tu cuenta de Dubai Shop." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>{{ $mode === 'register' ? __('Crea tu cuenta.') : __('Bienvenido de nuevo.') }}</h1><p>{{ __('Consulta tus pedidos y guarda tus direcciones de entrega.') }}</p></div></section>
    <section class="catalog-section page-container"><div class="checkout-panel" style="max-width:620px;margin:auto">
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ $mode === 'register' ? route('customer.register.store') : route('customer.login.store') }}" class="checkout-fields">
            @csrf
            @if ($mode === 'register')<label class="checkout-fields__wide">{{ __('Nombre completo') }}<input name="name" value="{{ old('name') }}" autocomplete="name" required></label>@endif
            <label class="checkout-fields__wide">{{ __('Correo electrónico') }}<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label class="checkout-fields__wide">{{ __('Contraseña') }}<input type="password" name="password" autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" required></label>
            @if ($mode === 'register')<label class="checkout-fields__wide">{{ __('Confirmar contraseña') }}<input type="password" name="password_confirmation" autocomplete="new-password" required></label>@else<label><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>@endif
            <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ $mode === 'register' ? __('Crear cuenta') : __('Iniciar sesión') }}</button></div>
        </form>
        <p style="margin-top:1.5rem">@if ($mode === 'register'){{ __('¿Ya tienes cuenta?') }} <a href="{{ route('customer.login') }}">{{ __('Inicia sesión') }}</a>. @else{{ __('¿Aún no tienes cuenta?') }} <a href="{{ route('customer.register') }}">{{ __('Regístrate') }}</a>. @endif</p>
        @if ($mode === 'login')<p><a href="{{ route('customer.password.request') }}">{{ __('¿Olvidaste tu contraseña?') }}</a></p>@endif
        <p>{{ __('También puedes') }} <a href="{{ route('cart.index') }}">{{ __('comprar como invitado') }}</a>, sin registrarte.</p>
    </div></section>
</x-storefront.layout>
