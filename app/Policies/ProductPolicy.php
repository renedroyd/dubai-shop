<?php

namespace App\Policies;

class ProductPolicy extends AdminResourcePolicy
{
    protected string $resource = 'products';
}
