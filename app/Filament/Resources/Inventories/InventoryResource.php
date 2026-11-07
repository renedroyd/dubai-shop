<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ListInventories;
use App\Models\Inventory;
use App\Models\InventoryLocationBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\WarehouseTransferService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Existencias'); }
    public static function getModelLabel(): string { return __('existencia'); }
    public static function getPluralModelLabel(): string { return __('existencias'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()->whereIn('warehouse_id', $warehouseIds);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('warehouse.name')->label(__('Almacén'))->sortable(),
            TextColumn::make('product.name')->label(__('Producto'))->searchable()->sortable(),
            TextColumn::make('variant.name')->label(__('Variante'))->placeholder(__('Producto general')),
            TextColumn::make('quantity_on_hand')->label(__('Existencia'))->sortable(),
            TextColumn::make('quantity_reserved')->label(__('Reservado'))->sortable(),
            TextColumn::make('quantity_blocked')->label(__('Bloqueado'))->sortable(),
            TextColumn::make('minimum_stock_quantity')->label(__('Umbral mínimo'))->placeholder('5 (predeterminado)')->sortable(),
            TextColumn::make('acquisition_cost_amount')->label(__('Costo de adquisición'))
                ->state(fn (Inventory $record): string => $record->acquisition_cost_amount === null ? __('Sin definir') : number_format($record->acquisition_cost_amount / 100, 2).' '.($record->product?->currency ?? ''))
                ->visible(fn (): bool => auth()->user()?->hasAdminPermission('inventory_costs.view') ?? false),
            TextColumn::make('minimum_sale_price_amount')->label(__('Precio mínimo'))
                ->state(fn (Inventory $record): string => $record->minimum_sale_price_amount === null ? __('Costo de adquisición') : number_format($record->minimum_sale_price_amount / 100, 2).' '.($record->product?->currency ?? ''))
                ->visible(fn (): bool => auth()->user()?->hasAdminPermission('inventory_costs.view') ?? false),
            TextColumn::make('available_quantity')->label(__('Disponible'))->state(fn (Inventory $record): int => $record->available_quantity)->badge()
                ->color(fn (int $state): string => $state === 0 ? 'danger' : ($state <= 5 ? 'warning' : 'success')),
            TextColumn::make('updated_at')->label(__('Actualizado'))->dateTime()->sortable(),
        ])->filters([
            Filter::make('low_stock')->label(__('Stock bajo'))->query(fn (Builder $query): Builder => $query->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= COALESCE(minimum_stock_quantity, 5)'),
        ])->defaultSort('updated_at', 'desc')->recordActions([
            Action::make('configureCosts')->label(__('Configurar costo y precio mínimo'))->icon('heroicon-o-currency-dollar')
                ->authorize(fn (Inventory $record): bool => (auth()->user()?->hasAdminPermission('inventory_costs.update') ?? false)
                    && (auth()->user()?->hasWarehousePermission($record->warehouse_id, 'inventory.manage') ?? false))
                ->schema([
                    TextInput::make('acquisition_cost')->label(__('Costo de adquisición (moneda del producto)'))->numeric()->minValue(0)->step(0.01)
                        ->helperText(__('Deja vacío si aún no conoces el costo. Se almacena por producto/variante y almacén.')),
                    TextInput::make('minimum_sale_price')->label(__('Precio mínimo de venta (opcional)'))->numeric()->minValue(0)->step(0.01)
                        ->helperText(__('Si queda vacío, el costo de adquisición será el límite inferior.')),
                    Textarea::make('reason')->label(__('Motivo del cambio'))->required()->maxLength(500),
                ])
                ->fillForm(fn (Inventory $record): array => [
                    'acquisition_cost' => $record->acquisition_cost_amount === null ? null : number_format($record->acquisition_cost_amount / 100, 2, '.', ''),
                    'minimum_sale_price' => $record->minimum_sale_price_amount === null ? null : number_format($record->minimum_sale_price_amount / 100, 2, '.', ''),
                ])
                ->action(function (Inventory $record, array $data): void {
                    $toMinor = fn ($value): ?int => $value === null || trim((string) $value) === '' ? null : (int) round((float) str_replace(',', '.', (string) $value) * 100);
                    app(InventoryCostService::class)->updateCosts(
                        $record,
                        auth()->user(),
                        $toMinor($data['acquisition_cost'] ?? null),
                        $toMinor($data['minimum_sale_price'] ?? null),
                        $data['reason'],
                    );
                }),
            Action::make('configureStockAlert')->label(__('Configurar alerta de stock'))->icon('heroicon-o-bell-alert')
                ->authorize(fn (Inventory $record): bool => (auth()->user()?->hasAdminPermission('inventories.update') ?? false)
                    && (auth()->user()?->hasWarehousePermission($record->warehouse_id, 'inventory.manage') ?? false))
                ->schema([
                    TextInput::make('minimum_stock_quantity')->label(__('Umbral mínimo de existencias'))->numeric()->integer()->minValue(0)->nullable()
                        ->helperText(__('Cantidad disponible que activa la alerta. Si queda vacío, se aplica el umbral predeterminado de 5 unidades.')),
                ])
                ->fillForm(fn (Inventory $record): array => ['minimum_stock_quantity' => $record->minimum_stock_quantity])
                ->action(fn (Inventory $record, array $data) => $record->update([
                    'minimum_stock_quantity' => filled($data['minimum_stock_quantity'] ?? null) ? (int) $data['minimum_stock_quantity'] : null,
                ])),
            Action::make('requestReplenishment')->label(__('Solicitar reposición'))->icon('heroicon-o-arrow-path-rounded-square')->color('warning')
                ->visible(fn (Inventory $record): bool => $record->available_quantity <= ($record->minimum_stock_quantity ?? 5))
                ->authorize(fn (Inventory $record): bool => auth()->user()?->hasWarehousePermission($record->warehouse_id, 'transfers.request') ?? false)
                ->schema([
                    Select::make('source_warehouse_id')->label(__('Almacén de origen'))
                        ->options(fn (Inventory $record) => Warehouse::query()->where('is_active', true)
                            ->whereIn('id', auth()->user()?->warehouseIdsWithPermission('transfers.request') ?? [])
                            ->whereKeyNot($record->warehouse_id)->orderBy('name')->pluck('name', 'id'))
                        ->live()->searchable()->required(),
                    Select::make('source_inventory_id')->label(__('Producto disponible en origen'))
                        ->options(fn (callable $get, Inventory $record) => filled($get('source_warehouse_id'))
                            ? Inventory::query()->where('warehouse_id', (int) $get('source_warehouse_id'))
                                ->where('stock_key', $record->stock_key)->with(['product', 'variant'])->get()
                                ->filter(fn (Inventory $source) => $source->available_quantity > 0)
                                ->mapWithKeys(fn (Inventory $source) => [$source->id => ($source->product?->name ?? $source->stock_key)
                                    .($source->variant?->name ? ' — '.$source->variant->name : '')
                                    .' · '.__('Disponibles').': '.$source->available_quantity])
                            : [])
                        ->live()->searchable()->required(),
                    Select::make('source_location_id')->label(__('Ubicación de origen'))
                        ->options(fn (callable $get) => filled($get('source_inventory_id'))
                            ? InventoryLocationBalance::query()->where('inventory_id', (int) $get('source_inventory_id'))
                                ->where('quantity_on_hand', '>', 0)->with('location')->get()
                                ->filter(fn (InventoryLocationBalance $balance) => $balance->location?->is_active)
                                ->mapWithKeys(fn (InventoryLocationBalance $balance) => [
                                    $balance->warehouse_location_id => $balance->location->code.' — '.$balance->location->name.' ('.$balance->quantity_on_hand.')',
                                ])
                            : [])
                        ->searchable()->required(),
                    Select::make('destination_location_id')->label(__('Ubicación de destino'))
                        ->options(fn (Inventory $record) => WarehouseLocation::query()->where('warehouse_id', $record->warehouse_id)
                            ->where('is_active', true)->where('type', '!=', WarehouseLocation::TYPE_UNASSIGNED)
                            ->orderBy('code')->get()->mapWithKeys(fn (WarehouseLocation $location) => [
                                $location->id => $location->code.' — '.$location->name,
                            ]))
                        ->searchable()->required()
                        ->helperText(__('El almacén de destino debe tener al menos una ubicación activa distinta de “Sin ubicación asignada”.')),
                    TextInput::make('quantity')->label(__('Cantidad solicitada'))->numeric()->integer()->minValue(1)->required(),
                    Textarea::make('notes')->label(__('Motivo / notas'))->maxLength(1000),
                ])
                ->action(function (Inventory $record, array $data): void {
                    $sourceWarehouse = Warehouse::query()->findOrFail($data['source_warehouse_id']);
                    $destinationWarehouse = Warehouse::query()->findOrFail($record->warehouse_id);
                    $sourceInventory = Inventory::query()
                        ->where('warehouse_id', $sourceWarehouse->id)
                        ->where('stock_key', $record->stock_key)
                        ->findOrFail($data['source_inventory_id']);
                    $sourceLocation = WarehouseLocation::query()
                        ->where('warehouse_id', $sourceWarehouse->id)->findOrFail($data['source_location_id']);
                    $destinationLocation = WarehouseLocation::query()
                        ->where('warehouse_id', $destinationWarehouse->id)->findOrFail($data['destination_location_id']);
                    $quantity = (int) $data['quantity'];
                    $sourceBalance = InventoryLocationBalance::query()
                        ->where('inventory_id', $sourceInventory->id)
                        ->where('warehouse_location_id', $sourceLocation->id)->first();

                    if ($quantity > $sourceInventory->available_quantity || ! $sourceBalance || $quantity > $sourceBalance->quantity_on_hand) {
                        throw ValidationException::withMessages(['quantity' => __('La cantidad solicitada supera las existencias disponibles en el origen o su ubicación.')]);
                    }

                    app(WarehouseTransferService::class)->create(
                        $sourceWarehouse,
                        $destinationWarehouse,
                        [[
                            'inventory_id' => $sourceInventory->id,
                            'source_location_id' => $sourceLocation->id,
                            'destination_location_id' => $destinationLocation->id,
                            'quantity' => $quantity,
                        ]],
                        auth()->id(),
                        $data['notes'] ?? null,
                        'replenishment',
                    );
                }),
            Action::make('adjust')->label(__('Ajustar existencia'))->icon('heroicon-o-adjustments-horizontal')
                ->authorize('update')
                ->schema([
                    TextInput::make('quantity')->label(__('Nueva existencia total'))->numeric()->integer()->minValue(0)->required(),
                    Textarea::make('reason')->label(__('Motivo del ajuste'))->required()->maxLength(500),
                ])
                ->fillForm(fn (Inventory $record): array => ['quantity' => $record->quantity_on_hand])
                ->action(function (Inventory $record, array $data): void {
                    app(InventoryService::class)->setOnHand(
                        $record->product,
                        (int) $data['quantity'],
                        $record->variant,
                        auth()->id(),
                        $data['reason'],
                        $record->warehouse,
                    );
                }),
        ])->headerActions([
            Action::make('registerStock')->label(__('Registrar producto / variante'))->icon('heroicon-o-plus')
                ->authorize(fn (): bool => auth()->user()?->hasAdminPermission('inventories.update') ?? false)
                ->schema([
                    Select::make('warehouse_id')->label(__('Almacén'))
                        ->options(fn () => Warehouse::query()->whereIn('id', auth()->user()?->warehouseIdsWithPermission('inventory.manage') ?? [])->orderBy('name')->pluck('name', 'id'))
                        ->required(),
                    Select::make('product_id')->label(__('Producto'))->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                    Select::make('product_variant_id')->label(__('Variante (opcional)'))->options(fn (callable $get) => ProductVariant::query()->where('product_id', $get('product_id'))->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable(),
                    TextInput::make('quantity')->label(__('Existencia inicial'))->numeric()->integer()->minValue(0)->required()->default(0),
                    Textarea::make('reason')->label(__('Motivo'))->required()->maxLength(500)->default('Carga inicial'),
                ])
                ->action(function (array $data): void {
                    $product = Product::query()->findOrFail($data['product_id']);
                    $variant = filled($data['product_variant_id'])
                        ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($data['product_variant_id'])
                        : null;

                    app(InventoryService::class)->setOnHand(
                        $product,
                        (int) $data['quantity'],
                        $variant,
                        auth()->id(),
                        $data['reason'],
                    );
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventories::route('/')];
    }
}
