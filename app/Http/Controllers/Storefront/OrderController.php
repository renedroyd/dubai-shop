<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Request $request, string $order, string $token): View
    {
        $record = Order::query()->where('number', $order)->with(['items', 'payments.method'])->firstOrFail();
        abort_unless(hash_equals($record->public_token_hash, hash('sha256', $token)), 404);

        return view('storefront.orders.show', ['order' => $record]);
    }
}
