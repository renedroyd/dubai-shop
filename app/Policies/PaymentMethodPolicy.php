<?php

namespace App\Policies;

class PaymentMethodPolicy extends AdminResourcePolicy
{
    protected string $resource = 'payment_methods';
}
