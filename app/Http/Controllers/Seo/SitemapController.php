<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('products.index'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.9'],
        ]);

        $urls = $urls->concat(Product::query()->published()->get(['slug', 'updated_at'])->map(fn (Product $product): array => [
            'loc' => route('products.show', $product->slug),
            'lastmod' => $product->updated_at?->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ]));

        $urls = $urls->concat(Category::query()->where('is_active', true)->get(['slug', 'updated_at'])->map(fn (Category $category): array => [
            'loc' => route('products.index', ['category' => $category->slug]),
            'lastmod' => $category->updated_at?->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.6',
        ]));

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
