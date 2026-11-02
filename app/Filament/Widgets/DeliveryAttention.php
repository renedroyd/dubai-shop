<?php

namespace App\\Filament\\Widgets;

use App\\Models\\Order;
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use Filament\\Widgets\\TableWidget;

class DeliveryAttention extends TableWidget
{
    protected static ?string $heading = 'Entregas fuera de plazo';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereNotNull('delivery_due_at')
                    ->where('delivery_due_at', '<', now())
                    ->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED])
                    ->orderBy('delivery_due_at')
            )
            ->columns([
                TextColumn::make('number')
                    ->label('Pedido')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->limit(24),
                TextColumn::make('delivery_due_at')
                    ->label('Compromiso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->paginated([5]);
    }
}
