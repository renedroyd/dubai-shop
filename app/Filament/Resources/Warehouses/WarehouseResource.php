<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Almacenes'); }
    public static function getModelLabel(): string { return __('almacén'); }
    public static function getPluralModelLabel(): string { return __('almacenes'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('Código'))->required()->alphaDash()->maxLength(40)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
            Select::make('type')->label(__('Tipo'))->required()->options([
                'general' => __('General'),
                'store' => __('Tienda física'),
                'online' => __('Tienda online'),
                'pos' => __('Punto de venta'),
                'transit' => __('Tránsito'),
            ])->default('general'),
            Textarea::make('address')->label(__('Dirección'))->rows(2),
            Textarea::make('notes')->label(__('Notas internas'))->rows(3),
            Toggle::make('is_primary')->label(__('Almacén principal'))
                ->helperText(__('Solo puede existir un almacén principal.'))
                ->disabled(fn (?Warehouse $record): bool => (bool) ($record?->is_primary)),
            Toggle::make('is_active')->label(__('Activo'))->default(true)
                ->disabled(fn (?Warehouse $record): bool => (bool) ($record?->is_primary)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('Código'))->searchable()->sortable(),
            TextColumn::make('name')->label(__('Almacén'))->searchable()->sortable(),
            TextColumn::make('type')->label(__('Tipo'))->badge(),
            TextColumn::make('inventories_count')->counts('inventories')->label(__('Referencias')),
            IconColumn::make('is_primary')->label(__('Principal'))->boolean(),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label(__('Estado'))])
          ->recordActions([
              EditAction::make(),
              DeleteAction::make()->requiresConfirmation()
                  ->visible(fn (Warehouse $record): bool => ! $record->is_primary && ! $record->inventories()->exists() && ! $record->locations()->exists()),
          ])->headerActions([CreateAction::make()->label(__('Nuevo almacén'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
