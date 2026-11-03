<?php

namespace App\Filament\Resources\NotificationLogs;

use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Jobs\SendOrderNotification;
use App\Models\NotificationLog;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Comunicación'); }
    public static function getNavigationLabel(): string { return __('Registro de correos'); }
    protected static ?string $modelLabel = 'registro de correo';
    protected static ?string $pluralModelLabel = 'registros de correo';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.number')->label(__('Pedido'))->placeholder(__('—'))->searchable(),
            TextColumn::make('event_key')->label(__('Evento'))->badge(),
            TextColumn::make('recipient')->label(__('Destinatario'))->searchable(),
            TextColumn::make('subject')->label(__('Asunto'))->limit(45),
            TextColumn::make('status')->label(__('Estado'))->badge()->color(fn (string $state): string => match ($state) {
                'sent' => 'success',
                'failed' => 'danger',
                'sending' => 'warning',
                'skipped' => 'gray',
                default => 'info',
            }),
            TextColumn::make('attempts')->label(__('Intentos'))->sortable(),
            TextColumn::make('error_message')->label(__('Error'))->limit(70)->placeholder(__('—')),
            TextColumn::make('sent_at')->label(__('Enviado'))->dateTime()->placeholder(__('—'))->sortable(),
            TextColumn::make('created_at')->label(__('Registrado'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->label(__('Estado'))->options([
                'queued' => 'En cola',
                'sending' => 'Enviando',
                'sent' => 'Enviado',
                'failed' => 'Fallido',
                'skipped' => 'Omitido',
            ]),
            SelectFilter::make('event_key')->label(__('Evento'))->options([
                'order_received' => 'Pedido recibido',
                'payment_approved' => 'Pago aprobado',
                'payment_rejected' => 'Pago rechazado',
                'order_confirmed' => 'Pedido confirmado',
                'order_processing' => 'En preparación',
                'order_shipped' => 'Enviado',
                'order_delivered' => 'Entregado',
                'order_cancelled' => 'Cancelado',
            ]),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('retry')->label(__('Reintentar'))->icon('heroicon-o-arrow-path')->color('warning')
                ->requiresConfirmation()
                ->visible(fn (NotificationLog $record): bool => $record->status === 'failed' && $record->order_id !== null)
                ->action(function (NotificationLog $record): void {
                    $record->update(['status' => 'queued', 'error_message' => null]);
                    SendOrderNotification::dispatch($record->order_id, $record->event_key);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationLogs::route('/')];
    }
}
