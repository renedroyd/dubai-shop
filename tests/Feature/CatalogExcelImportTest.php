<?php
namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogImport\CatalogExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_categories_and_brands_from_csv_files(): void
    {
        $categories = $this->csvFile("name,slug,parent_slug,description,is_active,sort_order\nElectrónica,electronica,,Catálogo,1,1\nAudio,audio,electronica,Sonido,1,2\n");
        $brands = $this->csvFile("name,slug,description,is_active\nMarca Uno,marca-uno,Primera marca,1\n");

        $categoryResult = app(CatalogExcelImporter::class)->import('categories', $categories);
        $brandResult = app(CatalogExcelImporter::class)->import('brands', $brands);

        $this->assertSame(2, $categoryResult['created']);
        $this->assertSame(1, $brandResult['created']);
        $this->assertSame([], $categoryResult['errors']);
        $this->assertSame(Category::where('slug', 'electronica')->value('id'), Category::where('slug', 'audio')->value('parent_id'));
        $this->assertDatabaseHas('brands', ['slug' => 'marca-uno', 'name' => 'Marca Uno']);
    }

    public function test_it_imports_products_with_relations_and_decimal_prices(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $brand = Brand::create(['name' => 'Marca Uno', 'slug' => 'marca-uno']);
        $file = $this->csvFile("name,slug,sku,category_slug,brand_slug,price,compare_at_price,currency,status,is_visible,is_featured\nAuriculares,auriculares,AU-001,audio,marca-uno,19.99,24.99,USD,active,1,0\n");

        $result = app(CatalogExcelImporter::class)->import('products', $file);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['errors']);
        $this->assertDatabaseHas('products', [
            'sku' => 'AU-001', 'category_id' => $category->id, 'brand_id' => $brand->id,
            'price_amount' => 1999, 'compare_at_price_amount' => 2499, 'status' => Product::STATUS_ACTIVE,
        ]);
    }

    public function test_it_can_skip_or_update_existing_products_by_sku(): void
    {
        $product = Product::create([
            'name' => 'Nombre anterior', 'slug' => 'nombre-anterior', 'sku' => 'SKU-100',
            'price_amount' => 1000, 'currency' => 'USD', 'status' => Product::STATUS_DRAFT,
        ]);
        $file = $this->csvFile("name,slug,sku,price,currency,status\nNombre nuevo,nombre-nuevo,SKU-100,25.00,USD,active\n");

        $skip = app(CatalogExcelImporter::class)->import('products', $file, 'skip');
        $this->assertSame(1, $skip['skipped']);
        $this->assertSame('Nombre anterior', $product->fresh()->name);

        $update = app(CatalogExcelImporter::class)->import('products', $file, 'update');
        $this->assertSame(1, $update['updated']);
        $this->assertSame('Nombre nuevo', $product->fresh()->name);
        $this->assertSame(2500, $product->fresh()->price_amount);
    }

    public function test_invalid_rows_are_reported_without_aborting_valid_rows(): void
    {
        $file = $this->csvFile("name,slug,sku,price,currency\nProducto válido,producto-valido,OK-1,10.00,USD\nProducto inválido,producto-invalido,ERR-1,precio,USD\n");
        $result = app(CatalogExcelImporter::class)->import('products', $file);

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Fila 3', $result['errors'][0]);
        $this->assertDatabaseHas('products', ['sku' => 'OK-1']);
        $this->assertDatabaseMissing('products', ['sku' => 'ERR-1']);
    }

    public function test_non_admin_users_cannot_access_the_catalog_import_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/products')->assertForbidden();
    }

    private function csvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-import-').'.csv';
        file_put_contents($path, $contents);
        return $path;
    }
}
