<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Movimientos'); }
    public static function getModelLabel(): string { return __('movimiento'); }
    public static function getPluralModelLabel(): string { return __('movimientos'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('inventory.product.name')->label(__('Producto'))->searchable(),
            TextColumn::make('inventory.variant.name')->label(__('Variante'))->placeholder(__('General')),
            TextColumn::make('type')->label(__('Tipo'))->badge(),
            TextColumn::make('quantity_delta')->label(__('Variación'))->numeric(),
            TextColumn::make('reason')->label(__('Motivo'))->wrap(),
            TextColumn::make('user.name')->label(__('Usuario'))->placeholder(__('Sistema')),
            TextColumn::make('created_at')->label(__('Fecha'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('type')->options([
                'adjustment' => __('Ajuste'),
                'reservation' => __('Reserva'),
                'reservation_release' => __('Liberación de reserva'),
                'sale' => __('Venta'),
            ]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryMovements::route('/')];
    }
}
