<?php

namespace App\Filament\Resources\StorefrontSettings\Pages;

use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontSettings extends ListRecords
{
    protected static string $resource = StorefrontSettingResource::class;
}
