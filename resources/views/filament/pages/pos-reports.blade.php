<x-filament-panels::page>
    <form method="GET" action="{{ url()->current() }}" class="mb-6 grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-2 xl:grid-cols-4">
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Almacén') }}
            <select name="warehouse_id" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('Todos los almacenes autorizados') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Terminal') }}
            <select name="terminal_id" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('Todos los terminales') }}</option>
                @foreach ($terminals as $terminal)
                    <option value="{{ $terminal->id }}" @selected($filters['terminal_id'] === $terminal->id)>{{ $terminal->name }} · {{ $terminal->code }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Cajero') }}
            <select name="cashier_id" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('Todos los cajeros') }}</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected($filters['cashier_id'] === $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Moneda') }}
            <select name="currency" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('Todas las monedas') }}</option>
                @foreach (['CUP', 'USD', 'EUR'] as $code)
                    <option value="{{ $code }}" @selected($filters['currency'] === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Desde') }}
            <input type="date" name="from" value="{{ $filters['from'] }}" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Hasta') }}
            <input type="date" name="to" value="{{ $filters['to'] }}" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <div class="flex items-end gap-2 md:col-span-2">
            <button type="submit" class="fi-btn fi-btn-color-primary flex-1 justify-center">{{ __('Aplicar filtros') }}</button>
            <button
                type="button"
                wire:click="exportCsv(@js($filters['from']), @js($filters['to']), @js($filters['warehouse_id']), @js($filters['terminal_id']), @js($filters['cashier_id']), @js($filters['currency']))"
                wire:loading.attr="disabled"
                class="fi-btn fi-btn-color-gray justify-center"
            >{{ __('Exportar CSV') }}</button>
        </div>
        <p class="text-xs text-gray-500 md:col-span-2 xl:col-span-4">{{ __('Los importes se presentan separados por moneda, sin conversión. El rango máximo permitido es de 366 días.') }}</p>
    </form>

    <section class="mb-6 space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Resumen de ventas') }}</h2>
                <p class="text-xs text-gray-500">{{ $filters['from'] }} — {{ $filters['to'] }}</p>
            </div>
            <p class="text-xs text-gray-500">{{ __('Comparación con :from — :to', ['from' => $previousFrom->toDateString(), 'to' => $previousTo->toDateString()]) }}</p>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($sales as $sale)
                @php($previous = $previousSales->firstWhere('currency', $sale->currency))
                @php($previousAmount = (int) ($previous?->total_amount ?? 0))
                @php($refundAmount = (int) ($refunds->firstWhere('currency', $sale->currency)?->amount ?? 0))
                @php($netAfterRefunds = (int) $sale->total_amount - $refundAmount)
                @php($difference = (int) $sale->total_amount - $previousAmount)
                <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Ventas después de descuentos') }} · {{ $sale->currency }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format($sale->total_amount / 100, 2) }} {{ $sale->currency }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><p class="text-xs text-gray-500">{{ __('Pedidos pagados') }}</p><p class="font-semibold">{{ $sale->orders_count }}</p></div>
                        <div><p class="text-xs text-gray-500">{{ __('Descuentos') }}</p><p class="font-semibold tabular-nums">{{ number_format($sale->discounts_amount / 100, 2) }}</p></div>
                        <div><p class="text-xs text-gray-500">{{ __('Reembolsos') }}</p><p class="font-semibold tabular-nums">{{ number_format($refundAmount / 100, 2) }}</p></div>
                        <div><p class="text-xs text-gray-500">{{ __('Neto tras reembolsos') }}</p><p class="font-semibold tabular-nums">{{ number_format($netAfterRefunds / 100, 2) }} {{ $sale->currency }}</p></div>
                    </div>
                    <div class="mt-3 border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                        <span class="text-gray-500">{{ __('Período anterior') }}:</span>
                        <span class="font-semibold tabular-nums">{{ number_format($previousAmount / 100, 2) }} {{ $sale->currency }}</span>
                        <span class="{{ $difference >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-semibold">({{ $difference >= 0 ? '+' : '−' }}{{ number_format(abs($difference) / 100, 2) }})</span>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700">{{ __('No hay ventas POS pagadas en el período seleccionado.') }}</div>
            @endforelse
        </div>
    </section>

    <section class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h2 class="mb-3 text-base font-semibold">{{ __('Margen bruto estimado') }}</h2>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($grossMargin as $margin)
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                    <p class="text-xs font-semibold text-gray-500">{{ $margin->currency }}</p>
                    @if ($margin->margin_amount === null)
                        <p class="mt-1 font-semibold text-amber-700 dark:text-amber-300">{{ __('No disponible: faltan costos históricos') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Líneas sin costo: :count', ['count' => $margin->missing_cost_lines]) }}</p>
                    @else
                        <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($margin->margin_amount / 100, 2) }} {{ $margin->currency }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Ingresos de líneas') }} {{ number_format($margin->item_revenue / 100, 2) }} · {{ __('Costo') }} {{ number_format($margin->cost_amount / 100, 2) }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('No hay datos de margen en el período.') }}</p>
            @endforelse
        </div>
        <p class="mt-3 text-xs text-gray-500">{{ __('El cálculo usa el costo histórico guardado en cada línea de venta y descuenta el descuento global. Si alguna línea no tiene costo histórico, no se presenta un margen total incompleto.') }}</p>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por almacén') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Pedidos') }}</th><th class="text-right">{{ __('Ventas') }}</th></tr></thead>
                <tbody>@forelse ($salesByWarehouse as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->posWarehouse?->name ?? '—' }}</td><td>{{ $row->currency }}</td><td>{{ $row->orders_count }}</td><td class="text-right tabular-nums">{{ number_format($row->total_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por terminal') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Terminal') }}</th><th>{{ __('Almacén') }}</th><th>{{ __('Moneda') }}</th><th class="text-right">{{ __('Ventas') }}</th></tr></thead>
                <tbody>@forelse ($salesByTerminal as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->posTerminal?->name ?? '—' }}</td><td>{{ $row->posWarehouse?->name ?? '—' }}</td><td>{{ $row->currency }}</td><td class="text-right tabular-nums">{{ number_format($row->total_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por cajero') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Cajero') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Pedidos') }}</th><th class="text-right">{{ __('Ventas') }}</th></tr></thead>
                <tbody>@forelse ($salesByCashier as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->cashier?->name ?? '—' }}</td><td>{{ $row->currency }}</td><td>{{ $row->orders_count }}</td><td class="text-right tabular-nums">{{ number_format($row->total_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por método de pago') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Método') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Operaciones') }}</th><th class="text-right">{{ __('Neto cobrado') }}</th></tr></thead>
                <tbody>@forelse ($payments as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->method_name }}</td><td>{{ $row->currency }}</td><td>{{ $row->payments_count }}</td><td class="text-right tabular-nums">{{ number_format($row->amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin pagos confirmados') }}</td></tr>@endforelse</tbody>
            </table></div>
            <p class="mt-2 text-xs text-gray-500">{{ __('Los reembolsos ya están descontados de cada medio de pago original.') }}</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por producto y variante') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Producto') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Unidades') }}</th><th class="text-right">{{ __('Importe') }}</th></tr></thead>
                <tbody>@forelse ($salesByProduct as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->product_name }} @if($row->variant_name)<span class="text-xs text-gray-500">· {{ $row->variant_name }}</span>@endif</td><td>{{ $row->sku }}</td><td>{{ $row->quantity_sold }}</td><td class="text-right tabular-nums">{{ number_format($row->sales_amount / 100, 2) }} {{ $row->currency }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
            <p class="mt-2 text-xs text-gray-500">{{ __('Importes por artículo después del descuento de línea y antes de distribuir descuentos globales del pedido. Se muestran separados por moneda.') }}</p>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por categoría') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Categoría') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Unidades') }}</th><th class="text-right">{{ __('Importe') }}</th></tr></thead>
                <tbody>@forelse ($salesByCategory as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->category_name }}</td><td>{{ $row->currency }}</td><td>{{ $row->quantity_sold }}</td><td class="text-right tabular-nums">{{ number_format($row->sales_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas por marca') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Marca') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Unidades') }}</th><th class="text-right">{{ __('Importe') }}</th></tr></thead>
                <tbody>@forelse ($salesByBrand as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->brand_name }}</td><td>{{ $row->currency }}</td><td>{{ $row->quantity_sold }}</td><td class="text-right tabular-nums">{{ number_format($row->sales_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin datos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Reembolsos por almacén') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Cantidad') }}</th><th class="text-right">{{ __('Reembolsado') }}</th></tr></thead>
                <tbody>@forelse ($refundsByWarehouse as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->warehouse_name }}</td><td>{{ $row->currency }}</td><td>{{ $row->refunds_count }}</td><td class="text-right tabular-nums">{{ number_format($row->amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin reembolsos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Diferencias de caja') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Moneda') }}</th><th>{{ __('Cierres') }}</th><th class="text-right">{{ __('Diferencia neta') }}</th><th class="text-right">{{ __('Diferencia absoluta') }}</th></tr></thead>
                <tbody>@forelse ($cashDiscrepancies as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->currency }}</td><td>{{ $row->sessions_count }}</td><td class="text-right tabular-nums">{{ number_format($row->difference_amount / 100, 2) }}</td><td class="text-right tabular-nums">{{ number_format($row->absolute_difference_amount / 100, 2) }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('No hay arqueos cerrados en el período') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Sesiones de caja y arqueos') }}</h2>
            <div class="max-h-80 overflow-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Terminal') }}</th><th>{{ __('Turno') }}</th><th>{{ __('Apertura') }}</th><th>{{ __('Cajero') }}</th><th>{{ __('Aprobó cierre') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Diferencia') }}</th></tr></thead>
                <tbody>@forelse ($sessions as $session)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $session->terminal?->name ?? '—' }}</td><td>{{ $session->shift_label ?: '—' }}</td><td>{{ $session->opened_at?->format('d/m/Y H:i') }}</td><td>{{ $session->opener?->name ?? '—' }}</td><td>{{ $session->closeApprover?->name ?? '—' }}</td><td>{{ __($session->status) }} @if($session->close_requested_at && !$session->closed_at)<span class="text-xs text-amber-600">· {{ __('Pendiente de aprobación') }}</span>@endif</td><td class="text-right tabular-nums">{{ $session->difference_amount === null ? '—' : number_format($session->difference_amount / 100, 2).' '.$session->currency }}</td></tr>
                @empty <tr><td colspan="7" class="py-4 text-center text-gray-500">{{ __('Sin sesiones') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Movimientos de inventario') }}</h2>
            <div class="max-h-80 overflow-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Producto') }}</th><th>{{ __('Tipo') }}</th><th class="text-right">{{ __('Variación') }}</th></tr></thead>
                <tbody>@forelse ($inventoryMovements as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $row->inventory?->warehouse?->name ?? '—' }}</td><td>{{ $row->inventory?->variant?->name ?? $row->inventory?->product?->name ?? '—' }}</td><td>{{ __($row->type) }}</td><td class="text-right tabular-nums">{{ $row->quantity_delta }}</td></tr>
                @empty <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('Sin movimientos') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Transferencias por estado') }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="py-2">{{ __('Estado') }}</th><th class="text-right">{{ __('Cantidad') }}</th></tr></thead>
                <tbody>@forelse ($transfers as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ __($row->status) }}</td><td class="text-right">{{ $row->transfers_count }}</td></tr>
                @empty <tr><td colspan="2" class="py-4 text-center text-gray-500">{{ __('Sin transferencias') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
</x-filament-panels::page>
