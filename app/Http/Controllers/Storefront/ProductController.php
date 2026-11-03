<?php

namespace App\\Http\\Controllers\\Storefront;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Brand;
use App\\Models\\Category;
use App\\Models\\Product;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:160'],
            'sort' => ['nullable', 'in:latest,price_asc,price_desc,name'],
        ]);

        $products = Product::query()
            ->published()
            ->with([
                'category',
                'brand',
                'images',
                'inventory',
                'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
            ])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))
            ->when($filters['brand'] ?? null, fn ($query, $slug) => $query->whereHas('brand', fn ($brand) => $brand->where('slug', $slug)))
            ->when(($filters['sort'] ?? 'latest') === 'price_asc', fn ($query) => $query->orderBy('price_amount'))
            ->when(($filters['sort'] ?? null) === 'price_desc', fn ($query) => $query->orderByDesc('price_amount'))
            ->when(($filters['sort'] ?? null) === 'name', fn ($query) => $query->orderBy('name'))
            ->when(($filters['sort'] ?? 'latest') === 'latest', fn ($query) => $query->latest())
            ->paginate(12)
            ->withQueryString();

        return view('storefront.products.index', [
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === Product::STATUS_ACTIVE && $product->is_visible, 404);

        $product->load([
            'category',
            'brand',
            'images',
            'inventory',
            'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
        ]);

        $relatedProducts = Product::query()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '<>', $product->id)
            ->with([
                'images',
                'brand',
                'inventory',
                'variants' => fn ($query) => $query->where('is_active', true)->with('inventory'),
            ])
            ->latest()
            ->limit(4)
            ->get();

        return view('storefront.products.show', compact('product', 'relatedProducts'));
    }
}
