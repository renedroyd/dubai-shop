<div class="space-y-5 text-sm">
    <section>
        <h3 class="mb-2 font-semibold">{{ __('Datos del cliente') }}</h3>
        <p>{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}</p>
        <p>{{ $order->shipping_address }} {{ $order->shipping_address_line_2 }}</p>
        <p>{{ $order->shipping_city }}@if ($order->shipping_locality), {{ $order->shipping_locality }}@endif, {{ $order->shipping_region }} {{ $order->shipping_postal_code }} · {{ $order->shipping_country === 'CU' ? 'Cuba' : $order->shipping_country }}</p>
        <p>{{ __('Zona de envío:') }} {{ $order->shipping_zone_label ?? '—' }}</p>
        <p>{{ __('Entrega comprometida: dentro de') }} {{ $order->delivery_commitment_hours ?? 24 }} horas · {{ __('Límite:') }} {{ $order->delivery_due_at?->format('d/m/Y H:i') ?? '—' }}</p>
        @if ($order->customer_note)<p class="mt-2">{{ __('Nota:') }} {{ $order->customer_note }}</p>@endif
    </section>
    <section>
        <h3 class="mb-2 font-semibold">{{ __('Productos') }}</h3>
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-4 border-b py-2">
                <span>{{ $item->quantity }} × {{ $item->product_name }} @if ($item->variant_name) — {{ $item->variant_name }} @endif <small class="block">{{ $item->sku }}</small></span>
                <strong>{{ number_format($item->line_total_amount / 100, 2) }} {{ $item->currency }}</strong>
                @if ($order->posExchangeAsReplacement)
        <section>
            <h3 class="mb-2 font-semibold">{{ __('Cambio vinculado') }}</h3>
            <p>{{ __('Venta original:') }} {{ $order->posExchangeAsReplacement->originalOrder?->number ?? '—' }}</p>
            <p>{{ __('Devolución aplicada:') }} {{ number_format($order->posExchangeAsReplacement->returned_amount / 100, 2) }} {{ $order->posExchangeAsReplacement->currency }}</p>
            <p>{{ __('Venta de reemplazo:') }} {{ number_format($order->posExchangeAsReplacement->replacement_amount / 100, 2) }} {{ $order->posExchangeAsReplacement->currency }}</p>
            <p class="font-semibold">{{ __('Diferencia neta:') }} {{ number_format($order->posExchangeAsReplacement->difference_amount / 100, 2) }} {{ $order->posExchangeAsReplacement->currency }}</p>
            <p>{{ $order->posExchangeAsReplacement->reason }}</p>
        </section>
    @endif
    @if ($order->posExchangesAsOriginal->isNotEmpty())
        <section>
            <h3 class="mb-2 font-semibold">{{ __('Cambios realizados desde esta venta') }}</h3>
            @foreach ($order->posExchangesAsOriginal as $exchange)
                <p>{{ __('Venta de reemplazo:') }} {{ $exchange->replacementOrder?->number ?? '—' }} · {{ __('Diferencia neta:') }} {{ number_format($exchange->difference_amount / 100, 2) }} {{ $exchange->currency }}</p>
            @endforeach
        </section>
    @endif
</div>
        @endforeach
        <p class="mt-3 text-right font-semibold">{{ __('Productos:') }} {{ number_format($order->subtotal_amount / 100, 2) }} {{ $order->currency }}</p>
        <p class="text-right">{{ __('Envío:') }} {{ number_format($order->shipping_amount / 100, 2) }} {{ $order->currency }} · {{ __('Impuestos:') }} {{ number_format($order->tax_amount / 100, 2) }} {{ $order->currency }}</p>
        <p class="text-right font-bold">{{ __('Total registrado:') }} {{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</p>
    </section>
    @if ($order->shipment)
        <section>
            <h3 class="mb-2 font-semibold">{{ __('Logística y seguimiento') }}</h3>
            <p>{{ __('Código:') }} <strong>{{ $order->shipment->tracking_code }}</strong> · {{ __('Estado:') }} {{ $order->shipment->status }}</p>
            <p>{{ __('Repartidor:') }} {{ $order->shipment->courier_name ?? '{{ __('Sin asignar') }}' }} · {{ $order->shipment->courier_phone ?? '—' }}</p>
            @if ($order->shipment->events->isNotEmpty())
                <h4 class="mt-3 font-medium">{{ __('Historial') }}</h4>
                @foreach ($order->shipment->events as $event)
                    <p>{{ $event->occurred_at->format('d/m/Y H:i') }} · {{ $event->description }}</p>
                @endforeach
            @endif
            @if ($order->shipment->attempts->isNotEmpty())
                <h4 class="mt-3 font-medium">{{ __('Intentos de entrega') }}</h4>
                @foreach ($order->shipment->attempts as $attempt)
                    <p>{{ $attempt->attempted_at->format('d/m/Y H:i') }} · {{ $attempt->result }} · {{ $attempt->note }}</p>
                @endforeach
            @endif
        </section>
    @endif
    <section>
        <h3 class="mb-2 font-semibold">{{ __('Pagos') }}</h3>
        @foreach ($order->payments as $payment)
            <p>{{ $payment->method?->name ?? $payment->driver }} — {{ $payment->status }} — {{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</p>
            @if ($payment->reference)<p>{{ __('Referencia:') }} {{ $payment->reference }}</p>@endif
            @if ($payment->customer_note)<p>{{ __('Nota del cliente:') }} {{ $payment->customer_note }}</p>@endif
            @if ($payment->review_note)<p>{{ __('Nota de revisión:') }} {{ $payment->review_note }}</p>@endif
            @foreach ($payment->events as $event)
                <p class="text-xs text-gray-500">{{ $event->created_at->format('Y-m-d H:i') }} · {{ $event->from_status ?? '{{ __('creado') }}' }} → {{ $event->to_status }} · {{ $event->note }}</p>
            @endforeach
        @endforeach
    </section>
    @if ($order->posRefunds->isNotEmpty())
        <section>
            <h3 class="mb-2 font-semibold">{{ __('Devoluciones y reembolsos') }}</h3>
            @foreach ($order->posRefunds as $refund)
                <div class="border-b py-2">
                    <p class="font-medium">{{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }} · {{ $refund->status }} · {{ $refund->refunded_at->format('d/m/Y H:i') }}</p>
                    <p>{{ __('Procesado por') }}: {{ $refund->processor?->name ?? '—' }} · {{ $refund->reason }}</p>
                    @foreach ($refund->items as $refundItem)
                        <p class="text-xs">{{ $refundItem->quantity }} × {{ $refundItem->orderItem?->product_name ?? __('Producto eliminado') }}</p>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif
</div>
