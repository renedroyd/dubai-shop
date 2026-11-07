<?php

namespace App\Filament\Resources\PosLoyaltySettings\Pages;

use App\Filament\Resources\PosLoyaltySettings\PosLoyaltySettingResource;
use Filament\Resources\Pages\EditRecord;

class EditPosLoyaltySetting extends EditRecord
{
    protected static string $resource = PosLoyaltySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
