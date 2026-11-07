<?php

namespace App\Filament\Resources\PosCashSessions;

use App\Filament\Resources\PosCashSessions\Pages\ListPosCashSessions;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\PosTerminal;
use App\Services\Pos\PosCashSessionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PosCashSessionResource extends Resource
{
    protected static ?string $model = PosCashSession::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Cajas y arqueos'); }
    public static function getModelLabel(): string { return __('sesión de caja'); }
    public static function getPluralModelLabel(): string { return __('sesiones de caja'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('terminal.location.name')->label(__('Establecimiento'))->searchable(),
            TextColumn::make('terminal.code')->label(__('Terminal'))->searchable(),
            TextColumn::make('opener.name')->label(__('Abierta por')),
            TextColumn::make('currency')->label(__('Moneda')),
            TextColumn::make('opening_cash_amount')->label(__('Fondo inicial'))->formatStateUsing(fn ($state, PosCashSession $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('expected_cash_amount')->label(__('Efectivo esperado'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('counted_cash_amount')->label(__('Efectivo contado'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('difference_amount')->label(__('Diferencia'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label(__('Estado'))->badge()->color(fn (string $state): string => $state === PosCashSession::STATUS_OPEN ? 'success' : 'gray'),
            TextColumn::make('opened_at')->label(__('Apertura'))->dateTime()->sortable(),
            TextColumn::make('closed_at')->label(__('Cierre'))->dateTime(),
        ])->filters([
            SelectFilter::make('status')->options([
                PosCashSession::STATUS_OPEN => __('Abierta'),
                PosCashSession::STATUS_CLOSED => __('Cerrada'),
            ]),
        ])->defaultSort('opened_at', 'desc')->recordActions([
            Action::make('cashIn')->label(__('Entrada de efectivo'))->icon('heroicon-o-arrow-down-circle')
                ->authorize('update')
                ->visible(fn (PosCashSession $record): bool => $record->isOpen())
                ->form([
                    TextInput::make('amount')->label(__('Importe en centavos'))->numeric()->integer()->minValue(1)->required(),
                    Textarea::make('note')->label(__('Motivo'))->required()->maxLength(1000),
                    TextInput::make('reference')->label(__('Referencia'))->maxLength(120),
                ])
                ->action(fn (PosCashSession $record, array $data) => app(PosCashSessionService::class)->recordCashMovement(
                    $record, auth()->id(), PosCashMovement::TYPE_CASH_IN, (int) $data['amount'], $data['note'], $data['reference'] ?? null,
                )),
            Action::make('cashOut')->label(__('Salida de efectivo'))->icon('heroicon-o-arrow-up-circle')
                ->color('warning')->authorize('update')
                ->visible(fn (PosCashSession $record): bool => $record->isOpen())
                ->form([
                    TextInput::make('amount')->label(__('Importe en centavos'))->numeric()->integer()->minValue(1)->required(),
                    Textarea::make('note')->label(__('Motivo'))->required()->maxLength(1000),
                    TextInput::make('reference')->label(__('Referencia'))->maxLength(120),
                ])
                ->action(fn (PosCashSession $record, array $data) => app(PosCashSessionService::class)->recordCashMovement(
                    $record, auth()->id(), PosCashMovement::TYPE_CASH_OUT, (int) $data['amount'], $data['note'], $data['reference'] ?? null,
                )),
            Action::make('close')->label(__('Cerrar y arquear'))->icon('heroicon-o-lock-closed')->color('danger')
                ->requiresConfirmation()->authorize('update')
                ->visible(fn (PosCashSession $record): bool => $record->isOpen())
                ->form([
                    TextInput::make('counted_amount')->label(__('Efectivo contado en centavos'))->numeric()->integer()->minValue(0)->required(),
                    Textarea::make('note')->label(__('Observaciones del cierre'))->maxLength(2000),
                ])
                ->action(fn (PosCashSession $record, array $data) => app(PosCashSessionService::class)->close(
                    $record, auth()->id(), (int) $data['counted_amount'], $data['note'] ?? null,
                )),
        ])->headerActions([
            Action::make('open')->label(__('Abrir caja'))->icon('heroicon-o-lock-open')->authorize('create')
                ->form([
                    Select::make('pos_terminal_id')->label(__('Terminal'))
                        ->options(fn () => PosTerminal::query()->where('is_active', true)->whereHas('location', fn ($q) => $q->where('is_active', true))->whereHas('warehouse', fn ($q) => $q->where('is_active', true))->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                    Select::make('currency')->label(__('Moneda'))->options(['CUP' => 'CUP', 'USD' => 'USD', 'EUR' => 'EUR'])->required(),
                    TextInput::make('opening_amount')->label(__('Fondo inicial en centavos'))->numeric()->integer()->minValue(0)->required()->default(0),
                    Textarea::make('note')->label(__('Observaciones de apertura'))->maxLength(2000),
                ])
                ->action(fn (array $data) => app(PosCashSessionService::class)->open(
                    PosTerminal::query()->findOrFail($data['pos_terminal_id']),
                    auth()->id(),
                    $data['currency'],
                    (int) $data['opening_amount'],
                    $data['note'] ?? null,
                )),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPosCashSessions::route('/')];
    }
}