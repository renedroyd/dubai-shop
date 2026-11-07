<x-filament-panels::page>
    @php($session = $this->currentSession())
    @php($sessions = $this->openSessions())
    @php($products = $this->products())
    @php($categories = $this->categories())
    @php($cartItems = $this->cartItems())
    @php($methods = $this->cashPaymentMethods())

    <div class="space-y-5">
        @if ($lastOrderNumber)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm">
                <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    <span>{{ __('Última venta registrada: :number', ['number' => $lastOrderNumber]) }}</span>
                </div>
                <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index') }}" class="text-sm font-semibold underline">{{ __('Ver pedidos y recibos') }}</a>
            </div>
        @endif

        @if (! $session)
            <section class="mx-auto max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-5 flex items-center gap-3">
                    <div class="rounded-xl bg-amber-500/10 p-3 text-amber-500"><x-heroicon-o-calculator class="h-7 w-7" /></div>
                    <div>
                        <h2 class="text-xl font-semibold">{{ __('Preparar caja para vender') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Selecciona el terminal, la moneda y el fondo inicial.') }}</p>
                    </div>
                </div>
                @if ($this->availableTerminals()->isEmpty())
                    <div class="rounded-xl bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
                        {{ __('No tienes terminales activos autorizados. Solicita al administrador acceso POS al almacén y verifica la configuración del terminal.') }}
                    </div>
                @else
                    <form wire:submit="openCashSession($terminalId)" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium">{{ __('Terminal') }}</label>
                            <select id="pos-terminal" wire:model="terminalId" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" required>
                                <option value="">{{ __('Selecciona un terminal') }}</option>
                                @foreach ($this->availableTerminals() as $terminal)
                                    <option value="{{ $terminal->id }}">{{ $terminal->name }} · {{ $terminal->location->name }} · {{ $terminal->warehouse->name }}</option>
                                @endforeach
                            </select>
                            @error('terminalId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium">{{ __('Moneda') }}
                                <select wire:model="openingCurrency" class="fi-input mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                                    <option value="CUP">CUP</option><option value="USD">USD</option><option value="EUR">EUR</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium">{{ __('Fondo inicial') }}
                                <input type="number" min="0" step="0.01" wire:model="openingAmount" class="fi-input mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                                @error('openingAmount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                            </label>
                        </div>
                        <button type="submit" class="fi-btn fi-btn-color-primary w-full justify-center" wire:loading.attr="disabled">
                            <x-heroicon-o-lock-open class="h-4 w-4" /> {{ __('Abrir caja y comenzar') }}
                        </button>
                    </form>
                @endif
            </section>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/30 bg-gradient-to-r from-gray-950 to-gray-900 px-4 py-4 text-white shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-amber-400/15 p-3 text-amber-300"><x-heroicon-o-shopping-bag class="h-6 w-6" /></div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">{{ __('Clásico Dubai · Punto de venta') }}</p>
                        <h2 class="text-lg font-semibold">{{ $session->terminal->name }}</h2>
                        <p class="text-xs text-gray-300">{{ $session->terminal->location->name }} · {{ $session->terminal->warehouse->name }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($sessions->count() > 1)
                        <select wire:model.live="cashSessionId" class="rounded-lg border border-white/20 bg-gray-900 px-3 py-2 text-sm text-white">
                            @foreach ($sessions as $openSession)
                                <option value="{{ $openSession->id }}">{{ $openSession->terminal->name }} · {{ $openSession->currency }}</option>
                            @endforeach
                        </select>
                    @endif
                    <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-right">
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ __('Efectivo esperado') }}</p>
                        <p class="font-semibold tabular-nums">{{ $this->formatMoney($this->cashBalance($session), $session->currency) }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-semibold text-emerald-300">{{ __('Caja abierta') }}</span>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
                <section class="min-w-0 space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <label class="relative min-w-0 flex-1">
                            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar por nombre o SKU…') }}" class="fi-input w-full rounded-xl border-gray-300 py-3 pl-10 dark:border-gray-700 dark:bg-gray-900">
                        </label>
                        <select wire:model.live="categoryId" class="fi-input rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 sm:w-56">
                            <option value="">{{ __('Todas las categorías') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold">{{ __('Catálogo') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('Precios en :currency · Existencias del almacén de esta caja', ['currency' => $session->currency]) }}</p>
                        </div>
                        <span class="text-xs text-gray-500">{{ $products->count() }} / 60</span>
                    </div>

                    @if ($products->isEmpty())
                        <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700">
                            <x-heroicon-o-cube class="mx-auto h-10 w-10 text-gray-400" />
                            <p class="mt-3 font-medium">{{ __('No hay productos para mostrar') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Prueba otra búsqueda o revisa los productos activos en esta moneda.') }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-4">
                            @foreach ($products as $product)
                                @php($selectedVariant = $product->variants->firstWhere('id', $this->variantSelections[$product->id] ?? null))
                                @php($stock = $this->availableStock($product, $selectedVariant))
                                @php($price = (int) ($selectedVariant?->price_amount ?? $product->price_amount))
                                <article wire:key="pos-product-{{ $product->id }}" class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-amber-400/60 hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                                    <div class="relative aspect-[5/4] overflow-hidden bg-gray-100 dark:bg-gray-800">
                                        @if ($this->productImage($product))
                                            <img src="{{ $this->productImage($product) }}" alt="{{ $product->images->first()?->alt_text ?: $product->name }}" loading="lazy" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full items-center justify-center text-gray-400"><x-heroicon-o-cube class="h-12 w-12" /></div>
                                        @endif
                                        @if ($stock !== null)
                                            <span class="absolute left-2 top-2 rounded-full bg-gray-950/80 px-2 py-1 text-[11px] font-semibold text-white">{{ __('Disponible: :quantity', ['quantity' => $stock]) }}</span>
                                        @else
                                            <span class="absolute left-2 top-2 rounded-full bg-gray-950/75 px-2 py-1 text-[11px] text-white">{{ __('Sin control de stock') }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-1 flex-col p-3">
                                        <p class="mb-1 truncate text-[11px] text-gray-500">{{ $product->category?->name ?: __('Sin categoría') }} · {{ $product->sku }}</p>
                                        <h4 class="line-clamp-2 min-h-10 text-sm font-semibold">{{ $product->name }}</h4>
                                        <p class="mt-2 text-base font-bold text-amber-600 dark:text-amber-400">{{ $this->formatMoney($price, $product->currency) }}</p>
                                        @if ($product->variants->isNotEmpty())
                                            <select wire:model.live="variantSelections.{{ $product->id }}" class="fi-input mt-2 w-full rounded-lg border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-950">
                                                <option value="">{{ __('Seleccionar variante') }}</option>
                                                @foreach ($product->variants as $variant)
                                                    <option value="{{ $variant->id }}">{{ $variant->name }} · {{ $this->formatMoney((int) ($variant->price_amount ?? $product->price_amount), $product->currency) }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="button" wire:click="addProduct({{ $product->id }}, {{ $selectedVariant?->id ?? 'null' }})" @disabled(($stock !== null && $stock < 1) || ($product->variants->isNotEmpty() && ! $selectedVariant)) wire:loading.attr="disabled" class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-gray-950 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-amber-500 dark:text-gray-950 dark:hover:bg-amber-400">
                                            <x-heroicon-o-plus class="h-4 w-4" /> {{ __('Agregar') }}
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <aside class="h-fit space-y-4 xl:sticky xl:top-4">
                    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                            <div>
                                <h3 class="font-semibold">{{ __('Venta actual') }}</h3>
                                <p class="text-xs text-gray-500">{{ count($cartItems) }} {{ __('artículos') }}</p>
                            </div>
                            <button type="button" wire:click="clearCart" class="text-xs font-medium text-danger-600 hover:underline" @disabled(empty($cartItems))>{{ __('Vaciar') }}</button>
                        </div>
                        <div class="max-h-[360px] space-y-3 overflow-y-auto p-4">
                            @forelse ($cartItems as $item)
                                <div wire:key="pos-cart-{{ $item['key'] }}" class="flex gap-3">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                        @if ($item['product']->images->first())
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['product']->images->first()->path) }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full items-center justify-center text-gray-400"><x-heroicon-o-cube class="h-6 w-6" /></div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-sm font-medium">{{ $item['product']->name }}</p>
                                        @if ($item['variant']) <p class="text-xs text-gray-500">{{ $item['variant']->name }}</p> @endif
                                        <p class="mt-1 text-xs text-gray-500">{{ $this->formatMoney($item['price'], $session->currency) }}</p>
                                        <div class="mt-2 flex items-center gap-2">
                                            <button type="button" wire:click="updateQuantity('{{ $item['key'] }}', {{ $item['quantity'] - 1 }})" class="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700" @disabled($item['quantity'] <= 1)>−</button>
                                            <span class="min-w-5 text-center text-sm font-semibold tabular-nums">{{ $item['quantity'] }}</span>
                                            <button type="button" wire:click="updateQuantity('{{ $item['key'] }}', {{ $item['quantity'] + 1 }})" class="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700">+</button>
                                            <button type="button" wire:click="removeProduct('{{ $item['key'] }}')" class="ml-auto text-gray-400 hover:text-danger-600" aria-label="{{ __('Eliminar producto') }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                        </div>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums">{{ $this->formatMoney($item['total'], $session->currency) }}</p>
                                </div>
                            @empty
                                <div class="py-8 text-center">
                                    <x-heroicon-o-shopping-cart class="mx-auto h-9 w-9 text-gray-300" />
                                    <p class="mt-2 text-sm text-gray-500">{{ __('El carrito está vacío') }}</p>
                                    <p class="text-xs text-gray-400">{{ __('Agrega productos del catálogo para comenzar.') }}</p>
                                </div>
                            @endforelse
                        </div>
                        <div class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex items-center justify-between text-sm text-gray-500"><span>{{ __('Subtotal') }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ $this->formatMoney($this->cartSubtotal(), $session->currency) }}</span></div>
                            <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-3 text-base font-bold dark:border-gray-700"><span>{{ __('Total a cobrar') }}</span><span class="text-xl text-amber-600 dark:text-amber-400">{{ $this->formatMoney($this->cartSubtotal(), $session->currency) }}</span></div>
                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-gray-500">{{ __('Cliente (opcional)') }}</label>
                                <input wire:model="customerName" placeholder="{{ __('Nombre del cliente') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                                <input wire:model="customerPhone" placeholder="{{ __('Teléfono') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                                <input wire:model="customerEmail" type="email" placeholder="{{ __('Correo electrónico') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                            </div>
                            <label class="block text-xs font-medium text-gray-500">{{ __('Método de cobro') }}
                                <select wire:model="paymentMethodId" class="fi-input mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" wire:click="completeSale" wire:confirm="{{ __('¿Confirmas el cobro y registro de esta venta?') }}" wire:loading.attr="disabled" @disabled(empty($cartItems) || $methods->isEmpty()) class="fi-btn fi-btn-color-primary w-full justify-center py-3 text-base font-bold disabled:opacity-50">
                                <x-heroicon-o-banknotes class="h-5 w-5" /> {{ __('Cobrar :total', ['total' => $this->formatMoney($this->cartSubtotal(), $session->currency)]) }}
                            </button>
                            @error('cart') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-3 text-sm font-semibold">{{ __('Operaciones de caja') }}</h3>
                        <div class="space-y-3">
                            <form wire:submit="recordCashMovement" class="space-y-2">
                                <select wire:model="cashMovementType" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                                    <option value="cash_in">{{ __('Entrada de efectivo') }}</option>
                                    <option value="cash_out">{{ __('Salida de efectivo') }}</option>
                                </select>
                                <input type="number" min="0.01" step="0.01" wire:model="cashMovementAmount" placeholder="{{ __('Importe') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                                <input wire:model="cashMovementNote" placeholder="{{ __('Motivo del movimiento') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                                <button type="submit" class="fi-btn fi-btn-color-gray w-full justify-center">{{ __('Registrar movimiento') }}</button>
                            </form>
                            <div class="border-t border-gray-200 pt-3 dark:border-gray-800">
                                <form wire:submit="closeCashSession" class="space-y-2">
                                    <label class="block text-xs font-medium text-gray-500">{{ __('Efectivo contado al cierre') }}</label>
                                    <input type="number" min="0" step="0.01" wire:model="countedCashAmount" placeholder="0.00" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                                    <input wire:model="closingNote" placeholder="{{ __('Observaciones del cierre') }}" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                                    <button type="submit" wire:confirm="{{ __('¿Cerrar la caja y registrar el arqueo?') }}" class="fi-btn fi-btn-color-danger w-full justify-center">{{ __('Cerrar caja y arquear') }}</button>
                                </form>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        @endif
    </div>
</x-filament-panels::page>
