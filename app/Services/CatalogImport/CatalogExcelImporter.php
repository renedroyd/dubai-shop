<?php
namespace App\Services\CatalogImport;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CatalogExcelImporter
{
    public function __construct(private readonly ExcelFileReader $reader) {}

    public function import(string $type, string $path, string $conflictMode = 'update'): array
    {
        if (! in_array($type, ['categories', 'brands', 'products'], true)) throw new RuntimeException('Tipo de catálogo no válido.');
        if (! in_array($conflictMode, ['update', 'skip'], true)) throw new RuntimeException('Modo de resolución de duplicados no válido.');

        $rows = $this->reader->read($path);
        if ($rows === []) throw new RuntimeException('El archivo no contiene filas para importar.');

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $seen = [];

        foreach ($rows as $entry) {
            $row = $entry['row'];
            $data = $entry['data'];

            try {
                $this->validateRequiredColumns($type, $data);
                $key = $type === 'products' ? strtoupper($data['sku']) : Str::slug($data['slug'] ?: $data['name']);
                if (isset($seen[$key])) throw new RuntimeException("El identificador '{$key}' está repetido dentro del archivo.");
                $seen[$key] = true;

                DB::transaction(function () use ($type, $data, $conflictMode, &$result): void {
                    match ($type) {
                        'categories' => $this->importCategory($data, $conflictMode, $result),
                        'brands' => $this->importBrand($data, $conflictMode, $result),
                        'products' => $this->importProduct($data, $conflictMode, $result),
                    };
                });
            } catch (Throwable $exception) {
                $result['errors'][] = "Fila {$row}: ".$exception->getMessage();
            }
        }

        return $result;
    }

    private function validateRequiredColumns(string $type, array $data): void
    {
        $required = match ($type) {
            'categories', 'brands' => ['name'],
            'products' => ['name', 'sku', 'price'],
        };

        foreach ($required as $column) {
            if (! isset($data[$column]) || trim($data[$column]) === '') throw new RuntimeException("Falta el valor obligatorio de la columna '{$column}'.");
        }
    }

    private function importCategory(array $data, string $mode, array &$result): void
    {
        $name = trim($data['name']);
        $slug = $this->slug($data['slug'] ?? '', $name);
        $record = Category::query()->where('slug', $slug)->first();
        if ($record && $mode === 'skip') { $result['skipped']++; return; }

        $created = ! $record;
        $record ??= new Category();
        $record->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
            'is_active' => $this->boolean($data['is_active'] ?? '1'),
            'sort_order' => $this->integer($data['sort_order'] ?? '0'),
            'parent_id' => null,
        ]);
        $record->save();

        $parentSlug = trim($data['parent_slug'] ?? '');
        if ($parentSlug !== '') {
            $parent = Category::query()->where('slug', $parentSlug)->first();
            if (! $parent || $parent->is($record)) throw new RuntimeException("La categoría superior '{$parentSlug}' no existe o no es válida.");
            $record->parent_id = $parent->id;
            $record->save();
        }

        $result[$created ? 'created' : 'updated']++;
    }

    private function importBrand(array $data, string $mode, array &$result): void
    {
        $name = trim($data['name']);
        $slug = $this->slug($data['slug'] ?? '', $name);
        $record = Brand::query()->where('slug', $slug)->first();
        if ($record && $mode === 'skip') { $result['skipped']++; return; }

        $created = ! $record;
        $record ??= new Brand();
        $record->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
            'is_active' => $this->boolean($data['is_active'] ?? '1'),
        ]);
        $record->save();
        $result[$created ? 'created' : 'updated']++;
    }

    private function importProduct(array $data, string $mode, array &$result): void
    {
        $sku = trim($data['sku']);
        $record = Product::query()->where('sku', $sku)->first();
        if ($record && $mode === 'skip') { $result['skipped']++; return; }

        $currency = strtoupper(trim($data['currency'] ?? 'USD'));
        if (! in_array($currency, ['USD', 'CUP', 'EUR'], true)) throw new RuntimeException("La moneda '{$currency}' no está admitida. Usa USD, CUP o EUR.");

        $status = strtolower(trim($data['status'] ?? Product::STATUS_DRAFT));
        if (! in_array($status, [Product::STATUS_DRAFT, Product::STATUS_ACTIVE, Product::STATUS_ARCHIVED], true)) throw new RuntimeException("El estado '{$status}' no es válido.");

        $categoryId = $this->relatedId(Category::class, $data['category_slug'] ?? '', 'categoría');
        $brandId = $this->relatedId(Brand::class, $data['brand_slug'] ?? '', 'marca');
        $name = trim($data['name']);
        $created = ! $record;
        $record ??= new Product();
        $compareAt = $this->nullable($data['compare_at_price'] ?? null);

        $record->fill([
            'name' => $name,
            'slug' => $this->slug($data['slug'] ?? '', $name),
            'sku' => $sku,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'short_description' => $this->nullable($data['short_description'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'price_amount' => $this->moneyToCents($data['price']),
            'compare_at_price_amount' => $compareAt === null ? null : $this->moneyToCents($compareAt),
            'currency' => $currency,
            'status' => $status,
            'is_visible' => $this->boolean($data['is_visible'] ?? '1'),
            'is_featured' => $this->boolean($data['is_featured'] ?? '0'),
            'meta_title' => $this->nullable($data['meta_title'] ?? null),
            'meta_description' => $this->nullable($data['meta_description'] ?? null),
        ]);
        $record->save();
        $result[$created ? 'created' : 'updated']++;
    }

    private function relatedId(string $model, string $slug, string $label): ?int
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        $record = $model::query()->where('slug', $slug)->first();
        if (! $record) throw new RuntimeException("No existe la {$label} con slug '{$slug}'. Importa primero ese registro.");
        return $record->id;
    }

    private function slug(string $slug, string $name): string
    {
        $slug = Str::slug(trim($slug) !== '' ? $slug : $name);
        if ($slug === '') throw new RuntimeException('No fue posible generar un slug válido.');
        return $slug;
    }

    private function moneyToCents(string $value): int
    {
        $value = trim(str_replace(',', '', $value));
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) throw new RuntimeException("El importe '{$value}' no es válido. Usa el formato 19.99.");
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'si', 'sí', 'activo', 'active'], true);
    }

    private function integer(string $value): int
    {
        if (! preg_match('/^\d+$/', trim($value))) throw new RuntimeException("El valor '{$value}' debe ser un número entero no negativo.");
        return (int) $value;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
