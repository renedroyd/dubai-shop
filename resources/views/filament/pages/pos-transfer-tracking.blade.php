<x-filament-panels::page>
    @php
        $statusLabels = [
            'pending_approval' => __('Pendiente de aprobación'),
            'approved' => __('Aprobada'),
            'rejected' => __('Rechazada'),
            'in_transit' => __('En tránsito'),
            'partially_received' => __('Recepción parcial'),
            'received' => __('Recibida'),
            'cancelled' => __('Cancelada'),
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('Transferencias activas') }}</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $activeCount }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('En tránsito o recepción parcial') }}</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $inTransitCount }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('Incidencias registradas') }}</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $incidentCount }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('Transferencias consultables') }}</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $statusCounts->sum() }}</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('Estado de transferencias')">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($statusLabels as $status => $label)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ (int) ($statusCounts[$status] ?? 0) }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Actividad reciente')" :description="__('Últimas 30 transferencias entre almacenes a los que tienes acceso de consulta.')">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left dark:border-gray-700">
                        <th class="px-3 py-2 font-medium">{{ __('Código') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Ruta') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Tipo') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Estado') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Solicitante') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Solicitada') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Último evento') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTransfers as $transfer)
                        @php($latestEvent = $transfer->events->first())
                        <tr class="border-b border-gray-100 align-top dark:border-gray-800">
                            <td class="px-3 py-3 font-semibold">{{ $transfer->code }}</td>
                            <td class="px-3 py-3">{{ $transfer->sourceWarehouse?->name }} → {{ $transfer->destinationWarehouse?->name }}</td>
                            <td class="px-3 py-3">{{ $transfer->transfer_type === 'replenishment' ? __('Reposición') : __($transfer->transfer_type) }}</td>
                            <td class="px-3 py-3">{{ $statusLabels[$transfer->status] ?? $transfer->status }}</td>
                            <td class="px-3 py-3">{{ $transfer->requester?->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $transfer->requested_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @if ($latestEvent)
                                    <span class="font-medium">{{ str($latestEvent->event)->replace('_', ' ')->ucfirst() }}</span>
                                    <span class="block text-xs text-gray-500">{{ $latestEvent->created_at?->diffForHumans() }} · {{ $latestEvent->user?->name ?? __('Sistema') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500">{{ __('No hay transferencias en el ámbito de almacenes autorizado.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-filament::section :heading="__('Incidencias recientes')" :description="__('Recepciones con daños y transferencias rechazadas.')">
            <div class="space-y-3">
                @forelse ($incidentEvents as $event)
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold">{{ $event->transfer?->code }}</span>
                            <span class="text-xs text-gray-500">{{ $event->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm">{{ $event->event === 'damage_reported' ? __('Daños reportados') : __('Transferencia rechazada') }}</p>
                        <p class="text-xs text-gray-500">{{ $event->transfer?->sourceWarehouse?->name }} → {{ $event->transfer?->destinationWarehouse?->name }} · {{ $event->user?->name ?? __('Sistema') }}</p>
                        @if (filled($event->details['reason'] ?? null))
                            <p class="mt-2 text-sm">{{ $event->details['reason'] }}</p>
                        @elseif (filled($event->details['damaged_quantity'] ?? null))
                            <p class="mt-2 text-sm">{{ __('Unidades dañadas: :quantity', ['quantity' => $event->details['damaged_quantity']]) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500">{{ __('No hay incidencias registradas.') }}</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('Actividad por almacén')">
            <div class="space-y-3">
                @foreach ($warehouseMetrics as $warehouse)
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <div>
                            <p class="font-medium">{{ $warehouse->name }}</p>
                            <p class="text-xs text-gray-500">{{ $warehouse->code }}</p>
                        </div>
                        <div class="text-right text-sm">
                            <p>{{ __('Salientes') }}: <strong>{{ $warehouse->outgoing_transfer_count }}</strong></p>
                            <p>{{ __('Entrantes') }}: <strong>{{ $warehouse->incoming_transfer_count }}</strong></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
