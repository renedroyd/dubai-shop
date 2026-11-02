<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockInventory extends TableWidget
{
    protected static ?string $heading = 'Inventario que requiere atención';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventory::query()
                    ->with(['product', 'variant'])
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= 5')
                    ->orderByRaw('(quantity_on_hand - quantity_reserved) asc')
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->placeholder('Producto eliminado')
                    ->searchable(),
                TextColumn::make('variant.name')
                    ->label('Variante')
                    ->placeholder('—'),
                TextColumn::make('quantity_on_hand')
                    ->label('En almacén')
                    ->numeric(),
                TextColumn::make('quantity_reserved')
                    ->label('Reservado')
                    ->numeric(),
                TextColumn::make('available_quantity')
                    ->label('Disponible')
                    ->state(fn (Inventory $record): int => max(0, $record->quantity_on_hand - $record->quantity_reserved))
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'warning'),
            ])
            ->paginated([5]);
    }
}
