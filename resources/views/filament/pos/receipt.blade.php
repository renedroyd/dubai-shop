<div class="space-y-4 text-sm text-gray-900 dark:text-gray-100">
    <div class="text-center">
        <h2 class="text-xl font-semibold">Clásico Dubai</h2>
        <p>{{ $order->posTerminal?->location?->name }}</p>
        <p>{{ $order->posTerminal?->name }} · {{ $order->posTerminal?->code }}</p>
        <p class="font-semibold">{{ $order->number }}</p>
        <p>{{ $order->placed_at?->format('d/m/Y H:i') }}</p>
    </div>
    <div class="border-t border-b py-3">
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-4 py-1">
                <div>
                    <div>{{ $item->product_name }}</div>
                    @if ($item->variant_name)<div class="text-xs">{{ $item->variant_name }}</div>@endif
                    <div class="text-xs">{{ $item->quantity }} × {{ number_format($item->unit_price_amount / 100, 2) }} {{ $item->currency }}</div>
                </div>
                <div class="font-medium">{{ number_format($item->line_total_amount / 100, 2) }} {{ $item->currency }}</div>
            </div>
        @endforeach
    </div>
    <div class="flex justify-between text-base font-semibold">
        <span>{{ __('Total del pedido') }}</span>
        <span>{{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</span>
    </div>
    <div class="text-xs text-center">
        <p>{{ __('Método de cobro') }}: {{ $order->payments->first()?->method?->name }}</p>
        <p>{{ __('Atendido por') }}: {{ $order->cashier?->name }}</p>
        <p>{{ __('Gracias por su compra') }}</p>
    </div>
    <div class="flex justify-center print:hidden">
        <button type="button" onclick="window.print()" class="rounded-lg bg-gray-900 px-4 py-2 text-white">{{ __('Imprimir recibo') }}</button>
    </div>
</div>
