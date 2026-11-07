<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Registrar ajuste manual') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('Cada ajuste exige un motivo y registra al usuario que lo autorizó.') }}</p>

            <div class="mt-4 space-y-3">
                <label class="block text-sm font-medium">{{ __('Buscar cliente') }}
                    <input wire:model.live.debounce.300ms="customerSearch" class="fi-input mt-1 w-full" placeholder="{{ __('Nombre o correo electrónico') }}">
                </label>
                @if (! $customerUserId && $this->customerMatches()->isNotEmpty())
                    <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        @foreach ($this->customerMatches() as $customer)
                            <button type="button" wire:click="selectCustomer({{ $customer->id }})" class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800">
                                <span class="block font-medium">{{ $customer->name }}</span>
                                <span class="text-xs text-gray-500">{{ $customer->email }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                @if ($customerUserId)
                    <div class="flex items-center justify-between rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-500/10">
                        <span class="font-semibold">{{ $customerName }}</span>
                        <button type="button" wire:click="clearCustomer" class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ __('Cambiar') }}</button>
                    </div>
                @endif
                <label class="block text-sm font-medium">{{ __('Moneda') }}
                    <select wire:model="adjustmentCurrency" class="fi-input mt-1 w-full">
                        <option value="CUP">CUP</option><option value="USD">USD</option><option value="EUR">EUR</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">{{ __('Puntos a ajustar (+ o −)') }}
                    <input type="number" step="1" wire:model="adjustmentPoints" class="fi-input mt-1 w-full" placeholder="Ej. 50 o -20">
                </label>
                <label class="block text-sm font-medium">{{ __('Motivo obligatorio') }}
                    <textarea wire:model="adjustmentReason" rows="3" maxlength="500" class="fi-input mt-1 w-full" placeholder="{{ __('Explica el motivo del ajuste') }}"></textarea>
                </label>
                @foreach (['customerUserId','adjustmentCurrency','adjustmentPoints','adjustmentReason'] as $field)
                    @error($field) <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                @endforeach
                <button type="button" wire:click="submitAdjustment" wire:loading.attr="disabled" @disabled(! $customerUserId) class="fi-btn fi-btn-color-primary w-full justify-center">
                    {{ __('Guardar ajuste auditado') }}
                </button>
            </div>
        </section>

        <section class="space-y-5">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">{{ __('Saldos del cliente') }}</h2>
                @if ($customerUserId)
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach ($this->customerWallets() as $wallet)
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <p class="text-xs text-gray-500">{{ $wallet->currency }}</p>
                                <p class="text-2xl font-bold tabular-nums">{{ number_format(max(0, $wallet->available_points)) }}</p>
                                @if ($wallet->available_points < 0)
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Saldo por compensar: :points puntos', ['points' => number_format(abs($wallet->available_points))]) }}</p>
                                @endif
                                @if ($wallet->expiring_points > 0)
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ number_format($wallet->expiring_points) }} {{ __('vencen en 30 días') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm text-gray-500">{{ __('Selecciona una cuenta de cliente para consultar sus saldos y movimientos.') }}</p>
                @endif
            </div>

            @if ($customerUserId)
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                        <h2 class="font-semibold">{{ __('Últimos movimientos') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr><th class="px-3 py-2">{{ __('Fecha') }}</th><th class="px-3 py-2">{{ __('Moneda') }}</th><th class="px-3 py-2">{{ __('Tipo') }}</th><th class="px-3 py-2 text-right">{{ __('Puntos') }}</th><th class="px-3 py-2">{{ __('Motivo / referencia') }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($this->recentEntries() as $entry)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="whitespace-nowrap px-3 py-2">{{ $entry->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="px-3 py-2">{{ $entry->wallet->currency }}</td>
                                        <td class="px-3 py-2">{{ str_replace('_', ' ', $entry->type) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $entry->points_delta > 0 ? '+' : '' }}{{ number_format($entry->points_delta) }}</td>
                                        <td class="max-w-xs px-3 py-2 text-xs text-gray-500">{{ $entry->metadata['reason'] ?? ($entry->order?->number ?? '—') }} · {{ $entry->actor?->name ?? __('Sistema') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('Sin movimientos registrados.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
