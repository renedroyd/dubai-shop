<?php

namespace App\Filament\Resources\InventoryLocationBalances;

use App\Filament\Resources\InventoryLocationBalances\Pages\ListInventoryLocationBalances;
use App\Models\InventoryLocationBalance;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryLocationService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryLocationBalanceResource extends Resource
{
    protected static ?string $model = InventoryLocationBalance::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Stock por ubicación'); }
    public static function getModelLabel(): string { return __('saldo por ubicación'); }
    public static function getPluralModelLabel(): string { return __('stock por ubicación'); }

    public static function getEloquentQuery(): Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()->whereHas('inventory', fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('inventory.warehouse.name')->label(__('Almacén'))->sortable(),
            TextColumn::make('inventory.product.name')->label(__('Producto'))->searchable()->sortable(),
            TextColumn::make('inventory.variant.name')->label(__('Variante'))->placeholder(__('Producto general')),
            TextColumn::make('location.code')->label(__('Código ubicación'))->searchable(),
            TextColumn::make('location.name')->label(__('Ubicación'))->searchable(),
            TextColumn::make('quantity_on_hand')->label(__('Existencia'))->numeric()->sortable(),
            TextColumn::make('inventory.quantity_on_hand')->label(__('Total almacén'))->numeric(),
            TextColumn::make('reconciliation')->label(__('Conciliación'))
                ->state(fn (InventoryLocationBalance $record): string => app(InventoryLocationService::class)->reconcile($record->inventory)['is_balanced']
                    ? __('Cuadrado') : __('Diferencia'))
                ->badge()
                ->color(fn (string $state): string => $state === __('Cuadrado') ? 'success' : 'danger'),
            TextColumn::make('updated_at')->label(__('Actualizado'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('warehouse')->label(__('Almacén'))
                ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas('inventory', fn (Builder $inventory) => $inventory->where('warehouse_id', $data['value']))
                    : $query),
            SelectFilter::make('warehouse_location_id')->label(__('Ubicación'))
                ->options(fn () => WarehouseLocation::query()->where('type', '!=', WarehouseLocation::TYPE_UNASSIGNED)->orderBy('name')->pluck('name', 'id')),
        ])->defaultSort('updated_at', 'desc')->recordActions([
            Action::make('adjust')->label(__('Ajustar en ubicación'))->icon('heroicon-o-adjustments-horizontal')
                ->authorize('update')
                ->visible(fn (InventoryLocationBalance $record): bool => $record->location->type !== WarehouseLocation::TYPE_UNASSIGNED)
                ->schema([
                    TextInput::make('quantity')->label(__('Nueva existencia en esta ubicación'))->numeric()->integer()->minValue(0)->required(),
                    Textarea::make('reason')->label(__('Motivo'))->required()->maxLength(500),
                ])
                ->fillForm(fn (InventoryLocationBalance $record): array => ['quantity' => $record->quantity_on_hand])
                ->action(fn (InventoryLocationBalance $record, array $data) => app(InventoryLocationService::class)->adjust(
                    $record->inventory,
                    $record->location,
                    (int) $data['quantity'],
                    (int) auth()->id(),
                    $data['reason'],
                )),
            Action::make('transfer')->label(__('Mover a otra ubicación'))->icon('heroicon-o-arrows-right-left')
                ->authorize('update')
                ->schema([
                    Select::make('to_location_id')->label(__('Ubicación destino'))->options(fn (InventoryLocationBalance $record) => WarehouseLocation::query()
                        ->where('warehouse_id', $record->inventory->warehouse_id)
                        ->whereKeyNot($record->warehouse_location_id)
                        ->where('is_active', true)
                        ->where('type', '!=', WarehouseLocation::TYPE_UNASSIGNED)
                        ->orderBy('name')->get()->mapWithKeys(fn (WarehouseLocation $location) => [
                            $location->id => $location->code.' — '.$location->name,
                        ]))->searchable()->required(),
                    TextInput::make('quantity')->label(__('Cantidad a mover'))->numeric()->integer()->minValue(1)->required(),
                    Textarea::make('reason')->label(__('Motivo'))->required()->maxLength(500),
                ])
                ->action(function (InventoryLocationBalance $record, array $data): void {
                    $destination = WarehouseLocation::query()->findOrFail($data['to_location_id']);
                    app(InventoryLocationService::class)->transfer(
                        $record->inventory,
                        $record->location,
                        $destination,
                        (int) $data['quantity'],
                        (int) auth()->id(),
                        $data['reason'],
                    );
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryLocationBalances::route('/')];
    }
}
