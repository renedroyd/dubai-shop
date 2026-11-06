<?php

namespace App\Filament\Resources\WarehouseTransferRules\Pages;

use App\Filament\Resources\WarehouseTransferRules\WarehouseTransferRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseTransferRules extends ListRecords
{
    protected static string $resource = WarehouseTransferRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Nueva regla'))];
    }
}
