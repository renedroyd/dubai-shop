<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocialPublicatorCatalogController extends Controller
{
    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ]);

        $products = Product::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'images',
                'inventories.warehouse',
                'variants.inventories.warehouse',
            ])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->cursorPaginate(
                (int) ($validated['limit'] ?? 50),
                ['*'],
                'cursor',
                $validated['cursor'] ?? null,
            );

        $warehouses = $this->eligibleWarehouses();
        $items = $products->getCollection()
            ->flatMap(fn (Product $product): Collection => $this->itemsForProduct($product, $warehouses))
            ->values();

        return response()->json([
            'contract_version' => 'v1',
            'data' => $items,
            'next_cursor' => $products->nextCursor()?->encode(),
            'has_more' => $products->hasMorePages(),
        ]);
    }

    public function show(string $externalId): JsonResponse
    {
        $warehouses = $this->eligibleWarehouses();

        $variant = ProductVariant::query()
            ->where('external_id', $externalId)
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query->published())
            ->with([
                'inventories.warehouse',
                'product' => fn (Builder $query) => $query->with([
                    'category:id,name,slug',
                    'brand:id,name,slug',
                    'images',
                    'inventories.warehouse',
                ]),
            ])
            ->first();

        if ($variant) {
            return response()->json([
                'contract_version' => 'v1',
                'data' => $this->serializeItem($variant->product, $variant, $warehouses),
            ]);
        }

        $product = Product::query()
            ->published()
            ->where('external_id', $externalId)
            ->whereDoesntHave('variants')
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images', 'inventories.warehouse'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Catalog item not found.'], 404);
        }

        return response()->json([
            'contract_version' => 'v1',
            'data' => $this->serializeItem($product, null, $warehouses),
        ]);
    }

    public function warehouses(): JsonResponse
    {
        return response()->json([
            'contract_version' => 'v1',
            'data' => $this->eligibleWarehouses()->map(fn (Warehouse $warehouse): array => [
                'external_id' => $warehouse->external_id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ])->values(),
        ]);
    }

    private function eligibleWarehouses(): Collection
    {
        return Warehouse::query()
            ->active()
            ->where('online_fulfillment_enabled', true)
            ->orderBy('online_fulfillment_priority')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['id', 'external_id', 'code', 'name']);
    }

    private function itemsForProduct(Product $product, Collection $warehouses): Collection
    {
        if ($product->variants->isNotEmpty()) {
            return $product->variants
                ->where('is_active', true)
                ->map(fn (ProductVariant $variant): array => $this->serializeItem($product, $variant, $warehouses))
                ->values();
        }

        return collect([$this->serializeItem($product, null, $warehouses)]);
    }

    private function serializeItem(Product $product, ?ProductVariant $variant, Collection $warehouses): array
    {
        $inventories = $variant?->inventories ?? $product->inventories;

        if ($variant && $inventories->isEmpty()) {
            $inventories = $product->inventories;
        }

        $inventoryByWarehouse = $inventories->keyBy('warehouse_id');
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $description = $product->short_description ?: Str::limit(strip_tags($product->description ?? ''), 1000, '');

        return [
            'external_id' => $variant?->external_id ?? $product->external_id,
            'item_type' => $variant ? 'variant' : 'product',
            'product_external_id' => $product->external_id,
            'sku' => $variant?->sku ?? $product->sku,
            'name' => $variant ? $product->name.' — '.$variant->name : $product->name,
            'description' => $description,
            'category' => $product->category ? [
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'brand' => $product->brand ? [
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'image_url' => $image ? Storage::disk('public')->url($image->path) : null,
            'active' => true,
            'visible' => true,
            'price' => [
                'amount_minor' => (int) ($variant?->price_amount ?? $product->price_amount),
                'currency' => $product->currency,
            ],
            'availability' => [
                'warehouses' => $warehouses->map(fn (Warehouse $warehouse): array => [
                    'warehouse_external_id' => $warehouse->external_id,
                    'available_quantity' => (int) ($inventoryByWarehouse->get($warehouse->id)?->available_quantity ?? 0),
                ])->values(),
            ],
            'revision' => (int) $product->external_revision,
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }
}
