<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\IntegrationEventPublisher;

class InventoryObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function saved(Inventory $inventory): void
    {
        $this->publishForInventory($inventory);
    }

    public function deleted(Inventory $inventory): void
    {
        $this->publishForInventory($inventory);
    }

    private function publishForInventory(Inventory $inventory): void
    {
        $productId = $inventory->product_id
            ?? ProductVariant::query()->whereKey($inventory->product_variant_id)->value('product_id');

        if (! $productId) {
            return;
        }

        $product = Product::query()->with('variants')->find($productId);

        if (! $product) {
            return;
        }

        if ($inventory->product_variant_id) {
            $variants = $product->variants->where('id', $inventory->product_variant_id);
            $items = $variants->where('is_active', true)->map(fn (ProductVariant $variant): array => [
                'external_id' => $variant->external_id,
                'revision' => (int) $product->external_revision,
            ]);
        } elseif ($product->variants->isEmpty()) {
            $items = collect([[
                'external_id' => $product->external_id,
                'revision' => (int) $product->external_revision,
            ]]);
        } else {
            $items = $product->variants
                ->where('is_active', true)
                ->filter(fn (ProductVariant $variant): bool => ! $variant->inventories()->exists())
                ->map(fn (ProductVariant $variant): array => [
                    'external_id' => $variant->external_id,
                    'revision' => (int) $product->external_revision,
                ]);
        }

        foreach ($items as $item) {
            $this->publisher->publish(
                'inventory.changed',
                'catalog_item',
                $item['external_id'],
                max(1, $item['revision']),
                ['change_source' => 'inventory'],
            );
        }
    }
}
