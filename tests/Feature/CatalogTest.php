<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_lists_published_visible_products(): void
    {
        $this->withoutVite();

        Product::create([
            'name' => 'Premium Headphones',
            'slug' => 'premium-headphones',
            'sku' => 'HEAD-001',
            'price_amount' => 12999,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        Product::create([
            'name' => 'Draft Product',
            'slug' => 'draft-product',
            'sku' => 'DRAFT-001',
            'price_amount' => 5000,
            'currency' => 'USD',
            'status' => Product::STATUS_DRAFT,
            'is_visible' => true,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Premium Headphones')
            ->assertDontSee('Draft Product');
    }

    public function test_catalog_can_be_filtered_by_category_and_search_term(): void
    {
        $this->withoutVite();

        $category = Category::create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Speaker',
            'slug' => 'wireless-speaker',
            'sku' => 'SPK-001',
            'price_amount' => 8999,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        Product::create([
            'name' => 'Desk Lamp',
            'slug' => 'desk-lamp',
            'sku' => 'LAMP-001',
            'price_amount' => 3999,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        $this->get(route('products.index', ['category' => 'tecnologia', 'q' => 'speaker']))
            ->assertOk()
            ->assertSee('Wireless Speaker')
            ->assertDontSee('Desk Lamp');
    }

    public function test_product_detail_is_public_only_when_product_is_published_and_visible(): void
    {
        $this->withoutVite();

        $product = Product::create([
            'name' => 'Signature Gift Set',
            'slug' => 'signature-gift-set',
            'sku' => 'GIFT-001',
            'price_amount' => 2599,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
            'description' => 'A thoughtful gift.',
        ]);

        $this->get(route('products.show', $product))->assertOk()->assertSee('Signature Gift Set');

        $product->update(['is_visible' => false]);

        $this->get(route('products.show', $product))->assertNotFound();
    }
}
