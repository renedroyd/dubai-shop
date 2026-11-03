<?php

namespace App\Filament\Resources\StorefrontBanners;

use App\Models\StorefrontBanner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use UnitEnum;

class StorefrontBannerResource extends Resource
{
    protected static ?string $model = StorefrontBanner::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Tienda'); }
    public static function getNavigationLabel(): string { return __('Banners y campañas'); }
    public static function getModelLabel(): string { return __('banner'); }
    public static function getPluralModelLabel(): string { return __('banners'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido')->columns(2)->schema([
                TextInput::make('title')->label(__('Título interno'))->required()->maxLength(255),
                TextInput::make('eyebrow')->label(__('Texto superior'))->maxLength(255),
                Textarea::make('description')->label(__('Descripción'))->rows(3)->columnSpanFull(),
                FileUpload::make('image_path')->label(__('Imagen'))->image()->disk('public')->directory('storefront/banners')->required(),
                TextInput::make('image_alt')->label(__('Texto alternativo'))->maxLength(255),
                TextInput::make('link_url')->label(__('Enlace'))->url()->maxLength(2048),
                TextInput::make('link_label')->label(__('Texto del botón'))->maxLength(80),
            ]),
            Section::make('Publicación')->columns(2)->schema([
                Select::make('placement')->label(__('Ubicación'))->options(['home' => 'Página de inicio', 'campaign' => 'Campaña'])->required()->default('home'),
                Toggle::make('is_active')->label(__('Activo'))->default(false),
                DateTimePicker::make('starts_at')->label(__('Mostrar desde')),
                DateTimePicker::make('ends_at')->label(__('Mostrar hasta'))->afterOrEqual('starts_at'),
                TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('Banner'))->searchable()->sortable(),
            TextColumn::make('placement')->label(__('Ubicación'))->badge(),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
            TextColumn::make('starts_at')->label(__('Desde'))->dateTime(),
            TextColumn::make('ends_at')->label(__('Hasta'))->dateTime(),
        ])->filters([SelectFilter::make('placement')->options(['home' => 'Inicio', 'campaign' => 'Campaña'])])
            ->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label(__('Nuevo banner'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorefrontBanners::route('/'),
            'create' => Pages\CreateStorefrontBanner::route('/create'),
            'edit' => Pages\EditStorefrontBanner::route('/{record}/edit'),
        ];
    }
}
