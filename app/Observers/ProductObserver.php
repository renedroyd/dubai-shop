<?php

namespace App\\Observers;

use App\\Models\\Product;
use App\\Services\\IntegrationEventPublisher;

class ProductObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function created(Product $product): void
    {
        if ($this->isPublished($product)) {
            $this->publishLifecycle($product, 'catalog.item_published');
        }
    }

    public function updated(Product $product): void
    {
        $wasPublished = $this->wasPublished($product);
        $isPublished = $this->isPublished($product);

        if (! $wasPublished && $isPublished) {
            $this->publishLifecycle($product, 'catalog.item_published');
        } elseif ($wasPublished && ! $isPublished) {
            $this->publishLifecycle($product, 'catalog.item_unpublished');
        } elseif ($isPublished && $product->wasChanged([
            'name',
            'short_description',
            'description',
            'category_id',
            'brand_id',
            'sku',
        ])) {
            $this->publishLifecycle($product, 'catalog.item_updated');
        }

        if (! $product->wasChanged(['price_amount', 'currency'])) {
            return;
        }

        $previousAmount = (int) $product->getOriginal('price_amount');
        $previousCurrency = (string) $product->getOriginal('currency');
        $currentAmount = (int) $product->price_amount;
        $currentCurrency = (string) $product->currency;
        $variants = $product->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            $this->publishPriceChange(
                $product->external_id,
                (int) $product->external_revision,
                $previousAmount,
                $previousCurrency,
                $currentAmount,
                $currentCurrency,
            );

            return;
        }

        foreach ($variants as $variant) {
            $oldAmount = $variant->price_amount === null ? $previousAmount : (int) $variant->price_amount;
            $newAmount = $variant->price_amount === null ? $currentAmount : (int) $variant->price_amount;

            if ($oldAmount === $newAmount && $previousCurrency === $currentCurrency) {
                continue;
            }

            $this->publishPriceChange(
                $variant->external_id,
                (int) $product->external_revision,
                $oldAmount,
                $previousCurrency,
                $newAmount,
                $currentCurrency,
            );
        }
    }

    public function deleted(Product $product): void
    {
        $this->publishLifecycle($product, 'catalog.item_unpublished');
    }

    public function restored(Product $product): void
    {
        if ($this->isPublished($product)) {
            $this->publishLifecycle($product, 'catalog.item_published');
        }
    }

    private function isPublished(Product $product): bool
    {
        return ! $product->trashed()
            && $product->status === Product::STATUS_ACTIVE
            && (bool) $product->is_visible;
    }

    private function wasPublished(Product $product): bool
    {
        return $product->getOriginal('deleted_at') === null
            && $product->getOriginal('status') === Product::STATUS_ACTIVE
            && (bool) $product->getOriginal('is_visible');
    }

    /**
     * Publish one lifecycle event for each item exposed by the integration API.
     */
    private function publishLifecycle(Product $product, string $eventType): void
    {
        $variants = $product->variants()
            ->where('is_active', true)
            ->get(['external_id']);

        $externalIds = $variants->isEmpty()
            ? [$product->external_id]
            : $variants->pluck('external_id')->all();

        foreach ($externalIds as $externalId) {
            $this->publisher->publish(
                $eventType,
                'catalog_item',
                $externalId,
                max(1, (int) $product->external_revision),
                ['product_external_id' => $product->external_id],
            );
        }
    }

    private function publishPriceChange(
        string $externalId,
        int $revision,
        int $previousAmount,
        string $previousCurrency,
        int $currentAmount,
        string $currentCurrency,
    ): void {
        $this->publisher->publish(
            'price.changed',
            'catalog_item',
            $externalId,
            max(1, $revision),
            [
                'previous_price' => ['amount_minor' => $previousAmount, 'currency' => $previousCurrency],
                'current_price' => ['amount_minor' => $currentAmount, 'currency' => $currentCurrency],
            ],
        );
    }
}
