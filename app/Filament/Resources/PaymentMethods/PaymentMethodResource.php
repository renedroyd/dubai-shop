<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\PaymentMethod;
use App\Payments\PaymentDriverManager;
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
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Configuración'); }
    public static function getNavigationLabel(): string { return __('Métodos de pago'); }
    protected static ?string $modelLabel = 'método de pago';
    protected static ?string $pluralModelLabel = 'métodos de pago';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Nombre visible'))->required()->maxLength(255),
            TextInput::make('code')->label(__('Código interno'))->required()->alphaDash()->unique(ignoreRecord: true),
            Select::make('driver')->label(__('Integración'))
                ->options(fn (): array => app(PaymentDriverManager::class)->options())
                ->required()
                ->helperText(__('Los drivers disponibles se registran en config/payments.php.')),
            Textarea::make('description')->label(__('Descripción para el cliente'))->rows(2),
            Textarea::make('instructions')->label(__('Instrucciones para el cliente'))->rows(3),
            KeyValue::make('settings')->label(__('Datos de la cuenta / configuración'))->keyLabel(__('Campo'))->valueLabel(__('Valor'))
                ->helperText(__('Para Zelle puedes definir recipient_name y recipient_email. Estos datos se muestran durante el checkout.')),
            TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0),
            Toggle::make('is_enabled')->label(__('Método habilitado'))->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Método'))->searchable()->sortable(),
            TextColumn::make('driver')->label(__('Integración'))->badge(),
            IconColumn::make('is_enabled')->label(__('Habilitado'))->boolean(),
            TextColumn::make('sort_order')->label(__('Orden'))->sortable(),
        ])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label(__('Nuevo método de pago'))]);
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
