@php
    $receiptWidth = in_array($order->posTerminal?->receipt_width, ['58', '80'], true) ? $order->posTerminal->receipt_width : '80';
    $receiptCopies = max(1, min(3, (int) ($order->posTerminal?->receipt_copies ?? 1)));
    $receiptLines = [
        'CLÁSICO DUBAI',
        $order->posTerminal?->location?->name ?? '',
        $order->posTerminal?->name ?? '',
        $order->number,
        $order->placed_at?->format('d/m/Y H:i') ?? '',
        str_repeat('-', 32),
    ];
    foreach ($order->items as $item) {
        $receiptLines[] = $item->product_name.($item->variant_name ? ' · '.$item->variant_name : '');
        $receiptLines[] = $item->quantity.' x '.number_format($item->unit_price_amount / 100, 2).' = '.number_format($item->line_total_amount / 100, 2).' '.$item->currency;
    }
    $receiptLines[] = str_repeat('-', 32);
    $receiptLines[] = 'Subtotal: '.number_format($order->subtotal_amount / 100, 2).' '.$order->currency;
    if (($order->discount_amount ?? 0) > 0) $receiptLines[] = 'Descuentos: -'.number_format($order->discount_amount / 100, 2).' '.$order->currency;
    $receiptLines[] = 'TOTAL: '.number_format($order->total_amount / 100, 2).' '.$order->currency;
    foreach ($order->payments as $payment) {
        $receiptLines[] = ($payment->method?->name ?? $payment->driver).': '.number_format($payment->amount / 100, 2).' '.$payment->currency;
    }
    $receiptLines[] = 'Atendido por: '.($order->cashier?->name ?? '');
    $receiptLines[] = 'Gracias por su compra';
    $receiptText = implode("\n", array_filter($receiptLines, fn ($line) => $line !== ''));
