<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\IntegrationEventPublisher;
use Illuminate\Support\Collection;

class InventoryObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function saved(Inventory $inventory): void
    {
        if (! $inventory->wasChanged([
            'warehouse_id',
            'product_id',
            'product_variant_id',
            'quantity_on_hand',
            'quantity_reserved',
            'quantity_blocked',
        ])) {
            return;
        }

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
            $items = $product->variants
                ->where('id', $inventory->product_variant_id)
                ->where('is_active', true)
                ->map(fn (ProductVariant $variant): array => [
                    'external_id' => $variant->external_id,
                    'variant' => $variant,
                ]);
        } elseif ($product->variants->isEmpty()) {
            $items = collect([[
                'external_id' => $product->external_id,
                'variant' => null,
            ]]);
        } else {
            $items = $product->variants
                ->where('is_active', true)
                ->filter(fn (ProductVariant $variant): bool => ! $variant->inventories()->exists())
                ->map(fn (ProductVariant $variant): array => [
                    'external_id' => $variant->external_id,
                    'variant' => $variant,
                ]);
        }

        if ($items->isEmpty()) {
            return;
        }

        $warehouses = Warehouse::query()
            ->active()
            ->where('online_fulfillment_enabled', true)
            ->orderBy('online_fulfillment_priority')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['id', 'external_id']);

        foreach ($items as $item) {
            $availability = $this->availabilitySnapshot($product, $item['variant'], $warehouses);

            $this->publisher->publish(
                'inventory.changed',
                'catalog_item',
                $item['external_id'],
                max(1, (int) $product->external_revision),
                [
                    'change_source' => 'inventory',
                    'availability' => ['warehouses' => $availability],
                ],
            );
        }
    }

    /**
     * Match the public catalog API's effective inventory and warehouse rules.
     *
     * @return array<int, array{warehouse_external_id:string, available_quantity:int}>
     */
    private function availabilitySnapshot(Product $product, ?ProductVariant $variant, Collection $warehouses): array
    {
        $inventories = $variant
            ? $variant->inventories()->get()->keyBy('warehouse_id')
            : collect();

        if (! $variant || $inventories->isEmpty()) {
            $inventories = $product->inventories()->get()->keyBy('warehouse_id');
        }

        return $warehouses->map(function (Warehouse $warehouse) use ($inventories): array {
            $inventory = $inventories->get($warehouse->id);

            return [
                'warehouse_external_id' => $warehouse->external_id,
                'available_quantity' => (int) ($inventory?->available_quantity ?? 0),
            ];
        })->values()->all();
    }
}
