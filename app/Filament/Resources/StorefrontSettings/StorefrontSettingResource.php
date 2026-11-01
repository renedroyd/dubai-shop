<?php

namespace App\Filament\Resources\StorefrontSettings;

use App\Filament\Resources\StorefrontSettings\Pages\CreateStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\EditStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\ListStorefrontSettings;
use App\Models\StorefrontSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use UnitEnum;

class StorefrontSettingResource extends Resource
{
    protected static ?string $model = StorefrontSetting::class;
    protected static string | UnitEnum | null $navigationGroup = 'Tienda';
    protected static ?string $navigationLabel = 'Contenido y textos';
    protected static ?string $modelLabel = 'configuración de contenido';
    protected static ?string $pluralModelLabel = 'contenido de la tienda';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Nombre del campo')->required()->maxLength(255),
            TextInput::make('key')->label('Clave interna')->required()->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Usa puntos para agrupar claves, por ejemplo home.hero_title.'),
            Select::make('group')->label('Sección')->options([
                'general' => 'General',
                'inicio' => 'Página de inicio',
                'beneficios' => 'Beneficios',
                'seo' => 'SEO',
            ])->required()->default('general'),
            Textarea::make('value')->label('Contenido')->rows(4)->columnSpanFull(),
            TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
            Toggle::make('is_public')->label('Visible en la tienda')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label('Campo')->searchable()->sortable(),
            TextColumn::make('key')->label('Clave')->searchable()->copyable(),
            TextColumn::make('group')->label('Sección')->badge(),
            TextColumn::make('value')->label('Contenido')->limit(60)->wrap(),
            IconColumn::make('is_public')->label('Público')->boolean(),
        ])->filters([
            SelectFilter::make('group')->options([
                'general' => 'General',
                'inicio' => 'Página de inicio',
                'beneficios' => 'Beneficios',
                'seo' => 'SEO',
            ]),
        ])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('Nuevo contenido')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontSettings::route('/'),
            'create' => CreateStorefrontSetting::route('/create'),
            'edit' => EditStorefrontSetting::route('/{record}/edit'),
        ];
    }
}
