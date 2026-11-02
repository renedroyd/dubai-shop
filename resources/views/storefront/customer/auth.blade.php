<x-storefront.layout :title="$mode === 'register' ? 'Crear cuenta | Dubai Shop' : 'Iniciar sesión | Dubai Shop'" description="Accede a tu cuenta de Dubai Shop." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">MI CUENTA</span><h1>{{ $mode === 'register' ? 'Crea tu cuenta.' : 'Bienvenido de nuevo.' }}</h1><p>Consulta tus pedidos y guarda tus direcciones de entrega.</p></div></section>
    <section class="catalog-section page-container"><div class="checkout-panel" style="max-width:620px;margin:auto">
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ $mode === 'register' ? route('customer.register.store') : route('customer.login.store') }}" class="checkout-fields">
            @csrf
            @if ($mode === 'register')<label class="checkout-fields__wide">Nombre completo<input name="name" value="{{ old('name') }}" autocomplete="name" required></label>@endif
            <label class="checkout-fields__wide">Correo electrónico<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label class="checkout-fields__wide">Contraseña<input type="password" name="password" autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" required></label>
            @if ($mode === 'register')<label class="checkout-fields__wide">Confirmar contraseña<input type="password" name="password_confirmation" autocomplete="new-password" required></label>@else<label><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>@endif
            <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ $mode === 'register' ? 'Crear cuenta' : 'Iniciar sesión' }}</button></div>
        </form>
        <p style="margin-top:1.5rem">@if ($mode === 'register')¿Ya tienes cuenta? <a href="{{ route('customer.login') }}">Inicia sesión</a>. @else¿Aún no tienes cuenta? <a href="{{ route('customer.register') }}">Regístrate</a>. @endif</p>
        <p>También puedes <a href="{{ route('cart.index') }}">comprar como invitado</a>, sin registrarte.</p>
    </div></section>
</x-storefront.layout>
