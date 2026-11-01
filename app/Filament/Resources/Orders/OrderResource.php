<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Services\Orders\OrderInventoryService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string | UnitEnum | null $navigationGroup = 'Ventas';
    protected static ?string $navigationLabel = 'Pedidos';
    protected static ?string $modelLabel = 'pedido';
    protected static ?string $pluralModelLabel = 'pedidos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Pedido')->searchable()->sortable(),
            TextColumn::make('customer_name')->label('Cliente')->searchable(),
            TextColumn::make('customer_email')->label('Correo')->searchable(),
            TextColumn::make('total_amount')->label('Importe productos')->formatStateUsing(fn ($state, Order $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label('Estado')->badge(),
            TextColumn::make('payment_status')->label('Pago')->badge(),
            TextColumn::make('placed_at')->label('Fecha')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options([
                Order::STATUS_PENDING => 'Pendiente',
                Order::STATUS_CONFIRMED => 'Confirmado',
                Order::STATUS_PROCESSING => 'En preparación',
                Order::STATUS_SHIPPED => 'Enviado',
                Order::STATUS_DELIVERED => 'Entregado',
                Order::STATUS_CANCELLED => 'Cancelado',
            ]),
        ])->defaultSort('placed_at', 'desc')->recordActions([
            Action::make('confirm')->label('Confirmar pedido')->color('success')->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING && in_array($record->payment_status, ['pending_collection', 'paid'], true))
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()])),
            Action::make('processing')->label('Preparar')->visible(fn (Order $record): bool => $record->status === Order::STATUS_CONFIRMED)
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_PROCESSING])),
            Action::make('ship')->label('Marcar enviado')->visible(fn (Order $record): bool => $record->status === Order::STATUS_PROCESSING)
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_SHIPPED])),
            Action::make('deliver')->label('Marcar entregado')->color('success')->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SHIPPED)
                ->action(function (Order $record): void {
                    DB::transaction(function () use ($record): void {
                        app(OrderInventoryService::class)->fulfill($record);
                        $record->update(['status' => Order::STATUS_DELIVERED]);
                    });
                }),
            Action::make('cancel')->label('Cancelar pedido')->color('danger')->requiresConfirmation()
                ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING], true))
                ->action(function (Order $record): void {
                    DB::transaction(function () use ($record): void {
                        app(OrderInventoryService::class)->release($record);
                        $record->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
                    });
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/')];
    }
}
