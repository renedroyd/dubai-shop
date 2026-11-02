<div class="space-y-5 text-sm">
    <section>
        <h3 class="mb-2 font-semibold">Datos del cliente</h3>
        <p>{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}</p>
        <p>{{ $order->shipping_address }} {{ $order->shipping_address_line_2 }}</p>
        <p>{{ $order->shipping_city }}@if ($order->shipping_locality), {{ $order->shipping_locality }}@endif, {{ $order->shipping_region }} {{ $order->shipping_postal_code }} · {{ $order->shipping_country === 'CU' ? 'Cuba' : $order->shipping_country }}</p>
        <p>Zona de envío: {{ $order->shipping_zone_label ?? '—' }}</p>
        @if ($order->customer_note)<p class="mt-2">Nota: {{ $order->customer_note }}</p>@endif
    </section>
    <section>
        <h3 class="mb-2 font-semibold">Productos</h3>
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-4 border-b py-2">
                <span>{{ $item->quantity }} × {{ $item->product_name }} @if ($item->variant_name) — {{ $item->variant_name }} @endif <small class="block">{{ $item->sku }}</small></span>
                <strong>{{ number_format($item->line_total_amount / 100, 2) }} {{ $item->currency }}</strong>
            </div>
        @endforeach
        <p class="mt-3 text-right font-semibold">Productos: {{ number_format($order->subtotal_amount / 100, 2) }} {{ $order->currency }}</p>
        <p class="text-right">Envío: {{ number_format($order->shipping_amount / 100, 2) }} {{ $order->currency }} · Impuestos: {{ number_format($order->tax_amount / 100, 2) }} {{ $order->currency }}</p>
        <p class="text-right font-bold">Total registrado: {{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</p>
    </section>
    <section>
        <h3 class="mb-2 font-semibold">Pagos</h3>
        @foreach ($order->payments as $payment)
            <p>{{ $payment->method?->name ?? $payment->driver }} — {{ $payment->status }} — {{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</p>
            @if ($payment->reference)<p>Referencia: {{ $payment->reference }}</p>@endif
            @if ($payment->customer_note)<p>Nota del cliente: {{ $payment->customer_note }}</p>@endif
            @if ($payment->review_note)<p>Nota de revisión: {{ $payment->review_note }}</p>@endif
            @foreach ($payment->events as $event)
                <p class="text-xs text-gray-500">{{ $event->created_at->format('Y-m-d H:i') }} · {{ $event->from_status ?? 'creado' }} → {{ $event->to_status }} · {{ $event->note }}</p>
            @endforeach
        @endforeach
    </section>
</div>
