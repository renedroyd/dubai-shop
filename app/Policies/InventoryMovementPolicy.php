<?php

namespace App\Policies;

class InventoryMovementPolicy extends AdminResourcePolicy
{
    protected string $resource = 'inventory_movements';
}
