<?php

namespace App\Contracts\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;

interface PaymentDriver
{
    public function createPayment(Order $order, PaymentMethod $method, array $data): Payment;

    /** @return array<string, mixed> */
    public function checkoutInstructions(PaymentMethod $method): array;
}
