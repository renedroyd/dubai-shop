<?php

namespace App\Observers;

use App\Jobs\SendOrderNotification;
use App\Models\Order;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Order $order): void
    {
        SendOrderNotification::dispatch($order->id, 'order_received');
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status')) {
            $eventKey = match ($order->payment_status) {
                'paid' => 'payment_approved',
                'payment_rejected' => 'payment_rejected',
                default => null,
            };

            if ($eventKey) {
                SendOrderNotification::dispatch($order->id, $eventKey);
            }
        }

        if ($order->wasChanged('status')) {
            $eventKey = match ($order->status) {
                Order::STATUS_CONFIRMED => 'order_confirmed',
                Order::STATUS_PROCESSING => 'order_processing',
                Order::STATUS_SHIPPED => 'order_shipped',
                Order::STATUS_DELIVERED => 'order_delivered',
                Order::STATUS_CANCELLED => 'order_cancelled',
                default => null,
            };

            if ($eventKey) {
                SendOrderNotification::dispatch($order->id, $eventKey);
            }
        }
    }
}
