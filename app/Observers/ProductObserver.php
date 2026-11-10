<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\IntegrationEventPublisher;

class ProductObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function saved(Product $product): void
    {
        if ($product->wasRecentlyCreated || ! $product->wasChanged(['price_amount', 'currency'])) {
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
