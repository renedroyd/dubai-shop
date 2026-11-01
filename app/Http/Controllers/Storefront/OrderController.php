<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function show(string $order, string $token): Response
    {
        $record = Order::query()->where('number', $order)->firstOrFail();
        abort_unless(hash_equals($record->public_token_hash, hash('sha256', $token)), 404);

        $record->load(['items', 'payments.method']);

        return response()->view('storefront.orders.show', ['order' => $record])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
