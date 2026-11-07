<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ListInventories;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
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
            Filter::make('low_stock')->label(__('Stock bajo'))->query(fn (Builder $query): Builder => $query->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= 5')),
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
