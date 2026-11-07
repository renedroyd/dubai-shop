<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosSaleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class PosSales extends Page
{
    use WithFileUploads;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.pos-sales';

    public string $search = '';
    public ?int $categoryId = null;
    public ?int $cashSessionId = null;
    public ?int $terminalId = null;
    public array $cart = [];
    public array $variantSelections = [];
    public ?int $paymentMethodId = null;
    public string $cashReceivedAmount = '';
    public bool $mixedPayment = false;
    public string $mixedCashAmount = '';
    public string $mixedZelleAmount = '';
    public string $zelleSenderName = '';
    public string $zelleReference = '';
    public string $zelleTransferDate = '';
    public $zelleProof = null;
    public string $customerSearch = '';
    public ?int $customerUserId = null;
    public string $customerName = '';
    public string $customerPhone = '';
    public string $customerEmail = '';
    public string $openingCurrency = 'CUP';
    public string $openingAmount = '0.00';
    public string $cashMovementType = PosCashMovement::TYPE_CASH_IN;
    public string $cashMovementAmount = '';
    public string $cashMovementNote = '';
    public string $countedCashAmount = '';
    public string $closingNote = '';
    public ?string $lastOrderNumber = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAdminPermission('pos_cash_sessions.view')
            && $user->warehouseIdsWithPermission('pos.operate') !== [];
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('Punto de venta');
    }

    public static function getNavigationLabel(): string
    {
        return __('Terminal de ventas');
    }

    public function getTitle(): string
    {
        return __('Terminal de ventas');
    }

    public function mount(): void
    {
        $session = $this->openSessions()->first();

        if ($session) {
            $this->cashSessionId = $session->id;
        }

        $this->paymentMethodId = $this->cashPaymentMethods()->first()?->id;
    }

    public function updatedCashSessionId(mixed $value): void
    {
        $session = $this->openSessions()->firstWhere('id', (int) $value);

        if (! $session) {
            $this->cashSessionId = $this->openSessions()->first()?->id;
            $this->cart = [];

            return;
        }

        $this->cart = [];
        $this->paymentMethodId = $this->cashPaymentMethods()->first()?->id;
    }

    public function openSessions(): Collection
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('pos.operate') ?? [];

        if ($warehouseIds === []) {
            return collect();
        }

        return PosCashSession::query()
            ->where('status', PosCashSession::STATUS_OPEN)
            ->whereNull('closed_at')
            ->whereHas('terminal', fn (Builder $query) => $query
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('is_active', true)
                ->whereHas('location', fn (Builder $location) => $location->where('is_active', true))
                ->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('is_active', true)))
            ->with(['terminal.location', 'terminal.warehouse'])
            ->orderByDesc('opened_at')
            ->get();
    }

    public function availableTerminals(): Collection
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('pos.operate') ?? [];

        return PosTerminal::query()
            ->where('is_active', true)
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('location', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('warehouse', fn (Builder $query) => $query->where('is_active', true))
            ->with(['location', 'warehouse'])
            ->orderBy('name')
            ->get();
    }

    public function cashPaymentMethods(): Collection
    {
        return PaymentMethod::query()->where('is_enabled', true)->where('driver', 'cash_on_delivery')->orderBy('sort_order')->get();
    }

    public function paymentMethods(): Collection
    {
        $currency = $this->currentSession()?->currency;
        return PaymentMethod::query()->where('is_enabled', true)
            ->whereIn('driver', ['cash_on_delivery', 'zelle_manual'])
            ->when($currency !== 'USD', fn (Builder $query) => $query->where('driver', '!=', 'zelle_manual'))
            ->orderBy('sort_order')->get();
    }

    public function selectedPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->firstWhere('id', $this->paymentMethodId);
    }

    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $query) => $query->where('status', Product::STATUS_ACTIVE))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function products(): Collection
    {
        $session = $this->currentSession();

        if (! $session) {
            return collect();
        }

        $warehouseId = $session->terminal->warehouse_id;

        return Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('currency', $session->currency)
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('sku', 'like', '%'.trim($this->search).'%')))
            ->when($this->categoryId, fn (Builder $query) => $query->where('category_id', $this->categoryId))
            ->with([
                'category:id,name',
                'images:id,product_id,path,alt_text,is_primary,sort_order',
                'inventories' => fn (Relation $query) => $query->where('warehouse_id', $warehouseId)->whereNull('product_variant_id'),
                'variants' => fn (Relation $query) => $query->where('is_active', true)->with([
                    'inventories' => fn (Relation $inventory) => $inventory->where('warehouse_id', $warehouseId),
                ]),
            ])
            ->orderBy('name')
            ->limit(60)
            ->get();
    }

    public function currentSession(): ?PosCashSession
    {
        if (! $this->cashSessionId) {
            return null;
        }

        return $this->openSessions()->firstWhere('id', $this->cashSessionId);
    }

    public function productImage(Product $product): ?string
    {
        $path = $product->images->first()?->path;

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    public function availableStock(Product $product, ?ProductVariant $variant = null): ?int
    {
        $inventory = $variant ? $variant->inventories->first() : $product->inventories->first();

        return $inventory?->available_quantity;
    }

    public function addProduct(int $productId, ?int $variantId = null): void
    {
        $session = $this->currentSession();

        if (! $session) {
            Notification::make()->warning()->title(__('Abre una caja para comenzar a vender.'))->send();

            return;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('currency', $session->currency)
            ->with(['variants' => fn (Relation $query) => $query->where('is_active', true)])
            ->firstOrFail();

        if ($product->variants->isNotEmpty() && $variantId === null) {
            throw ValidationException::withMessages(['variantSelections.'.$product->id => __('Selecciona una variante antes de agregar el producto.')]);
        }

        $variant = null;

        if ($variantId !== null) {
            $variant = $product->variants->firstWhere('id', $variantId);

            if (! $variant) {
                throw ValidationException::withMessages(['cart' => __('La variante seleccionada no está disponible.')]);
            }
        }

        $key = $product->id.':'.($variant?->id ?? 0);
        $quantity = (int) ($this->cart[$key]['quantity'] ?? 0) + 1;
        $stock = $this->stockFor($product, $variant, $session->terminal->warehouse_id);

        if ($stock !== null && $quantity > $stock) {
            Notification::make()->warning()->title(__('Existencias insuficientes'))->body(__('No hay unidades suficientes en el almacén asignado a esta caja.'))->send();

            return;
        }

        $this->cart[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
        ];
    }

    public function updateQuantity(string $key, mixed $quantity): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $quantity = max(1, (int) $quantity);
        $session = $this->currentSession();

        if (! $session) {
            return;
        }

        $line = $this->cart[$key];
        $product = Product::query()->findOrFail($line['product_id']);
        $variant = $line['variant_id'] ? ProductVariant::query()->findOrFail($line['variant_id']) : null;
        $stock = $this->stockFor($product, $variant, $session->terminal->warehouse_id);

        if ($stock !== null && $quantity > $stock) {
            Notification::make()->warning()->title(__('Existencias insuficientes'))->body(__('Disponible: :quantity', ['quantity' => $stock]))->send();
            $quantity = max(1, $stock);
        }

        $this->cart[$key]['quantity'] = $quantity;
    }

    public function removeProduct(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function customerMatches(): Collection
    {
        $term = trim($this->customerSearch);
        if (mb_strlen($term) < 2) return collect();

        return User::query()
            ->where('is_admin', false)
            ->where('admin_access_enabled', false)
            ->where(fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('name')->limit(8)->get(['id', 'name', 'email']);
    }

    public function frequentCustomers(): Collection
    {
        $session = $this->currentSession();
        if (! $session) return collect();

        $recentPosOrders = fn (Builder $query) => $query
            ->where('channel', 'pos')
            ->where('pos_warehouse_id', $session->terminal->warehouse_id)
            ->where('placed_at', '>=', now()->subDays(90));

        return User::query()
            ->where('is_admin', false)
            ->where('admin_access_enabled', false)
            ->whereHas('orders', $recentPosOrders)
            ->withCount(['orders as pos_orders_count' => $recentPosOrders])
            ->orderByDesc('pos_orders_count')->orderBy('name')->limit(5)
            ->get(['id', 'name', 'email']);
    }

    public function updatedCustomerSearch(string $value): void
    {
        if ($this->customerUserId && $value !== $this->customerName) {
            $this->customerUserId = null;
            $this->customerName = '';
            $this->customerPhone = '';
            $this->customerEmail = '';
        }
    }

    public function selectCustomer(int $userId): void
    {
        $customer = User::query()->whereKey($userId)
            ->where('is_admin', false)->where('admin_access_enabled', false)
            ->firstOrFail(['id', 'name', 'email']);

        $this->customerUserId = $customer->id;
        $this->customerSearch = $customer->name;
        $this->customerName = $customer->name;
        $this->customerEmail = $customer->email;
    }

    public function registerCustomer(): void
    {
        $this->authorizeCashierPermission('pos_cash_sessions.update');

        $this->customerEmail = mb_strtolower(trim($this->customerEmail));
        $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'customerPhone' => ['nullable', 'string', 'max:40'],
        ]);

        $customer = User::query()->create([
            'name' => trim($this->customerName),
            'email' => $this->customerEmail,
            'password' => Str::random(64),
            'is_admin' => false,
            'admin_access_enabled' => false,
        ]);

        $this->customerUserId = $customer->id;
        $this->customerSearch = $customer->name;

        Notification::make()
            ->success()
            ->title(__('Cliente registrado y vinculado'))
            ->body(__('La venta quedará asociada a la cuenta de :name.', ['name' => $customer->name]))
            ->send();
    }

    public function clearCustomer(): void
    {
        $this->customerUserId = null;
        $this->customerSearch = '';
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
    }

    public function customerHistory(): Collection
    {
        if (! $this->customerUserId || ! auth()->user()) return collect();

        $warehouseIds = auth()->user()->warehouseIdsWithPermission('view');

        return Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $warehouseIds)
            ->where('user_id', $this->customerUserId)
            ->latest('placed_at')->limit(5)
            ->get(['id', 'number', 'channel', 'currency', 'total_amount', 'status', 'payment_status', 'placed_at']);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->clearCustomer();
    }

    public function cartItems(): array
    {
        $items = [];

        foreach ($this->cart as $key => $line) {
            $product = Product::query()
                ->with(['images', 'variants' => fn (Relation $query) => $query->where('is_active', true)])
                ->find($line['product_id']);

            if (! $product || $product->status !== Product::STATUS_ACTIVE) {
                continue;
            }

            $variant = $line['variant_id'] ? $product->variants->firstWhere('id', $line['variant_id']) : null;
            $price = (int) ($variant?->price_amount ?? $product->price_amount);
            $items[] = [
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) $line['quantity'],
                'price' => $price,
                'total' => $price * (int) $line['quantity'],
            ];
        }

        return $items;
    }

    public function cartSubtotal(): int
    {
        return collect($this->cartItems())->sum('total');
    }

    public function formatMoney(int $amount, string $currency): string
    {
        return number_format($amount / 100, 2, '.', ',').' '.$currency;
    }

    public function openCashSession(): void
    {
        $this->authorizeCashierPermission('pos_cash_sessions.create');

        if (! in_array($this->openingCurrency, ['CUP', 'USD', 'EUR'], true)) {
            throw ValidationException::withMessages(['openingCurrency' => __('Selecciona una moneda admitida.')]);
        }

        $terminal = $this->terminalId ? $this->availableTerminals()->firstWhere('id', $this->terminalId) : null;

        if (! $terminal) {
            throw ValidationException::withMessages(['terminalId' => __('Selecciona un terminal autorizado y activo.')]);
        }

        $amount = $this->parseAmount($this->openingAmount, 'openingAmount', allowZero: true);
        $session = app(PosCashSessionService::class)->open(
            $terminal,
            auth()->id(),
            $this->openingCurrency,
            $amount,
            null,
        );

        $this->cashSessionId = $session->id;
        $this->cart = [];
        $this->paymentMethodId = $this->cashPaymentMethods()->first()?->id;

        Notification::make()->success()->title(__('Caja abierta'))->body(__('El terminal está listo para vender.'))->send();
    }

    public function recordCashMovement(): void
    {
        $this->authorizeCashierPermission('pos_cash_sessions.update');

        $session = $this->currentSession();

        if (! $session) {
            throw ValidationException::withMessages(['cashSessionId' => __('No hay una caja abierta seleccionada.')]);
        }

        $amount = $this->parseAmount($this->cashMovementAmount, 'cashMovementAmount');

        app(PosCashSessionService::class)->recordCashMovement(
            $session,
            auth()->id(),
            $this->cashMovementType,
            $amount,
            $this->cashMovementNote,
        );

        $this->cashMovementAmount = '';
        $this->cashMovementNote = '';

        Notification::make()->success()->title(__('Movimiento de caja registrado.'))->send();
    }

    public function closeCashSession(): void
    {
        $this->authorizeCashierPermission('pos_cash_sessions.update');

        $session = $this->currentSession();

        if (! $session) {
            throw ValidationException::withMessages(['cashSessionId' => __('No hay una caja abierta seleccionada.')]);
        }

        $amount = $this->parseAmount($this->countedCashAmount, 'countedCashAmount', allowZero: true);
        app(PosCashSessionService::class)->close($session, auth()->id(), $amount, $this->closingNote);

        $this->cashSessionId = $this->openSessions()->first()?->id;
        $this->cart = [];
        $this->countedCashAmount = '';
        $this->closingNote = '';

        Notification::make()->success()->title(__('Caja cerrada y arqueada.'))->send();
    }

    public function completeSale(): void
    {
        $this->authorizeCashierPermission('pos_cash_sessions.update');

        $session = $this->currentSession();

        if (! $session) {
            throw ValidationException::withMessages(['cashSessionId' => __('Abre o selecciona una caja antes de cobrar.')]);
        }

        if ($this->cart === []) {
            throw ValidationException::withMessages(['cart' => __('Agrega al menos un producto a la venta.')]);
        }

        $method = $this->selectedPaymentMethod();
        if (! $method) {
            throw ValidationException::withMessages(['paymentMethodId' => __('Selecciona un método de pago habilitado.')]);
        }

        $this->validate([
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:40'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $paymentDetails = [];
        if ($this->mixedPayment) {
            $this->validate([
                'mixedCashAmount' => ['required', 'numeric', 'gt:0'],
                'mixedZelleAmount' => ['required', 'numeric', 'gt:0'],
                'zelleSenderName' => ['required', 'string', 'max:255'],
                'zelleReference' => ['required', 'string', 'max:120'],
                'zelleTransferDate' => ['required', 'date'],
                'zelleProof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
            $paymentDetails = [
                'mixed' => true,
                'cash_amount' => $this->parseAmount($this->mixedCashAmount, 'mixedCashAmount'),
                'zelle_amount' => $this->parseAmount($this->mixedZelleAmount, 'mixedZelleAmount'),
                'cash_received_amount' => $this->cashReceivedAmount === ''
                    ? $this->parseAmount($this->mixedCashAmount, 'mixedCashAmount')
                    : $this->parseAmount($this->cashReceivedAmount, 'cashReceivedAmount'),
                'sender_name' => $this->zelleSenderName,
                'reference' => $this->zelleReference,
                'transfer_date' => $this->zelleTransferDate,
                'proof' => $this->zelleProof,
            ];
        } elseif ($method->driver === 'cash_on_delivery') {
            $paymentDetails['cash_received_amount'] = $this->cashReceivedAmount === ''
                ? $this->cartSubtotal()
                : $this->parseAmount($this->cashReceivedAmount, 'cashReceivedAmount');
        } else {
            $this->validate([
                'zelleSenderName' => ['required', 'string', 'max:255'],
                'zelleReference' => ['required', 'string', 'max:120'],
                'zelleTransferDate' => ['required', 'date'],
                'zelleProof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
            $paymentDetails = [
                'sender_name' => $this->zelleSenderName,
                'reference' => $this->zelleReference,
                'transfer_date' => $this->zelleTransferDate,
                'proof' => $this->zelleProof,
            ];
        }

        $order = app(PosSaleService::class)->sell(
            $session,
            auth()->id(),
            array_values(array_map(fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'product_variant_id' => $line['variant_id'] ? (int) $line['variant_id'] : null,
                'quantity' => (int) $line['quantity'],
            ], $this->cart)),
            $this->paymentMethodId,
            [
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
            ],
            $paymentDetails,
        );

        $this->lastOrderNumber = $order->number;
        $this->cashReceivedAmount = '';
        $this->mixedPayment = false;
        $this->mixedCashAmount = '';
        $this->mixedZelleAmount = '';
        $this->zelleSenderName = '';
        $this->zelleReference = '';
        $this->zelleTransferDate = '';
        $this->zelleProof = null;
        $this->clearCart();

        Notification::make()
            ->success()
            ->title(($method->driver === 'zelle_manual' || $this->mixedPayment) ? __('Venta registrada · pago pendiente de validación') : __('Venta registrada correctamente'))
            ->body(__('Pedido :number · :total', [
                'number' => $order->number,
                'total' => $this->formatMoney((int) $order->total_amount, $order->currency),
            ]))
            ->send();
    }

    public function cashBalance(PosCashSession $session): int
    {
        $authorizedSession = $this->openSessions()->firstWhere('id', $session->id);

        if (! $authorizedSession) {
            abort(403);
        }

        $totals = $authorizedSession->movements()
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_in', [
                PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_SALE,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_out', [
                PosCashMovement::TYPE_CASH_OUT, PosCashMovement::TYPE_REFUND,
            ])
            ->first();

        return (int) $session->opening_cash_amount + (int) $totals->cash_in - (int) $totals->cash_out;
    }

    private function authorizeCashierPermission(string $permission): void
    {
        abort_unless(auth()->user()?->hasAdminPermission($permission) ?? false, 403);
    }

    private function stockFor(Product $product, ?ProductVariant $variant, int $warehouseId): ?int
    {
        $inventory = Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_key', $variant ? 'variant:'.$variant->id : 'product:'.$product->id)
            ->first();

        return $inventory?->available_quantity;
    }

    private function parseAmount(string $amount, string $field, bool $allowZero = false): int
    {
        $normalized = str_replace(',', '.', trim($amount));

        if (! is_numeric($normalized) || (float) $normalized < 0 || (! $allowZero && (float) $normalized <= 0)) {
            throw ValidationException::withMessages([$field => __('Introduce un importe válido.')]);
        }

        return (int) round((float) $normalized * 100);
    }
}
