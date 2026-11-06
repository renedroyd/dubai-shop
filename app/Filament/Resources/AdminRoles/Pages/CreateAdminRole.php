<?php

namespace App\Filament\Resources\AdminRoles\Pages;

use App\Filament\Resources\AdminRoles\AdminRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminRole extends CreateRecord
{
    protected static string $resource = AdminRoleResource::class;
}
