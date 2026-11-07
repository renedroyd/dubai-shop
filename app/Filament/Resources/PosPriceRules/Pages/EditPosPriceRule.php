<?php

namespace App\Filament\Resources\PosPriceRules\Pages;

use App\Filament\Resources\PosPriceRules\PosPriceRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosPriceRule extends EditRecord
{
    protected static string $resource = PosPriceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
