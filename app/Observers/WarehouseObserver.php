<?php

namespace App\Observers;

use App\Models\Warehouse;
use App\Services\IntegrationEventPublisher;

class WarehouseObserver
{
    public function __construct(private readonly IntegrationEventPublisher $publisher) {}

    public function created(Warehouse $warehouse): void
    {
        $this->publish($warehouse);
    }

    public function updated(Warehouse $warehouse): void
    {
        if (! $warehouse->wasChanged([
            'name',
            'code',
            'is_active',
            'online_fulfillment_enabled',
            'online_fulfillment_priority',
        ])) {
            return;
        }

        $this->publish($warehouse);
    }

    public function deleted(Warehouse $warehouse): void
    {
        $this->publish($warehouse);
    }

    private function publish(Warehouse $warehouse): void
    {
        $this->publisher->publish(
            'warehouse.updated',
            'warehouse',
            $warehouse->external_id,
            1,
            [
                'warehouse_external_id' => $warehouse->external_id,
                'active' => (bool) $warehouse->is_active,
                'online_fulfillment_enabled' => (bool) $warehouse->online_fulfillment_enabled,
            ],
        );
    }
}
