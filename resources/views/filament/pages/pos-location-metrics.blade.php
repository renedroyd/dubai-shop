<x-filament-panels::page>
    <form method="GET" action="{{ url()->current() }}" class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-3">
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Desde') }}
            <input type="date" name="from" value="{{ $from }}" class="fi-input rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <label class="grid gap-1 text-sm font-medium">
            {{ __('Hasta') }}
            <input type="date" name="to" value="{{ $to }}" class="fi-input rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
        </label>
        <div class="flex items-end">
            <button type="submit" class="fi-btn fi-btn-color-primary w-full justify-center">{{ __('Aplicar período') }}</button>
        </div>
    </form>

    <div class="space-y-5">
        @forelse ($metrics as $metric)
            <x-filament::section :heading="$metric['location']->name" :description="$metric['location']->code">
                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-sm text-gray-500">{{ __('Terminales activos') }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $metric['location']->active_terminals_count }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-sm text-gray-500">{{ __('Cajas abiertas') }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ (int) ($metric['currentSessions']->open_sessions_count ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-sm text-gray-500">{{ __('Cierres pendientes de aprobación') }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ (int) ($metric['currentSessions']->pending_close_count ?? 0) }}</p>
                    </div>
                </div>

                <h3 class="mb-2 text-sm font-semibold">{{ __('Ventas por moneda') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b text-left"><th class="py-2">{{ __('Moneda') }}</th><th>{{ __('Pedidos') }}</th><th class="text-right">{{ __('Ventas brutas') }}</th><th class="text-right">{{ __('Reembolsos') }}</th><th class="text-right">{{ __('Ventas netas') }}</th></tr></thead>
                        <tbody>
                            @forelse ($metric['currencyRows'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 font-medium">{{ $row['currency'] }}</td>
                                    <td>{{ $row['orders_count'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['sales_amount'] / 100, 2) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['refunded_amount'] / 100, 2) }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ number_format(($row['sales_amount'] - $row['refunded_amount']) / 100, 2) }} {{ $row['currency'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-gray-500">{{ __('Sin ventas en el período seleccionado.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __('Sesiones de caja en el período: :count', ['count' => (int) ($metric['sessions']->sessions_count ?? 0)]) }}</p>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="py-6 text-center text-sm text-gray-500">{{ __('No hay establecimientos en los almacenes que tienes autorizados para consultar.') }}</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
