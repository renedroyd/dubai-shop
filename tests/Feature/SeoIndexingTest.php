<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug, string $status = Product::STATUS_ACTIVE): Product
    {
        return Product::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'sku' => strtoupper(str_replace('-', '', $slug)),
            'price_amount' => 1999,
            'currency' => 'USD',
            'status' => $status,
            'is_visible' => true,
            'short_description' => 'Descripción para buscadores.',
        ]);
    }

    public function test_sitemap_lists_home_catalog_and_published_products_only(): void
    {
        $this->product('producto-publicado');
        $this->product('producto-borrador', Product::STATUS_DRAFT);

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'))
            ->assertSee(route('products.index'))
            ->assertSee(route('products.show', 'producto-publicado'))
            ->assertDontSee('producto-borrador');
    }

    public function test_robots_txt_declares_the_dynamic_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee(route('sitemap'));
    }

    public function test_product_page_exposes_canonical_open_graph_and_product_structured_data(): void
    {
        $this->withoutVite();
        $product = $this->product('producto-seo');

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee(route('products.show', $product), false)
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"sku":"PRODUCTOSEO"', false);
    }
}
