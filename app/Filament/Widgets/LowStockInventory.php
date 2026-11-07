<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockInventory extends TableWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPermission('inventories.view') ?? false;
    }

    public function getHeading(): ?string
    {
        return __('Inventario que requiere atención');
    }

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventory::query()->accessibleTo(auth()->user())
                    ->with(['product', 'variant'])
                    ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) <= COALESCE(minimum_stock_quantity, 5)')
                    ->orderByRaw('(quantity_on_hand - quantity_reserved - quantity_blocked) asc')
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('Producto'))
                    ->placeholder(__('Producto eliminado'))
                    ->searchable(),
                TextColumn::make('variant.name')
                    ->label(__('Variante'))
                    ->placeholder(__('—')),
                TextColumn::make('quantity_on_hand')
                    ->label(__('En almacén'))
                    ->numeric(),
                TextColumn::make('quantity_reserved')
                    ->label(__('Reservado'))
                    ->numeric(),
                TextColumn::make('quantity_blocked')
                    ->label(__('Bloqueado'))
                    ->numeric(),
                TextColumn::make('minimum_stock_quantity')
                    ->label(__('Umbral'))
                    ->state(fn (Inventory $record): int => $record->minimum_stock_quantity ?? 5)
                    ->numeric(),
                TextColumn::make('available_quantity')
                    ->label(__('Disponible'))
                    ->state(fn (Inventory $record): int => $record->available_quantity)
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'warning'),
            ])
            ->paginated([5]);
    }
}
