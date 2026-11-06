<?php

namespace App\Policies;

class WarehouseTransferPolicy extends AdminResourcePolicy
{
    protected string $resource = 'warehouse_transfers';
}
