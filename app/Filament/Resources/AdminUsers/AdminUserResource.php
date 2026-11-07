<?php

namespace App\Filament\Resources\AdminUsers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Seguridad'); }
    public static function getNavigationLabel(): string { return __('Acceso de usuarios'); }
    public static function getModelLabel(): string { return __('usuario'); }
    public static function getPluralModelLabel(): string { return __('usuarios'); }

    public static function canViewAny(): bool { return auth()->user()?->canManageAdminAccess() ?? false; }
    public static function canCreate(): bool { return auth()->user()?->canManageAdminAccess() ?? false; }
    public static function canEdit(Model $record): bool { return (auth()->user()?->canManageAdminAccess() ?? false) && ! $record->isSuperAdmin(); }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
            TextInput::make('email')->label(__('Correo electrónico'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label(__('Contraseña temporal'))
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(12)
                ->confirmed()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->helperText(__('Define una contraseña inicial de al menos 12 caracteres y entrégala al usuario por un canal seguro.')),
            TextInput::make('password_confirmation')
                ->label(__('Confirmar contraseña'))
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(false)
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Toggle::make('admin_access_enabled')
                ->label(__('Puede acceder al panel administrativo'))
                ->default(false)
                ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false),
            Select::make('roles')
                ->label(__('Roles'))
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
                ->helperText(__('Un usuario puede tener varios roles. Los permisos se combinan.')),
            Repeater::make('warehouseAccesses')
                ->relationship()
                ->label(__('Acceso por almacén'))
                ->schema([
                    Select::make('warehouse_id')
                        ->label(__('Almacén'))
                        ->options(fn () => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required()->distinct(),
                    CheckboxList::make('permissions')
                        ->label(__('Permisos en este almacén'))
                        ->options([
                            'view' => __('Consultar'),
                            'inventory.manage' => __('Gestionar inventario'),
                            'transfers.request' => __('Solicitar transferencias'),
                            'transfers.approve' => __('Aprobar transferencias'),
                            'pos.operate' => __('Operar cajas POS'),
                            'reports.view' => __('Consultar informes'),
                            'warehouse.manage' => __('Administrar almacén y ubicaciones'),
                        ])
                        ->columns(2)->required(),
                ])
                ->columns(1)->defaultItems(0)
                ->addActionLabel(__('Asignar almacén'))
                ->helperText(__('Los permisos se aplican únicamente al almacén asignado. El Superadministrador conserva acceso global.')),
            Repeater::make('directPermissions')
                ->relationship()
                ->label(__('Permisos individuales'))
                ->schema([
                    Select::make('permission')
                        ->label(__('Permiso'))
                        ->options(config('admin-permissions.permissions'))
                        ->searchable()
                        ->required(),
                    Toggle::make('allowed')
                        ->label(__('Conceder permiso'))
                        ->default(true),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel(__('Agregar excepción'))
                ->helperText(__('Una excepción individual prevalece sobre los permisos heredados de los roles.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Usuario'))->searchable()->sortable(),
            TextColumn::make('email')->label(__('Correo'))->searchable(),
            IconColumn::make('admin_access_enabled')->label(__('Acceso al panel'))->boolean(),
            TextColumn::make('roles.name')->label(__('Roles'))->badge(),\n            TextColumn::make('warehouseAccesses.warehouse.name')->label(__('Almacenes autorizados'))->badge(),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label(__('Registrar usuario'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }
}
