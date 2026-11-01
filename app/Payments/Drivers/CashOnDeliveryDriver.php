<?php

namespace App\Payments\Drivers;

use App\Contracts\Payments\PaymentDriver;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;

class CashOnDeliveryDriver implements PaymentDriver
{
    public function createPayment(Order $order, PaymentMethod $method, array $data): Payment
    {
        return $order->payments()->create([
            'payment_method_id' => $method->id,
            'driver' => $method->driver,
            'status' => Payment::STATUS_PENDING_COLLECTION,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'customer_note' => $data['customer_note'] ?? null,
        ]);
    }

    public function checkoutInstructions(PaymentMethod $method): array
    {
        return $method->settings ?? [];
    }
}
