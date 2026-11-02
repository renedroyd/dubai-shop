<?php

namespace App\Filament\Resources\NotificationTemplates;

use App\Filament\Resources\NotificationTemplates\Pages\CreateNotificationTemplate;
use App\Filament\Resources\NotificationTemplates\Pages\EditNotificationTemplate;
use App\Filament\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Models\NotificationTemplate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;
    protected static string | UnitEnum | null $navigationGroup = 'Comunicación';
    protected static ?string $navigationLabel = 'Plantillas de correo';
    protected static ?string $modelLabel = 'plantilla de correo';
    protected static ?string $pluralModelLabel = 'plantillas de correo';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido de la notificación')->schema([
                Select::make('event_key')->label('Evento')->options([
                    'order_received' => 'Pedido recibido',
                    'payment_approved' => 'Pago aprobado',
                    'payment_rejected' => 'Pago rechazado',
                    'order_confirmed' => 'Pedido confirmado',
                    'order_processing' => 'Pedido en preparación',
                    'order_shipped' => 'Pedido enviado',
                    'delivery_attempt_failed' => 'Intento de entrega no completado',
                    'order_delivered' => 'Pedido entregado',
                    'order_cancelled' => 'Pedido cancelado',
                ])->required()->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nombre interno')->required()->maxLength(160),
                TextInput::make('subject')->label('Asunto del correo')->required()->maxLength(255),
                Textarea::make('body')->label('Mensaje')->required()->rows(12)->columnSpanFull()
                    ->helperText('Variables disponibles: {order_number}, {customer_name}, {order_status}, {payment_status}, {order_total}, {currency}, {store_name}, {tracking_code}, {courier_name}, {courier_phone}. El mensaje se envía como texto plano.'),
                Toggle::make('is_active')->label('Plantilla activa')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Plantilla')->searchable()->sortable(),
            TextColumn::make('event_key')->label('Evento')->badge(),
            TextColumn::make('subject')->label('Asunto')->limit(55),
            IconColumn::make('is_active')->label('Activa')->boolean(),
            TextColumn::make('updated_at')->label('Actualizada')->dateTime()->sortable(),
        ])->filters([
            TernaryFilter::make('is_active')->label('Estado')->trueLabel('Activas')->falseLabel('Inactivas'),
        ])->defaultSort('name')->recordActions([
            EditAction::make(),
            DeleteAction::make()->requiresConfirmation(),
        ])->headerActions([
            CreateAction::make()->label('Nueva plantilla'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
