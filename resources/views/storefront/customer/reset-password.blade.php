<x-storefront.layout :title="__('Restablecer contraseña | Dubai Shop')" :description="__('Crea una nueva contraseña para tu cuenta.')" robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>{{ __('Nueva') }} <em>{{ __('contraseña.') }}</em></h1><p>{{ __('Elige una contraseña segura para proteger tu cuenta.') }}</p></div></section>
    <section class="catalog-section page-container"><div class="checkout-panel" style="max-width:620px;margin:auto">
        @if ($errors->any())<div class="cart-notice" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('customer.password.update') }}" class="checkout-fields">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="checkout-fields__wide">{{ __('Correo electrónico') }}<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></label>
            <label class="checkout-fields__wide">{{ __('Nueva contraseña') }}<input type="password" name="password" autocomplete="new-password" required></label>
            <label class="checkout-fields__wide">{{ __('Confirmar contraseña') }}<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <div class="checkout-fields__wide"><button class="button button--gold" type="submit">{{ __('Guardar nueva contraseña') }}</button></div>
        </form>
    </div></section>
</x-storefront.layout>
