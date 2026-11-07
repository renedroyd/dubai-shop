<?php

namespace App\Filament\Resources\PosPriceRules;

use App\Filament\Resources\PosPriceRules\Pages\CreatePosPriceRule;
use App\Filament\Resources\PosPriceRules\Pages\EditPosPriceRule;
use App\Filament\Resources\PosPriceRules\Pages\ListPosPriceRules;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PosPriceRule;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosPriceRuleResource extends Resource
{
    protected static ?string $model = PosPriceRule::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Precios y promociones'); }
    public static function getModelLabel(): string { return __('regla de precio'); }
    public static function getPluralModelLabel(): string { return __('reglas de precios'); }

    public static function canViewAny(): bool { return auth()->user()?->hasAdminPermission('pos_price_rules.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->hasAdminPermission('pos_price_rules.create') ?? false; }
    public static function canEdit($record): bool { return auth()->user()?->hasAdminPermission('pos_price_rules.update') ?? false; }
    public static function canDelete($record): bool { return auth()->user()?->hasAdminPermission('pos_price_rules.delete') ?? false; }

    public static function getEloquentQuery(): Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];
        return parent::getEloquentQuery()->where(fn (Builder $query) => $query->whereNull('warehouse_id')->orWhereIn('warehouse_id', $warehouseIds));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Regla comercial'))->columns(2)->schema([
                TextInput::make('name')->label(__('Nombre interno'))->required()->maxLength(160),
                Select::make('scope')->label(__('Aplicar a'))->options([
                    PosPriceRule::SCOPE_PRODUCT => __('Producto'), PosPriceRule::SCOPE_CATEGORY => __('Categoría'),
                    PosPriceRule::SCOPE_BRAND => __('Marca'), PosPriceRule::SCOPE_ORDER => __('Venta completa'),
                ])->live()->required(),
                Select::make('target_id')->label(__('Elemento objetivo'))
                    ->options(fn (callable $get): array => match ($get('scope')) {
                        PosPriceRule::SCOPE_PRODUCT => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
                        PosPriceRule::SCOPE_CATEGORY => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
                        PosPriceRule::SCOPE_BRAND => Brand::query()->orderBy('name')->pluck('name', 'id')->all(),
                        default => [],
                    })
                    ->searchable()->visible(fn (callable $get): bool => $get('scope') !== PosPriceRule::SCOPE_ORDER)
                    ->required(fn (callable $get): bool => $get('scope') !== PosPriceRule::SCOPE_ORDER),
                Select::make('customer_id')->label(__('Cliente específico (opcional)'))
                    ->options(fn (): array => User::query()->where('is_admin', false)->where('admin_access_enabled', false)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload(),
                Select::make('warehouse_id')->label(__('Almacén (vacío = todos)'))
                    ->options(fn (): array => Warehouse::query()->whereIn('id', auth()->user()?->warehouseIdsWithPermission('inventory.manage') ?? [])->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->placeholder(__('Todos los almacenes')),
                Select::make('currency')->label(__('Moneda (vacío = todas)'))
                    ->options(['CUP' => 'CUP', 'USD' => 'USD', 'EUR' => 'EUR'])->placeholder(__('Todas las monedas'))
                    ->required(fn (callable $get): bool => in_array($get('rule_type'), [PosPriceRule::TYPE_FIXED, PosPriceRule::TYPE_SPECIAL_PRICE], true)),
                Select::make('rule_type')->label(__('Tipo de regla'))->live()
                    ->options(fn (callable $get): array => $get('scope') === PosPriceRule::SCOPE_ORDER
                        ? [PosPriceRule::TYPE_PERCENTAGE => __('Descuento porcentual'), PosPriceRule::TYPE_FIXED => __('Descuento fijo')]
                        : [PosPriceRule::TYPE_PERCENTAGE => __('Descuento porcentual'), PosPriceRule::TYPE_FIXED => __('Descuento fijo por unidad'), PosPriceRule::TYPE_SPECIAL_PRICE => __('Precio especial por unidad')])
                    ->required(),
                TextInput::make('value_amount')->label(__('Valor'))->numeric()->integer()->minValue(1)
                    ->maxValue(fn (callable $get): ?int => $get('rule_type') === PosPriceRule::TYPE_PERCENTAGE ? 10000 : null)
                    ->required()
                    ->helperText(fn (callable $get): string => match ($get('rule_type')) {
                        PosPriceRule::TYPE_PERCENTAGE => __('Porcentaje en puntos básicos: 1500 = 15 %. Máximo 10000 = 100 %.'),
                        PosPriceRule::TYPE_FIXED => __('Importe fijo en centavos; por unidad o por venta completa según el alcance.'),
                        PosPriceRule::TYPE_SPECIAL_PRICE => __('Precio final por unidad en centavos.'),
                        default => __('Introduce el valor de la regla.'),
                    }),
                TextInput::make('priority')->label(__('Prioridad'))->numeric()->integer()->default(0)->required(),
                DateTimePicker::make('starts_at')->label(__('Válida desde')),
                DateTimePicker::make('ends_at')->label(__('Válida hasta')),
                Toggle::make('requires_approval')->label(__('Requiere autorización de supervisor'))->default(false),
                Toggle::make('is_active')->label(__('Activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Regla'))->searchable()->sortable(),
            TextColumn::make('scope')->label(__('Alcance'))->badge(),
            TextColumn::make('rule_type')->label(__('Tipo'))->badge(),
            TextColumn::make('value_amount')->label(__('Valor'))->state(fn (PosPriceRule $record): string => $record->rule_type === PosPriceRule::TYPE_PERCENTAGE
                ? number_format($record->value_amount / 100, 2).'%'
                : number_format($record->value_amount / 100, 2).' '.($record->currency ?? '')),
            TextColumn::make('warehouse.name')->label(__('Almacén'))->placeholder(__('Todos')),
            TextColumn::make('priority')->label(__('Prioridad'))->sortable(),
            IconColumn::make('requires_approval')->label(__('Requiere aprobación'))->boolean(),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
            TextColumn::make('starts_at')->label(__('Desde'))->dateTime(),
            TextColumn::make('ends_at')->label(__('Hasta'))->dateTime(),
        ])->filters([
            SelectFilter::make('scope')->label(__('Alcance'))->options([
                PosPriceRule::SCOPE_PRODUCT => __('Producto'), PosPriceRule::SCOPE_CATEGORY => __('Categoría'),
                PosPriceRule::SCOPE_BRAND => __('Marca'), PosPriceRule::SCOPE_ORDER => __('Venta completa'),
            ]),
        ])->recordActions([
            EditAction::make()->visible(fn (PosPriceRule $record): bool => static::canEdit($record)),
            DeleteAction::make()->requiresConfirmation()->visible(fn (PosPriceRule $record): bool => static::canDelete($record)),
        ])->headerActions([CreateAction::make()->label(__('Nueva regla'))->visible(fn (): bool => static::canCreate())]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosPriceRules::route('/'),
            'create' => CreatePosPriceRule::route('/create'),
            'edit' => EditPosPriceRule::route('/{record}/edit'),
        ];
    }
}
