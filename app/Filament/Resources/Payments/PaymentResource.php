<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentStatusService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Ventas'); }
    public static function getNavigationLabel(): string { return __('Pagos'); }
    public static function getModelLabel(): string { return __('pago'); }
    public static function getPluralModelLabel(): string { return __('pagos'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.number')->label(__('Pedido'))->searchable()->sortable(),
            TextColumn::make('order.customer_name')->label(__('Cliente'))->searchable(),
            TextColumn::make('method.name')->label(__('Método'))->placeholder(fn (Payment $record) => $record->driver),
            TextColumn::make('amount')->label(__('Importe'))->formatStateUsing(fn ($state, Payment $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label(__('Estado'))->badge()->color(fn (string $state): string => match ($state) {
                Payment::STATUS_PAID => 'success',
                Payment::STATUS_REJECTED => 'danger',
                Payment::STATUS_AWAITING_REVIEW => 'warning',
                Payment::STATUS_PARTIALLY_REFUNDED => 'warning',
                Payment::STATUS_REFUNDED => 'gray',
                default => 'gray',
            }),
            TextColumn::make('created_at')->label(__('Fecha'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options([
                Payment::STATUS_PENDING => __('Pendiente'),
                Payment::STATUS_AWAITING_REVIEW => __('Por revisar'),
                Payment::STATUS_PAID => __('Pagado'),
                Payment::STATUS_REJECTED => __('Rechazado'),
                Payment::STATUS_PENDING_COLLECTION => __('Pendiente de cobro'),
                Payment::STATUS_PARTIALLY_REFUNDED => __('Reembolsado parcialmente'),
                Payment::STATUS_REFUNDED => __('Reembolsado'),
            ]),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('downloadProof')->label(__('Ver comprobante'))->icon('heroicon-o-arrow-down-tray')
                ->authorize('view')
                ->visible(fn (Payment $record): bool => filled($record->proof_path) && Storage::disk('local')->exists($record->proof_path))
                ->action(fn (Payment $record) => response()->download(Storage::disk('local')->path($record->proof_path))),
            Action::make('approve')->label(__('Aprobar pago'))->color('success')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_AWAITING_REVIEW && $record->order?->status === Order::STATUS_PENDING)
                ->action(fn (Payment $record) => app(PaymentStatusService::class)->approve(
                    $record,
                    auth()->id(),
                    'Aprobado manualmente desde Filament.',
                )),
            Action::make('reject')->label(__('Rechazar'))->color('danger')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_AWAITING_REVIEW && $record->order?->status === Order::STATUS_PENDING)
                ->action(fn (Payment $record) => app(PaymentStatusService::class)->reject(
                    $record,
                    auth()->id(),
                    'Rechazado manualmente desde Filament.',
                )),
            Action::make('confirmCollection')->label(__('Confirmar cobro'))->color('success')->requiresConfirmation()
                ->authorize('update')
                ->visible(fn (Payment $record): bool => $record->driver === 'cash_on_delivery' && $record->status === Payment::STATUS_PENDING_COLLECTION)
                ->action(fn (Payment $record) => app(PaymentStatusService::class)->confirmCollection(
                    $record,
                    auth()->id(),
                    'Cobro contra entrega confirmado desde Filament.',
                )),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
