<x-storefront.layout title="Mi cuenta | Dubai Shop" description="{{ __('Historial de pedidos') }} de tu cuenta Dubai Shop." robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light">{{ __('MI CUENTA') }}</span><h1>Hola, <em>{{ auth()->user()->name }}.</em></h1><p>{{ __('Administra tus pedidos y direcciones de entrega.') }}</p></div></section>
    <section class="catalog-section page-container">
        @if (session('status'))<div class="cart-notice" role="status">{{ session('status') }}</div>@endif
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem"><a class="button button--outline" href="{{ route('customer.addresses') }}">{{ __('Mis direcciones') }}</a><form method="POST" action="{{ route('customer.logout') }}">@csrf<button class="button button--outline" type="submit">{{ __('Cerrar sesión') }}</button></form></div>

        @if ($loyaltyWallets->isNotEmpty())
            <section class="checkout-panel" style="margin:0 0 2rem">
                <h2>{{ __('Mi programa de fidelización') }}</h2>
                <p>{{ __('Consulta tus puntos por moneda. Los saldos no se convierten ni se combinan entre monedas.') }}</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;margin-top:1rem">
                    @foreach ($loyaltyWallets as $wallet)
                        <article style="border:1px solid rgba(201,162,39,.35);border-radius:12px;padding:1rem">
                            <span class="eyebrow">{{ $wallet->currency }}</span>
                            <strong style="display:block;font-size:2rem">{{ number_format(max(0, $wallet->available_points)) }} {{ __('puntos') }}</strong>
                            @if ($wallet->available_points < 0)
                                <p style="font-size:.85rem;color:#a16207">{{ __('Saldo por compensar: :points puntos. Las próximas acumulaciones lo cubrirán.', ['points' => number_format(abs($wallet->available_points))]) }}</p>
                            @endif
                            @if ($wallet->expiring_points_30_days > 0)
                                <p style="font-size:.85rem;color:#a16207">{{ __('Aviso: :points puntos vencen durante los próximos 30 días.', ['points' => number_format($wallet->expiring_points_30_days)]) }}</p>
                            @endif
                            @if ($wallet->recentEntries->isNotEmpty())
                                <div style="margin-top:.75rem;border-top:1px solid rgba(120,120,120,.2);padding-top:.5rem">
                                    <p style="font-size:.75rem;font-weight:600">{{ __('Movimientos recientes') }}</p>
                                    @foreach ($wallet->recentEntries as $entry)
                                        <div style="display:flex;justify-content:space-between;gap:.5rem;font-size:.8rem;margin-top:.3rem">
                                            <span>{{ str($entry->type)->replace('_',' ')->title() }}</span>
                                            <strong>{{ $entry->points_delta > 0 ? '+' : '' }}{{ number_format($entry->points_delta) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
        <h2>{{ __('Historial de pedidos') }}</h2>
        @forelse ($orders as $order)
            <article class="checkout-panel" style="margin:1rem 0"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><strong>{{ $order->number }}</strong><span>{{ $order->placed_at?->format('d/m/Y H:i') }}</span></div><p>{{ __('Estado:') }} {{ ucfirst($order->status) }} · {{ __('Pago:') }} {{ str($order->payment_status)->replace('_',' ')->title() }}</p><p>Total: {{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</p><ul>@foreach ($order->items as $item)<li>{{ $item->quantity }} × {{ $item->product_name }}</li>@endforeach</ul></article>
        @empty <p>{{ __('Aún no tienes pedidos asociados a esta cuenta.') }}</p> @endforelse
        {{ $orders->links() }}
    </section>
</x-storefront.layout>
