<?php
namespace App\Filament\Actions;

use App\Services\CatalogImport\CatalogExcelImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CatalogImportActions
{
    public static function make(string $type): array
    {
        $templates = [
            'categories' => [
                ['name', 'slug', 'parent_slug', 'description', 'is_active', 'sort_order'],
                ['Ejemplo de categoría', 'ejemplo-de-categoria', '', 'Descripción opcional', '1', '0'],
            ],
            'brands' => [
                ['name', 'slug', 'description', 'is_active'],
                ['Ejemplo de marca', 'ejemplo-de-marca', 'Descripción opcional', '1'],
            ],
            'products' => [
                ['name', 'slug', 'sku', 'category_slug', 'brand_slug', 'price', 'compare_at_price', 'currency', 'status', 'is_visible', 'is_featured', 'short_description', 'description', 'meta_title', 'meta_description'],
                ['Producto de ejemplo', 'producto-de-ejemplo', 'SKU-001', 'ejemplo-de-categoria', 'ejemplo-de-marca', '19.99', '24.99', 'USD', 'draft', '1', '0', 'Descripción corta', 'Descripción completa', 'Título SEO', 'Descripción SEO'],
            ],
        ];

        return [
            Action::make('importExcel')
                ->label(__('Importar desde Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading(__('Importar registros del catálogo'))
                ->modalDescription(__('Sube un archivo XLSX o CSV. Las columnas deben coincidir con la plantilla descargable.'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('Archivo Excel o CSV'))
                        ->disk('local')
                        ->directory('catalog-imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/csv',
                        ])
                        ->maxSize(10240)
                        ->required(),
                    Select::make('conflict_mode')
                        ->label(__('Si el registro ya existe'))
                        ->options([
                            'update' => __('Actualizar el registro existente'),
                            'skip' => __('Omitir el registro existente'),
                        ])
                        ->default('update')
                        ->required(),
                ])
                ->action(function (array $data) use ($type): void {
                    $path = $data['file'];

                    try {
                        $absolutePath = Storage::disk('local')->path($path);
                        $result = app(CatalogExcelImporter::class)->import($type, $absolutePath, $data['conflict_mode']);
                        $body = __('Creados: :created · Actualizados: :updated · Omitidos: :skipped', $result);

                        if ($result['errors'] !== []) {
                            $body .= ' · '.__('Errores: :count', ['count' => count($result['errors'])]);
                            $body .= "\n".implode("\n", array_slice($result['errors'], 0, 5));
                            Notification::make()->title(__('Importación completada con observaciones'))->body($body)->warning()->persistent()->send();
                        } else {
                            Notification::make()->title(__('Importación completada'))->body($body)->success()->send();
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title(__('No se pudo completar la importación'))->body($exception->getMessage())->danger()->persistent()->send();
                    } finally {
                        Storage::disk('local')->delete($path);
                    }
                }),
            Action::make('downloadImportTemplate')
                ->label(__('Descargar plantilla'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () use ($type, $templates) {
                    [$headers, $sample] = $templates[$type];

                    return response()->streamDownload(function () use ($headers, $sample): void {
                        $stream = fopen('php://output', 'wb');
                        fwrite($stream, "\xEF\xBB\xBF");
                        fputcsv($stream, $headers);
                        fputcsv($stream, $sample);
                        fclose($stream);
                    }, $type.'-plantilla.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
