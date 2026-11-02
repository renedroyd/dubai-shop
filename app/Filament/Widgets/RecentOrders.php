<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrders extends TableWidget
{
    protected static ?string $heading = 'Pedidos recientes';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest('placed_at'))
            ->columns([
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
            ])
            ->defaultSort('placed_at', 'desc')
            ->paginated([5]);
    }
}
