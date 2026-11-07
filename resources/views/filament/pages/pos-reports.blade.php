<x-filament-panels::page>
    <form method="GET" action="{{ url()->current() }}" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-4">
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Almacén') }}
            <select name="warehouse_id" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">{{ __('Todos los almacenes autorizados') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Desde') }}
            <input type="date" name="from" value="{{ $fromDate }}" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Hasta') }}
            <input type="date" name="to" value="{{ $toDate }}" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <div class="flex items-end">
            <button type="submit" class="fi-btn fi-btn-color-primary w-full">{{ __('Aplicar filtros') }}</button>
        </div>
    </form>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Ventas POS por almacén y moneda') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Ventas') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $sale->posWarehouse?->name ?? '—' }}</td><td>{{ $sale->currency }}</td><td>{{ $sale->orders_count }}</td><td class="text-right tabular-nums">{{ number_format($sale->total_amount / 100, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('No hay ventas POS en el período seleccionado.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Devoluciones POS por almacén y moneda') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Moneda') }}</th><th>{{ __('Devoluciones') }}</th><th class="text-right">{{ __('Total reembolsado') }}</th></tr></thead>
                    <tbody>
                        @forelse ($refunds as $refund)
                            <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $refund->warehouse_name }}</td><td>{{ $refund->currency }}</td><td>{{ $refund->refunds_count }}</td><td class="text-right tabular-nums">{{ number_format($refund->total_amount / 100, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('No hay devoluciones en el período seleccionado.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Sesiones de caja y arqueos') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Terminal') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Diferencia') }}</th></tr></thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $session->terminal?->warehouse?->name ?? '—' }}</td><td>{{ $session->terminal?->code ?? '—' }}</td><td>{{ __($session->status) }}</td><td class="text-right tabular-nums">{{ $session->difference_amount === null ? '—' : number_format($session->difference_amount / 100, 2).' '.$session->currency }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('No hay sesiones de caja en el período seleccionado.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Movimientos de inventario') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="py-2">{{ __('Almacén') }}</th><th>{{ __('Producto') }}</th><th>{{ __('Tipo') }}</th><th class="text-right">{{ __('Variación') }}</th></tr></thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $movement->inventory?->warehouse?->name ?? '—' }}</td><td>{{ $movement->inventory?->variant?->name ?? $movement->inventory?->product?->name ?? '—' }}</td><td>{{ __($movement->type) }}</td><td class="text-right tabular-nums">{{ $movement->quantity_delta }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">{{ __('No hay movimientos en el período seleccionado.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">{{ __('Transferencias por estado') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left"><th class="py-2">{{ __('Estado') }}</th><th class="text-right">{{ __('Cantidad') }}</th></tr></thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ __($transfer->status) }}</td><td class="text-right tabular-nums">{{ $transfer->transfers_count }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">{{ __('No hay transferencias en el período seleccionado.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
