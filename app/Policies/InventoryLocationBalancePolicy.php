<?php

namespace App\Policies;

class InventoryLocationBalancePolicy extends AdminResourcePolicy
{
    protected string $resource = 'inventory_location_balances';
}
