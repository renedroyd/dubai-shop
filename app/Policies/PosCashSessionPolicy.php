<?php

namespace App\Policies;

class PosCashSessionPolicy extends AdminResourcePolicy
{
    protected string $resource = 'pos_cash_sessions';
}