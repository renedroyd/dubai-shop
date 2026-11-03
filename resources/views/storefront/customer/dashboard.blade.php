<x-storefront.layout title="Mi cuenta | Dubai Shop" description="{{ __('Historial de pedidos') }} de tu cuenta Dubai Shop." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>Hola, <em>{{ auth()->user()->name }}.</em></h1><p>{{ __('Administra tus pedidos y direcciones de entrega.') }}</p></div></section>
    <section class="catalog-section page-container">
        @if (session('status'))<div class="cart-notice" role="status">{{ session('status') }}</div>@endif
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem"><a class="button button--outline" href="{{ route('customer.addresses') }}">{{ __('Mis direcciones') }}</a><form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="button button--outline" type="submit">{{ __('Cerrar sesión') }}</button></form></div>
        <h2>{{ __('Historial de pedidos') }}</h2>
        @forelse ($orders as $order)
            <article class="checkout-panel" style="margin:1rem 0"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><strong>{{ $order->number }}</strong><span>{{ $order->placed_at?->format('d/m/Y H:i') }}</span></div><p>{{ __('Estado:') }} {{ ucfirst($order->status) }} · {{ __('Pago:') }} {{ str($order->payment_status)->replace('_',' ')->title() }}</p><p>Total: {{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</p><ul>@foreach ($order->items as $item)<li>{{ $item->quantity }} × {{ $item->product_name }}</li>@endforeach</ul></article>
        @empty <p>{{ __('Aún no tienes pedidos asociados a esta cuenta.') }}</p> @endforelse
        {{ $orders->links() }}
    </section>
</x-storefront.layout>
