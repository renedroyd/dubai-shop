<?php

namespace App\Filament\Resources\PosPriceRules\Pages;

use App\Filament\Resources\PosPriceRules\PosPriceRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosPriceRules extends ListRecords
{
    protected static string $resource = PosPriceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
