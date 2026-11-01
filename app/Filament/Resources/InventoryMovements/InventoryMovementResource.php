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
    protected static string | UnitEnum | null $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Movimientos';
    protected static ?string $modelLabel = 'movimiento';
    protected static ?string $pluralModelLabel = 'movimientos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('inventory.product.name')->label('Producto')->searchable(),
            TextColumn::make('inventory.variant.name')->label('Variante')->placeholder('General'),
            TextColumn::make('type')->label('Tipo')->badge(),
            TextColumn::make('quantity_delta')->label('Variación')->numeric(),
            TextColumn::make('reason')->label('Motivo')->wrap(),
            TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema'),
            TextColumn::make('created_at')->label('Fecha')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('type')->options([
                'adjustment' => 'Ajuste',
                'reservation' => 'Reserva',
                'reservation_release' => 'Liberación de reserva',
                'sale' => 'Venta',
            ]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryMovements::route('/')];
    }
}
