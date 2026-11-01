<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Order;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static string | UnitEnum | null $navigationGroup = 'Ventas';
    protected static ?string $navigationLabel = 'Pagos';
    protected static ?string $modelLabel = 'pago';
    protected static ?string $pluralModelLabel = 'pagos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.number')->label('Pedido')->searchable()->sortable(),
            TextColumn::make('order.customer_name')->label('Cliente')->searchable(),
            TextColumn::make('method.name')->label('Método')->placeholder(fn (Payment $record) => $record->driver),
            TextColumn::make('amount')->label('Importe')->formatStateUsing(fn ($state, Payment $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                Payment::STATUS_PAID => 'success',
                Payment::STATUS_REJECTED => 'danger',
                Payment::STATUS_AWAITING_REVIEW => 'warning',
                default => 'gray',
            }),
            TextColumn::make('created_at')->label('Fecha')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options([
                Payment::STATUS_PENDING => 'Pendiente',
                Payment::STATUS_AWAITING_REVIEW => 'Por revisar',
                Payment::STATUS_PAID => 'Pagado',
                Payment::STATUS_REJECTED => 'Rechazado',
                Payment::STATUS_PENDING_COLLECTION => 'Pendiente de cobro',
            ]),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('downloadProof')->label('Ver comprobante')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (Payment $record): bool => filled($record->proof_path) && Storage::disk('local')->exists($record->proof_path))
                ->action(fn (Payment $record) => response()->download(Storage::disk('local')->path($record->proof_path))),
            Action::make('approve')->label('Aprobar pago')->color('success')->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_AWAITING_REVIEW)
                ->action(function (Payment $record): void {
                    DB::transaction(function () use ($record): void {
                        $payment = Payment::query()->lockForUpdate()->findOrFail($record->id);
                        $payment->update(['status' => Payment::STATUS_PAID, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                        $payment->order()->update([
                            'payment_status' => 'paid',
                            'status' => Order::STATUS_CONFIRMED,
                            'confirmed_at' => now(),
                        ]);
                    });
                }),
            Action::make('reject')->label('Rechazar')->color('danger')->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_AWAITING_REVIEW)
                ->action(function (Payment $record): void {
                    DB::transaction(function () use ($record): void {
                        $payment = Payment::query()->lockForUpdate()->findOrFail($record->id);
                        $payment->update(['status' => Payment::STATUS_REJECTED, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                        $payment->order()->update(['payment_status' => 'payment_rejected']);
                    });
                }),
            Action::make('confirmCollection')->label('Confirmar cobro')->color('success')->requiresConfirmation()
                ->visible(fn (Payment $record): bool => $record->status === Payment::STATUS_PENDING_COLLECTION)
                ->action(function (Payment $record): void {
                    DB::transaction(function () use ($record): void {
                        $payment = Payment::query()->lockForUpdate()->findOrFail($record->id);
                        $payment->update(['status' => Payment::STATUS_PAID, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                        $payment->order()->update(['payment_status' => 'paid']);
                    });
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
