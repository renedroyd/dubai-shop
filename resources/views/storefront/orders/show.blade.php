<x-storefront.layout title="Pedido {{ $order->number }} | Dubai Shop" :description="__('Consulta el estado y los detalles de tu pedido.')" robots="noindex,nofollow">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light"><span></span> {{ __('PEDIDO REGISTRADO') }}</span><h1>{{ __('Gracias por tu') }} <em>{{ __('compra.') }}</em></h1><p>{{ __('Tu número de pedido es') }} {{ $order->number }}.</p></div></section>
    <section class="catalog-section page-container">
        <div class="order-confirmation">
            <div class="checkout-panel"><span class="eyebrow eyebrow--light">{{ __('ESTADO DEL PEDIDO') }}</span><h2>{{ $order->status === 'pending' ? __('Pendiente de confirmación') : str($order->status)->replace('_', ' ')->title() }}</h2>
                @if ($order->payment_status === 'awaiting_review')<p>{{ __('Hemos recibido tu comprobante. Nuestro equipo revisará la transferencia Zelle y actualizará el estado del pago.') }}</p>
                @elseif ($order->payment_status === 'pending_collection')<p>{{ __('Tu pedido quedó registrado. El pago en efectivo se realizará al momento de la entrega.') }}</p>@endif
                <p>{{ __('Enviaremos las actualizaciones a') }} <strong>{{ $order->customer_email }}</strong>.</p>
            </div>
            @if ($order->shipment)
                <div class="checkout-panel">
                    <span class="eyebrow eyebrow--light">{{ __('SEGUIMIENTO DE ENTREGA') }}</span>
                    <h2>{{ $order->shipment->status === 'delivered' ? __('Pedido entregado') : ($order->shipment->status === 'delivery_failed' ? __('Entrega pendiente de reintento') : __('Pedido en ruta')) }}</h2>
                    <p>{{ __('Código de seguimiento:') }} <strong>{{ $order->shipment->tracking_code }}</strong></p>
                    @if ($order->shipment->courier_name)
                        <p>{{ __('Repartidor:') }} <strong>{{ $order->shipment->courier_name }}</strong>
                            @if ($order->shipment->courier_phone) · {{ __('Contacto:') }} <strong>{{ $order->shipment->courier_phone }}</strong>@endif
                        </p>
                    @endif
                    @if ($order->shipment->delivered_at)
                        <p>{{ __('Entregado el') }} {{ $order->shipment->delivered_at->format('d/m/Y H:i') }}@if ($order->shipment->recipient_name) a {{ $order->shipment->recipient_name }}@endif.</p>
                    @endif
                    @if ($order->shipment->events->isNotEmpty())
                        <ol class="mt-4 space-y-3">
                            @foreach ($order->shipment->events as $event)
                                <li class="border-l-2 pl-3">
                                    <strong>{{ $event->description }}</strong>
                                    <span class="block text-sm">{{ $event->occurred_at->format('d/m/Y H:i') }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if ($order->shipment->attempts->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="font-semibold">{{ __('Intentos de entrega') }}</h3>
                            @foreach ($order->shipment->attempts as $attempt)
                                <p class="mt-2 text-sm">
                                    {{ $attempt->attempted_at->format('d/m/Y H:i') }} ·
                                    {{ $attempt->result === 'delivered' ? __('Entregado') : __('No se pudo entregar') }}
                                    @if ($attempt->note) — {{ $attempt->note }}@endif
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            <div class="checkout-panel"><span class="eyebrow eyebrow--light">{{ __('RESUMEN DEL PEDIDO') }}</span>
                @foreach ($order->items as $item)<div class="order-line"><span>{{ $item->quantity }} × {{ $item->product_name }} @if ($item->variant_name) — {{ $item->variant_name }} @endif</span><strong>{{ number_format($item->line_total_amount / 100, 2) }} {{ $item->currency }}</strong></div>@endforeach
                <div class="order-line"><span>{{ __('Subtotal de productos') }}</span><strong>{{ number_format($order->subtotal_amount / 100, 2) }} {{ $order->currency }}</strong></div>
                <div class="order-line"><span>Envío · {{ $order->shipping_zone_label }}</span><strong>{{ number_format($order->shipping_amount / 100, 2) }} {{ $order->currency }}</strong></div>
                <div class="order-line"><span>{{ __('Impuestos') }}</span><strong>0.00 {{ $order->currency }}</strong></div>
                <div class="order-line order-line--total"><span>{{ __('Total del pedido') }}</span><strong>{{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</strong></div>
                <p>{{ __('Entrega comprometida: dentro de') }} {{ $order->delivery_commitment_hours ?? 24 }} horas (hasta {{ $order->delivery_due_at?->format('d/m/Y H:i') }}).</p>
                <p>{{ __('Dirección de entrega:') }} {{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_region }}, {{ $order->shipping_country === 'CU' ? 'Cuba' : $order->shipping_country }}.</p>
            </div>
        </div>
    </section>
</x-storefront.layout>
