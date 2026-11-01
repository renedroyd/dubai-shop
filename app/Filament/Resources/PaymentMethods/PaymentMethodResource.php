<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\PaymentMethod;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;
    protected static string | UnitEnum | null $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Métodos de pago';
    protected static ?string $modelLabel = 'método de pago';
    protected static ?string $pluralModelLabel = 'métodos de pago';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre visible')->required()->maxLength(255),
            TextInput::make('code')->label('Código interno')->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('driver')->label('Integración')->options([
                'zelle_manual' => 'Zelle — validación manual',
                'cash_on_delivery' => 'Efectivo contra entrega',
            ])->required()->helperText('Los proveedores adicionales se incorporarán mediante nuevos drivers.'),
            Textarea::make('description')->label('Descripción para el cliente')->rows(2),
            Textarea::make('instructions')->label('Instrucciones para el cliente')->rows(3),
            KeyValue::make('settings')->label('Datos de la cuenta / configuración')->keyLabel('Campo')->valueLabel('Valor')
                ->helperText('Para Zelle puedes definir recipient_name y recipient_email. Estos datos se muestran durante el checkout.'),
            TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
            Toggle::make('is_enabled')->label('Método habilitado')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Método')->searchable()->sortable(),
            TextColumn::make('driver')->label('Integración')->badge(),
            IconColumn::make('is_enabled')->label('Habilitado')->boolean(),
            TextColumn::make('sort_order')->label('Orden')->sortable(),
        ])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('Nuevo método de pago')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
