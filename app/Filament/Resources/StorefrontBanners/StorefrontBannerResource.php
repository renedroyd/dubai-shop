<?php

namespace App\Filament\Resources\StorefrontBanners;

use App\Models\StorefrontBanner;
use App\Support\Storefront\BannerPromptGenerator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Js;

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
            Section::make(__('Crear imagen con IA'))
                ->description(__('Prepara un prompt con la identidad visual de Clásico Dubai. No requiere API ni pagos: copia el prompt en ChatGPT, genera la imagen y súbela en el campo de imagen de abajo.'))
                ->icon('heroicon-o-sparkles')
                ->headerActions([
                    Action::make('prepareAiPrompt')
                        ->label(__('Preparar prompt'))
                        ->icon('heroicon-o-sparkles')
                        ->modalHeading(__('Asistente gratuito de banners con IA'))
                        ->modalDescription(__('Completa los detalles de la campaña. Te entregaremos un prompt listo para copiar en ChatGPT.'))
                        ->form([
                            Select::make('campaign_type')
                                ->label(__('Tipo de campaña'))
                                ->options([
                                    'promotion' => __('Promoción'),
                                    'combos' => __('Combos y paquetes'),
                                    'products' => __('Productos'),
                                    'remittances' => __('Remesas'),
                                    'seasonal' => __('Temporada o fecha especial'),
                                    'brand' => __('Imagen institucional'),
                                ])
                                ->default('promotion')
                                ->required(),
                            Select::make('visual_style')
                                ->label(__('Estilo visual'))
                                ->options([
                                    'luxury' => __('Editorial de lujo'),
                                    'commercial' => __('Fotografía publicitaria realista'),
                                    'minimal' => __('Minimalista contemporáneo'),
                                    'cinematic' => __('Cinematográfico'),
                                ])
                                ->default('luxury')
                                ->required(),
                            Select::make('format')
                                ->label(__('Formato de imagen'))
                                ->options([
                                    'wide' => __('Banner panorámico 2.4:1 (recomendado)'),
                                    'landscape' => __('Horizontal 16:9'),
                                    'square' => __('Cuadrado 1:1'),
                                    'portrait' => __('Vertical 4:5'),
                                ])
                                ->default('wide')
                                ->required(),
                            Textarea::make('brief')
                                ->label(__('Describe la imagen que necesitas'))
                                ->placeholder(__('Ej.: composición elegante de un combo familiar con productos organizados sobre una superficie oscura y detalles dorados.'))
                                ->rows(4)
                                ->maxLength(1200)
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $prompt = app(BannerPromptGenerator::class)->generate($data);

                            Notification::make()
                                ->title(__('Tu prompt está listo'))
                                ->body($prompt)
                                ->persistent()
                                ->actions([
                                    NotificationAction::make('copyPrompt')
                                        ->label(__('Copiar prompt'))
                                        ->extraAttributes([
                                            'x-on:click.prevent' => 'navigator.clipboard.writeText('.Js::from($prompt).')',
                                        ]),
                                    NotificationAction::make('openChatGpt')
                                        ->label(__('Abrir ChatGPT'))
                                        ->url('https://chatgpt.com')
                                        ->openUrlInNewTab(),
                                ])
                                ->send();
                        }),
                ]),
            Section::make(__('Contenido del banner'))
                ->description(__('La imagen se utiliza como fondo y el contenido se muestra superpuesto.'))
                ->columns(2)->schema([
                    TextInput::make('title')->label(__('Título interno'))->required()->maxLength(255),
                    TextInput::make('eyebrow')->label(__('Texto superior'))->maxLength(255),
                    Textarea::make('description')->label(__('Descripción'))->rows(3)->columnSpanFull(),
                    FileUpload::make('image_path')->label(__('Imagen de fondo'))->image()->maxSize(5120)->disk('public')->directory('storefront/banners')->helperText(__('Sube aquí la imagen descargada de ChatGPT. Se mostrará detrás del título, la descripción y el botón.'))->required(),
                    TextInput::make('image_alt')->label(__('Texto alternativo'))->maxLength(255),
                    TextInput::make('link_url')->label(__('Enlace'))->url()->maxLength(2048),
                    TextInput::make('link_label')->label(__('Texto del botón'))->maxLength(80),
                ]),
            Section::make(__('Publicación'))->columns(2)->schema([
                Select::make('placement')->label(__('Ubicación'))->options(['home' => __('Página de inicio'), 'campaign' => __('Campaña')])->helperText(__('Define en qué espacio de la tienda se publicará el banner.'))->required()->default('home'),
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
            ImageColumn::make('image_path')->label(__('Vista previa'))->disk('public')->height(64)->width(112)->extraImgAttributes(['class' => 'rounded-md object-cover']),
            TextColumn::make('title')->label(__('Banner'))->searchable()->sortable(),
            TextColumn::make('placement')->label(__('Ubicación'))->badge(),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
            TextColumn::make('starts_at')->label(__('Desde'))->dateTime(),
            TextColumn::make('ends_at')->label(__('Hasta'))->dateTime(),
        ])->filters([SelectFilter::make('placement')->options(['home' => __('Inicio'), 'campaign' => __('Campaña')])])
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
