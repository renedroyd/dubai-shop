<?php

namespace App\Filament\Resources\AdminRoles\Pages;

use App\Filament\Resources\AdminRoles\AdminRoleResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminRoles extends ListRecords
{
    protected static string $resource = AdminRoleResource::class;
}
