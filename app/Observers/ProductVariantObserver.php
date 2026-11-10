<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\IntegrationEventPublisher;

class ProductVariantObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('price_amount')) {
            return;
        }

        $product = $variant->product()->first();

        if (! $product) {
            return;
        }

        $previousAmount = $variant->getOriginal('price_amount');
        $currentAmount = $variant->price_amount;
        $previousEffective = $previousAmount === null ? (int) $product->price_amount : (int) $previousAmount;
        $currentEffective = $currentAmount === null ? (int) $product->price_amount : (int) $currentAmount;

        if ($previousEffective === $currentEffective) {
            return;
        }

        $this->publisher->publish(
            'price.changed',
            'catalog_item',
            $variant->external_id,
            max(1, (int) $product->external_revision),
            [
                'previous_price' => ['amount_minor' => $previousEffective, 'currency' => $product->currency],
                'current_price' => ['amount_minor' => $currentEffective, 'currency' => $product->currency],
            ],
        );
    }
}
