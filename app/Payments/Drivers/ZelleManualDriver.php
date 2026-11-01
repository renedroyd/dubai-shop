<?php

namespace App\Payments\Drivers;

use App\Contracts\Payments\PaymentDriver;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Storage;

class ZelleManualDriver implements PaymentDriver
{
    public function createPayment(Order $order, PaymentMethod $method, array $data): Payment
    {
        $proofPath = Storage::disk('local')->putFile('payment-proofs', $data['proof']);

        return $order->payments()->create([
            'payment_method_id' => $method->id,
            'driver' => $method->driver,
            'status' => Payment::STATUS_AWAITING_REVIEW,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'reference' => $data['reference'] ?? null,
            'proof_path' => $proofPath,
            'customer_note' => $data['customer_note'] ?? null,
        ]);
    }

    public function checkoutInstructions(PaymentMethod $method): array
    {
        return $method->settings ?? [];
    }
}
