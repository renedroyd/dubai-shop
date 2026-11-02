<?php

namespace App\\Filament\\Widgets;

use App\\Models\\Order;
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use Filament\\Widgets\\TableWidget;
use Illuminate\\Database\\Eloquent\\Builder;

class RecentOrders extends TableWidget
{
    protected static ?string $heading = 'Pedidos recientes';

    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Order::query()->latest('placed_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('number')
                ->label('Pedido')
                ->searchable(),
            TextColumn::make('customer_name')
                ->label('Cliente')
                ->searchable()
                ->limit(28),
            TextColumn::make('total_amount')
                ->label('Total')
                ->formatStateUsing(fn ($state, Order $record): string => number_format(((int) $state) / 100, 2).' '.$record->currency),
            TextColumn::make('status')
                ->label('Estado')
                ->badge(),
            TextColumn::make('payment_status')
                ->label('Pago')
                ->badge(),
            TextColumn::make('placed_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    protected function getTableDefaultPaginationPageOption(): int
    {
        return 5;
    }
}
