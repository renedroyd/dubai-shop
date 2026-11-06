<?php

namespace App\Policies;

class PaymentPolicy extends AdminResourcePolicy
{
    protected string $resource = 'payments';
}
