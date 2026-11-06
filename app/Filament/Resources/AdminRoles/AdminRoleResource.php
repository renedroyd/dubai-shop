<?php

namespace App\Filament\Resources\AdminRoles;

use App\Models\AdminRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;

class AdminRoleResource extends Resource
{
    protected static ?string $model = AdminRole::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Seguridad'); }
    public static function getNavigationLabel(): string { return __('Roles y permisos'); }
    public static function getModelLabel(): string { return __('rol'); }
    public static function getPluralModelLabel(): string { return __('roles'); }

    public static function canViewAny(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canCreate(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
    public static function canEdit(Model $record): bool { return (auth()->user()?->isSuperAdmin() ?? false) && $record->slug !== 'superadmin'; }
    public static function canDelete(Model $record): bool { return (auth()->user()?->isSuperAdmin() ?? false) && ! $record->is_system && $record->slug !== 'superadmin'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Nombre'))->required()->maxLength(120),
            TextInput::make('slug')->label(__('Identificador'))->required()->alphaDash()->unique(ignoreRecord: true)->maxLength(120),
            Textarea::make('description')->label(__('Descripción'))->rows(2)->maxLength(500),
            CheckboxList::make('permissions')
                ->label(__('Permisos asignados'))
                ->options(config('admin-permissions.permissions'))
                ->columns(2)
                ->searchable()
                ->bulkToggleable()
                ->default([])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Rol'))->searchable()->sortable(),
            TextColumn::make('slug')->label(__('Identificador'))->badge(),
            TextColumn::make('users_count')->counts('users')->label(__('Usuarios')),
            IconColumn::make('is_system')->label(__('Rol del sistema'))->boolean(),
        ])->recordActions([
            EditAction::make()->visible(fn (AdminRole $record): bool => $record->slug !== 'superadmin'),
            DeleteAction::make()->visible(fn (AdminRole $record): bool => ! $record->is_system && $record->slug !== 'superadmin')->requiresConfirmation(),
        ])->headerActions([CreateAction::make()->label(__('Nuevo rol'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminRoles::route('/'),
            'create' => Pages\CreateAdminRole::route('/create'),
            'edit' => Pages\EditAdminRole::route('/{record}/edit'),
        ];
    }
}
