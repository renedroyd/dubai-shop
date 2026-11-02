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
    protected static string | UnitEnum | null $navigationGroup = 'Tienda';
    protected static ?string $navigationLabel = 'Banners y campañas';
    protected static ?string $modelLabel = 'banner';
    protected static ?string $pluralModelLabel = 'banners';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido')->columns(2)->schema([
                TextInput::make('title')->label('Título interno')->required()->maxLength(255),
                TextInput::make('eyebrow')->label('Texto superior')->maxLength(255),
                Textarea::make('description')->label('Descripción')->rows(3)->columnSpanFull(),
                FileUpload::make('image_path')->label('Imagen')->image()->disk('public')->directory('storefront/banners')->required(),
                TextInput::make('image_alt')->label('Texto alternativo')->maxLength(255),
                TextInput::make('link_url')->label('Enlace')->url()->maxLength(2048),
                TextInput::make('link_label')->label('Texto del botón')->maxLength(80),
            ]),
            Section::make('Publicación')->columns(2)->schema([
                Select::make('placement')->label('Ubicación')->options(['home' => 'Página de inicio', 'campaign' => 'Campaña'])->required()->default('home'),
                Toggle::make('is_active')->label('Activo')->default(false),
                DateTimePicker::make('starts_at')->label('Mostrar desde'),
                DateTimePicker::make('ends_at')->label('Mostrar hasta')->afterOrEqual('starts_at'),
                TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Banner')->searchable()->sortable(),
            TextColumn::make('placement')->label('Ubicación')->badge(),
            IconColumn::make('is_active')->label('Activo')->boolean(),
            TextColumn::make('starts_at')->label('Desde')->dateTime(),
            TextColumn::make('ends_at')->label('Hasta')->dateTime(),
        ])->filters([SelectFilter::make('placement')->options(['home' => 'Inicio', 'campaign' => 'Campaña'])])
            ->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('Nuevo banner')]);
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
