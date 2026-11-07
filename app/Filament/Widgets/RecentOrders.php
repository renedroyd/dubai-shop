<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrders extends TableWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPermission('orders.view') ?? false;
    }

    public function getHeading(): ?string
    {
        return __('Pedidos recientes');
    }

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->accessibleTo(auth()->user())->latest('placed_at'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('Pedido'))
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->limit(28),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state, Order $record): string => number_format(((int) $state) / 100, 2).' '.$record->currency),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label(__('Pago'))
                    ->badge(),
                TextColumn::make('placed_at')
                    ->label(__('Fecha'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->paginated([5]);
    }
}
