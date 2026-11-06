<?php

namespace App\Policies;

class OrderPolicy extends AdminResourcePolicy
{
    protected string $resource = 'orders';
}
