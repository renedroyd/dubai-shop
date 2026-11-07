<?php

namespace App\Filament\Resources\PosCashSessions;

use App\Filament\Resources\PosCashSessions\Pages\ListPosCashSessions;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\PosTerminal;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosCashSessionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()->whereHas('terminal', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereIn('warehouse_id', $warehouseIds));
    }

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
            TextColumn::make('shift_label')->label(__('Turno'))->placeholder('—'),
            TextColumn::make('close_requested_at')->label(__('Cierre solicitado'))->dateTime(),
            TextColumn::make('closeRequester.name')->label(__('Solicitó cierre'))->placeholder('—'),
            TextColumn::make('closeApprover.name')->label(__('Aprobó cierre'))->placeholder('—'),
            TextColumn::make('currency')->label(__('Moneda')),
            TextColumn::make('opening_cash_amount')->label(__('Fondo inicial'))->formatStateUsing(fn ($state, PosCashSession $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('expected_cash_amount')->label(__('Efectivo esperado'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('counted_cash_amount')->label(__('Efectivo contado'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('difference_amount')->label(__('Diferencia'))->formatStateUsing(fn ($state, PosCashSession $record) => $state === null ? '—' : number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('status')->label(__('Estado'))->state(fn (PosCashSession $record): string => $record->close_requested_at !== null && $record->closed_at === null ? __('Pendiente de aprobación') : ($record->status === PosCashSession::STATUS_OPEN ? __('Abierta') : __('Cerrada')))->badge()->color(fn (string $state): string => $state === __('Pendiente de aprobación') ? 'warning' : ($state === __('Abierta') ? 'success' : 'gray')),
            TextColumn::make('opened_at')->label(__('Apertura'))->dateTime()->sortable(),
            TextColumn::make('closed_at')->label(__('Cierre'))->dateTime(),
        ])->filters([
            SelectFilter::make('status')->options([
                PosCashSession::STATUS_OPEN => __('Abierta'),
                PosCashSession::STATUS_CLOSED => __('Cerrada'),
            ]),
        ])->defaultSort('opened_at', 'desc')->recordActions([
            Action::make('sell')->label(__('Registrar venta'))->icon('heroicon-o-shopping-cart')->color('success')
                ->authorize('update')
                ->visible(fn (PosCashSession $record): bool => $record->isOpen())
                ->form([
                    Repeater::make('items')->label(__('Productos'))->schema([
                        Select::make('product_id')->label(__('Producto'))
                            ->options(fn () => Product::query()->where('status', Product::STATUS_ACTIVE)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->required(),
                        Select::make('product_variant_id')->label(__('Variante (si aplica)'))
                            ->options(fn () => ProductVariant::query()->where('is_active', true)->whereHas('product', fn ($query) => $query->where('status', Product::STATUS_ACTIVE))->with('product')->get()
                                ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->product->name.' — '.$variant->name]))
                            ->searchable()->nullable(),
                        TextInput::make('quantity')->label(__('Cantidad'))->numeric()->integer()->minValue(1)->required()->default(1),
                    ])->columns(2)->minItems(1)->required(),
                    Select::make('payment_method_id')->label(__('Método de cobro'))
                        ->options(fn () => PaymentMethod::query()->where('is_enabled', true)->where('driver', 'cash_on_delivery')->orderBy('sort_order')->pluck('name', 'id'))
                        ->required(),
                    TextInput::make('customer_name')->label(__('Cliente'))->maxLength(255),
                    TextInput::make('customer_phone')->label(__('Teléfono'))->maxLength(40),
                    TextInput::make('customer_email')->label(__('Correo'))->email()->maxLength(255),
                    Textarea::make('customer_note')->label(__('Nota'))->maxLength(1000),
                ])
                ->action(function (PosCashSession $record, array $data): void {
                    app(PosSaleService::class)->sell(
                        $record,
                        auth()->id(),
                        $data['items'],
                        (int) $data['payment_method_id'],
                        [
                            'name' => $data['customer_name'] ?? null,
                            'phone' => $data['customer_phone'] ?? null,
                            'email' => $data['customer_email'] ?? null,
                            'note' => $data['customer_note'] ?? null,
                        ],
                    );
                }),
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
            Action::make('close')->label(fn (PosCashSession $record): string => $record->terminal?->supervised_closing ? __('Solicitar cierre supervisado') : __('Cerrar y arquear'))->icon('heroicon-o-lock-closed')->color('danger')
                ->requiresConfirmation()->authorize('update')
                ->visible(fn (PosCashSession $record): bool => $record->isOpen())
                ->form([
                    TextInput::make('counted_amount')->label(__('Efectivo contado en centavos'))->numeric()->integer()->minValue(0)->required(),
                    Textarea::make('note')->label(__('Observaciones del cierre'))->maxLength(2000),
                ])
                ->action(fn (PosCashSession $record, array $data) => app(PosCashSessionService::class)->close(
                    $record, auth()->id(), (int) $data['counted_amount'], $data['note'] ?? null,
                )),
            Action::make('approveClose')->label(__('Aprobar cierre'))->icon('heroicon-o-shield-check')->color('success')
                ->requiresConfirmation()
                ->visible(fn (PosCashSession $record): bool => $record->close_requested_at !== null && $record->closed_at === null)
                ->authorize(fn (PosCashSession $record): bool => (auth()->user()?->hasAdminPermission('pos_cash_sessions.approve_close') ?? false)
                    && (auth()->user()?->hasWarehousePermission($record->terminal->warehouse_id, 'pos.operate') ?? false))
                ->action(fn (PosCashSession $record) => app(PosCashSessionService::class)->approveClose($record, auth()->id())),
        ])->headerActions([
            Action::make('open')->label(__('Abrir caja'))->icon('heroicon-o-lock-open')->authorize('create')
                ->form([
                    Select::make('pos_terminal_id')->label(__('Terminal'))
                        ->options(fn () => PosTerminal::query()->where('is_active', true)->whereHas('location', fn ($q) => $q->where('is_active', true))->whereHas('warehouse', fn ($q) => $q->where('is_active', true))->whereIn('warehouse_id', auth()->user()?->warehouseIdsWithPermission('pos.operate') ?? [])->whereDoesntHave('cashSessions', fn ($query) => $query->where('status', PosCashSession::STATUS_OPEN)->whereNull('closed_at'))->orderBy('name')->get()
                            ->filter(fn (PosTerminal $terminal): bool => empty($terminal->assigned_cashier_ids)
                                || in_array((int) auth()->id(), array_map('intval', $terminal->assigned_cashier_ids), true))
                            ->pluck('name', 'id'))
                        ->searchable()->required(),
                    Select::make('currency')->label(__('Moneda'))->options(['CUP' => 'CUP', 'USD' => 'USD', 'EUR' => 'EUR'])->required(),
                    TextInput::make('opening_amount')->label(__('Fondo inicial en centavos'))->numeric()->integer()->minValue(0)->required()->default(0),
                    Textarea::make('note')->label(__('Observaciones de apertura'))->maxLength(2000),
                    TextInput::make('shift_label')->label(__('Turno / jornada'))->maxLength(120),
                ])
                ->action(fn (array $data) => app(PosCashSessionService::class)->open(
                    PosTerminal::query()->findOrFail($data['pos_terminal_id']),
                    auth()->id(),
                    $data['currency'],
                    (int) $data['opening_amount'],
                    $data['note'] ?? null,
                    $data['shift_label'] ?? null,
                )),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPosCashSessions::route('/')];
    }
}