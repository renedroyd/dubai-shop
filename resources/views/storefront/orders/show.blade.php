<x-storefront.layout title="Pedido {{ $order->number }} | Dubai Shop" description="Consulta el estado y los detalles de tu pedido.">
    <section class="catalog-hero"><div class="page-container"><span class="eyebrow eyebrow--light"><span></span> PEDIDO REGISTRADO</span><h1>Gracias por tu <em>compra.</em></h1><p>Tu número de pedido es {{ $order->number }}.</p></div></section>
    <section class="catalog-section page-container">
        <div class="order-confirmation">
            <div class="checkout-panel"><span class="eyebrow eyebrow--light">ESTADO DEL PEDIDO</span><h2>{{ $order->status === 'pending' ? 'Pendiente de confirmación' : str($order->status)->replace('_', ' ')->title() }}</h2>
                @if ($order->payment_status === 'awaiting_review')<p>Hemos recibido tu comprobante. Nuestro equipo revisará la transferencia Zelle y actualizará el estado del pago.</p>
                @elseif ($order->payment_status === 'pending_collection')<p>Tu pedido quedó registrado. El pago en efectivo se realizará al momento de la entrega.</p>@endif
                <p>Enviaremos las actualizaciones a <strong>{{ $order->customer_email }}</strong>.</p>
            </div>
            <div class="checkout-panel"><span class="eyebrow eyebrow--light">RESUMEN DEL PEDIDO</span>
                @foreach ($order->items as $item)<div class="order-line"><span>{{ $item->quantity }} × {{ $item->product_name }} @if ($item->variant_name) — {{ $item->variant_name }} @endif</span><strong>{{ number_format($item->line_total_amount / 100, 2) }} {{ $item->currency }}</strong></div>@endforeach
                <div class="order-line order-line--total"><span>Total de productos</span><strong>{{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</strong></div>
                <p>El envío y los impuestos se confirmarán por separado antes de procesar la entrega.</p>
            </div>
        </div>
    </section>
</x-storefront.layout>
