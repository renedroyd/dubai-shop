<?php

namespace App\Policies;

class InventoryPolicy extends AdminResourcePolicy
{
    protected string $resource = 'inventories';
}
