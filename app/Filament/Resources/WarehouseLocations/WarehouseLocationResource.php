<?php

namespace App\Filament\Resources\WarehouseLocations;

use App\Filament\Resources\WarehouseLocations\Pages\CreateWarehouseLocation;
use App\Filament\Resources\WarehouseLocations\Pages\EditWarehouseLocation;
use App\Filament\Resources\WarehouseLocations\Pages\ListWarehouseLocations;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseLocationResource extends Resource
{
    protected static ?string $model = WarehouseLocation::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 26;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Ubicaciones'); }
    public static function getModelLabel(): string { return __('ubicación'); }
    public static function getPluralModelLabel(): string { return __('ubicaciones'); }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()->whereIn('warehouse_id', $warehouseIds);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('warehouse_id')->label(__('Almacén'))->options(fn () => Warehouse::query()->whereIn('id', auth()->user()?->warehouseIdsWithPermission('warehouse.manage') ?? [])->orderBy('name')->pluck('name', 'id'))->required()->live()->searchable(),
            Select::make('parent_id')->label(__('Ubicación superior'))
                ->options(fn (Get $get, ?WarehouseLocation $record) => WarehouseLocation::query()
                    ->where('warehouse_id', $get('warehouse_id'))
                    ->whereIn('warehouse_id', auth()->user()?->warehouseIdsWithPermission('warehouse.manage') ?? [])
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->where('type', '!=', WarehouseLocation::TYPE_UNASSIGNED)
                    ->orderBy('name')->get()->mapWithKeys(fn ($location) => [
                        $location->id => $location->code.' — '.$location->name,
                    ]))
                ->searchable()->preload()->placeholder(__('Sin ubicación superior')),
            TextInput::make('code')->label(__('Código'))->required()->alphaDash()->maxLength(60),
            TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
            Select::make('type')->label(__('Nivel'))->required()->options([
                WarehouseLocation::TYPE_AREA => __('Área'),
                WarehouseLocation::TYPE_AISLE => __('Pasillo'),
                WarehouseLocation::TYPE_SHELF => __('Estantería'),
                WarehouseLocation::TYPE_BIN => __('Posición'),
                WarehouseLocation::TYPE_QUARANTINE => __('Cuarentena / no vendible'),
                WarehouseLocation::TYPE_QUARANTINE => __('Cuarentena / no vendible'),
            ])->default(WarehouseLocation::TYPE_AREA),
            TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0),
            Toggle::make('is_active')->label(__('Activa'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('warehouse.name')->label(__('Almacén'))->sortable()->searchable(),
            TextColumn::make('code')->label(__('Código'))->searchable()->sortable(),
            TextColumn::make('name')->label(__('Ubicación'))->searchable()->sortable(),
            TextColumn::make('type')->label(__('Nivel'))->badge(),
            TextColumn::make('parent.name')->label(__('Superior'))->placeholder('—'),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
        ])->filters([
            SelectFilter::make('warehouse_id')->label(__('Almacén'))->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('type')->label(__('Nivel'))->options([
                WarehouseLocation::TYPE_AREA => __('Área'),
                WarehouseLocation::TYPE_AISLE => __('Pasillo'),
                WarehouseLocation::TYPE_SHELF => __('Estantería'),
                WarehouseLocation::TYPE_BIN => __('Posición'),
            ]),
        ])->recordActions([
            EditAction::make()->visible(fn (WarehouseLocation $record): bool => $record->type !== WarehouseLocation::TYPE_UNASSIGNED),
            DeleteAction::make()->requiresConfirmation()->visible(fn (WarehouseLocation $record): bool => $record->type !== WarehouseLocation::TYPE_UNASSIGNED && ! $record->children()->exists() && ! $record->inventoryBalances()->exists()),
        ])->headerActions([CreateAction::make()->label(__('Nueva ubicación'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseLocations::route('/'),
            'create' => CreateWarehouseLocation::route('/create'),
            'edit' => EditWarehouseLocation::route('/{record}/edit'),
        ];
    }
}
