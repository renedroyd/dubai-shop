<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Services\Orders\OrderInventoryService;
use App\Services\Shipping\ShipmentService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Ventas'); }
    public static function getNavigationLabel(): string { return __('Pedidos'); }
    public static function getModelLabel(): string { return __('pedido'); }
    public static function getPluralModelLabel(): string { return __('pedidos'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label(__('Pedido'))->searchable()->sortable(),
            TextColumn::make('channel')->label(__('Canal'))->badge(),
            TextColumn::make('posTerminal.code')->label(__('Terminal'))->placeholder('—'),
            TextColumn::make('customer_name')->label(__('Cliente'))->searchable(),
            TextColumn::make('customer_email')->label(__('Correo'))->searchable(),
            TextColumn::make('total_amount')->label(__('Total del pedido'))->formatStateUsing(fn ($state, Order $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label(__('Estado'))->badge(),
            TextColumn::make('payment_status')->label(__('Pago'))->badge(),
            TextColumn::make('placed_at')->label(__('Fecha'))->dateTime()->sortable(),
            TextColumn::make('delivery_due_at')->label(__('Entrega límite'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options([
                Order::STATUS_PENDING => __('Pendiente'),
                Order::STATUS_CONFIRMED => __('Confirmado'),
                Order::STATUS_PROCESSING => __('En preparación'),
                Order::STATUS_SHIPPED => __('Enviado'),
                Order::STATUS_DELIVERED => __('Entregado'),
                Order::STATUS_CANCELLED => __('Cancelado'),
            ]),
        ])->defaultSort('placed_at', 'desc')->recordActions([
            Action::make('details')->label(__('Ver pedido'))->icon('heroicon-o-eye')
                ->authorize('view')
                ->modalHeading(fn (Order $record): string => __('Pedido').' '.$record->number)
                ->modalContent(fn (Order $record) => view('filament.orders.details', ['order' => $record->load(['items', 'payments.method', 'payments.events.actor', 'shipment.events', 'shipment.attempts'])]))
                ->modalSubmitAction(false)->modalCancelActionLabel(__('Cerrar')),
            Action::make('confirm')->label(__('Confirmar pedido'))->color('success')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING && in_array($record->payment_status, ['pending_collection', 'paid'], true))
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()])),
            Action::make('processing')->label(__('Preparar'))->visible(fn (Order $record): bool => $record->status === Order::STATUS_CONFIRMED)
                ->authorize('update')
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_PROCESSING])),
            Action::make('assignCourier')->label(__('Asignar repartidor'))->icon('heroicon-o-user')
                ->authorize('update')
                ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED], true))
                ->form([
                    TextInput::make('courier_name')->label(__('Nombre del repartidor'))->required()->maxLength(160),
                    TextInput::make('courier_phone')->label(__('Teléfono de contacto'))->tel()->maxLength(40),
                ])
                ->fillForm(fn (Order $record): array => [
                    'courier_name' => $record->shipment?->courier_name,
                    'courier_phone' => $record->shipment?->courier_phone,
                ])
                ->action(fn (Order $record, array $data) => app(ShipmentService::class)->assignCourier(
                    $record, $data['courier_name'], $data['courier_phone'] ?? null, auth()->id(),
                )),
            Action::make('dispatch')->label(__('Despachar pedido'))->icon('heroicon-o-truck')->color('info')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED], true)
                    && filled($record->shipment?->courier_name)
                    && in_array($record->shipment?->status, ['assigned', 'delivery_failed'], true))
                ->action(fn (Order $record) => app(ShipmentService::class)->dispatch($record, auth()->id())),
            Action::make('deliveryAttempt')->label(__('Registrar intento de entrega'))->icon('heroicon-o-clipboard-document-check')
                ->authorize('update')
                ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SHIPPED && $record->shipment?->status === 'dispatched')
                ->form([
                    Select::make('result')->label(__('Resultado'))->options(['delivered' => __('Entregado'), 'failed' => __('No se pudo entregar')])->required(),
                    TextInput::make('recipient_name')->label(__('Persona que recibe'))->maxLength(160),
                    Textarea::make('note')->label(__('Observaciones / incidencia'))->rows(3)->maxLength(2000),
                ])
                ->action(fn (Order $record, array $data) => app(ShipmentService::class)->recordAttempt(
                    $record->shipment, $data['result'], $data['recipient_name'] ?? null, $data['note'] ?? null, auth()->id(),
                )),
            Action::make('cancel')->label(__('Cancelar pedido'))->color('danger')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING], true) && $record->payment_status !== 'paid')
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
