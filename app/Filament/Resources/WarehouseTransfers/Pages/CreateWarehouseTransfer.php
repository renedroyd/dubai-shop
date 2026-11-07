<?php

namespace App\Filament\Resources\WarehouseTransfers\Pages;

use App\Filament\Resources\WarehouseTransfers\WarehouseTransferResource;
use App\Models\Warehouse;
use App\Services\Inventory\WarehouseTransferService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWarehouseTransfer extends CreateRecord
{
    protected static string $resource = WarehouseTransferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        return app(WarehouseTransferService::class)->create(
            Warehouse::query()->findOrFail($data['source_warehouse_id']),
            Warehouse::query()->findOrFail($data['destination_warehouse_id']),
            $items,
            auth()->id(),
            $data['notes'] ?? null,
            $data['transfer_type'] ?? 'standard',
        );
    }
}