@endphp
<style>
    @page { size: {{ $receiptWidth }}mm auto; margin: 2mm; }
    #pos-receipt { width: {{ $receiptWidth }}mm; max-width: 100%; margin: 0 auto; color: #111827; background: #fff; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .receipt-copy { padding: 3mm; font-size: 11px; line-height: 1.35; }
    .receipt-copy + .receipt-copy { border-top: 1px dashed #9ca3af; margin-top: 1rem; padding-top: 1rem; }
    @media print {
        body * { visibility: hidden !important; }
        #pos-receipt, #pos-receipt * { visibility: visible !important; }
        #pos-receipt { position: absolute; inset: 0; width: {{ $receiptWidth }}mm; max-width: none; margin: 0; }
        .receipt-copy { break-inside: avoid; page-break-inside: avoid; }
        .receipt-copy + .receipt-copy { break-before: page; page-break-before: always; border: 0; margin: 0; padding-top: 3mm; }
        .pos-receipt-actions { display: none !important; }
    }
</style>
<div id="pos-receipt" class="rounded-lg bg-white text-gray-900">
    @for ($copy = 1; $copy <= $receiptCopies; $copy++)
        <div class="receipt-copy space-y-3">
            <div class="text-center">
                <h2 class="text-base font-bold tracking-wide">Clásico Dubai</h2>
                <p>{{ $order->posTerminal?->location?->name }}</p>
                <p>{{ $order->posTerminal?->name }} · {{ $order->posTerminal?->code }}</p>
                <p class="mt-1 font-bold">{{ $order->number }}</p>
                <p>{{ $order->placed_at?->format('d/m/Y H:i') }}</p>
                <p class="mt-1 text-[9px] uppercase tracking-widest">{{ $copy === 1 ? __('Original') : __('Copia :number', ['number' => $copy - 1]) }}</p>
            </div>
            <div class="border-y border-dashed border-gray-400 py-2">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-2 py-1">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ $item->product_name }}</div>
                            @if ($item->variant_name)<div>{{ $item->variant_name }}</div>@endif
                            @if ($item->sku)<div class="text-[9px]">{{ $item->sku }}</div>@endif
                            @php($originalUnitPrice = $item->original_unit_price_amount ?? $item->unit_price_amount)
                            <div>{{ $item->quantity }} × @if ($item->discount_amount > 0)<span class="line-through">{{ number_format($originalUnitPrice / 100, 2) }}</span> {{ number_format($item->unit_price_amount / 100, 2) }} @else {{ number_format($item->unit_price_amount / 100, 2) }} @endif</div>
                            @if ($item->discount_amount > 0)<div>{{ __('Descuento') }}: −{{ number_format($item->discount_amount / 100, 2) }} {{ $item->currency }}</div>@endif
                        </div>
                        <div class="shrink-0 text-right font-semibold">{{ number_format($item->line_total_amount / 100, 2) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="space-y-1">
                <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span>{{ number_format($order->subtotal_amount / 100, 2) }} {{ $order->currency }}</span></div>
                @if (($order->discount_amount ?? 0) > 0)
                    <div class="flex justify-between"><span>{{ __('Descuentos') }}</span><span>−{{ number_format($order->discount_amount / 100, 2) }} {{ $order->currency }}</span></div>
                @endif
                <div class="flex justify-between text-sm font-bold"><span>{{ __('TOTAL') }}</span><span>{{ number_format($order->total_amount / 100, 2) }} {{ $order->currency }}</span></div>
            </div>
            <div class="border-t border-dashed border-gray-400 pt-2">
                <p class="mb-1 font-semibold">{{ __('Pagos') }}</p>
                @foreach ($order->payments as $payment)
                    <div class="flex justify-between gap-2">
                        <span>{{ $payment->method?->name ?? $payment->driver }}</span>
                        <span>{{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</span>
                    </div>
                @endforeach
            </div>
            <div class="text-center">
                <p>{{ __('Atendido por') }}: {{ $order->cashier?->name }}</p>
                <p class="mt-2 font-semibold">{{ __('Gracias por su compra') }}</p>
            </div>
        </div>
    @endfor
    <div class="pos-receipt-actions flex flex-wrap justify-center gap-2 py-3 print:hidden">
        @if (filled($order->posTerminal?->printer_name))
            <button
                type="button"
                id="pos-qz-print-receipt"
                data-printer="{{ $order->posTerminal->printer_name }}"
                data-receipt="{{ base64_encode($receiptText) }}"
                data-copies="{{ $receiptCopies }}"
                class="rounded-lg bg-gray-900 px-4 py-2 text-white"
            >{{ __('Imprimir en térmica') }} ({{ $receiptWidth }} mm · {{ $receiptCopies }} {{ __('copias') }})</button>
        @endif
        <button type="button" onclick="window.print()" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-900">{{ __('Imprimir con navegador') }}</button>
    </div>
</div>
@if (filled($order->posTerminal?->printer_name))
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js"></script>
    <script>
        document.getElementById('pos-qz-print-receipt')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const original = button.textContent;
            try {
                button.disabled = true;
                button.textContent = 'Conectando con QZ Tray…';
                if (!window.qz) throw new Error('No se pudo cargar QZ Tray.');
                if (!qz.websocket.isActive()) await qz.websocket.connect({ retries: 2, delay: 1 });
                const printer = await qz.printers.find(button.dataset.printer);
                const config = qz.configs.create(printer, { encoding: 'CP1252', copies: Number(button.dataset.copies || 1) });
                const receipt = decodeURIComponent(escape(atob(button.dataset.receipt)));
                await qz.print(config, [
                    '\x1B\x40',
                    '\x1B\x61\x01',
                    receipt,
                    '\n\n\n',
                    '\x1D\x56\x00'
                ]);
                button.textContent = 'Recibo enviado a la impresora';
            } catch (error) {
                button.textContent = 'Error: ' + (error?.message || error);
            } finally {
                button.disabled = false;
                window.setTimeout(() => { button.textContent = original; }, 4000);
            }
        });
    </script>
@endif
