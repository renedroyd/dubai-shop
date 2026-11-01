<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Payments\PaymentDriverManager;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly PaymentDriverManager $drivers,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current((string) $request->session()->get('cart_session_key'));

        if ($cart->items->isEmpty()) {
            return to_route('cart.index')->withErrors(['cart' => 'Agrega productos antes de continuar.']);
        }

        $paymentMethods = PaymentMethod::query()->enabled()->orderBy('sort_order')->get()
            ->map(fn (PaymentMethod $method) => [
                'method' => $method,
                'instructions' => $this->drivers->driver($method->driver)->checkoutInstructions($method),
            ]);

        return view('storefront.checkout.create', compact('cart', 'paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_region' => ['nullable', 'string', 'max:255'],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_country' => ['required', 'string', 'size:2'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $method = PaymentMethod::query()->enabled()->findOrFail($data['payment_method_id']);

        if ($method->driver === 'zelle_manual' && ! $request->hasFile('payment_proof')) {
            throw ValidationException::withMessages(['payment_proof' => 'Adjunta el comprobante de tu transferencia Zelle.']);
        }

        $cart = $this->carts->current((string) $request->session()->get('cart_session_key'));
        $customer = collect($data)->only([
            'customer_name', 'customer_email', 'customer_phone', 'shipping_address',
            'shipping_address_line_2', 'shipping_city', 'shipping_region',
            'shipping_postal_code', 'shipping_country', 'customer_note',
        ])->all();

        $paymentData = [
            'proof' => $request->file('payment_proof'),
            'reference' => $data['payment_reference'] ?? null,
            'customer_note' => $data['payment_note'] ?? null,
        ];
        $token = Str::random(64);
        $order = $this->checkout->placeOrder($cart, $customer, $method, $paymentData, $token);

        return to_route('orders.show', ['order' => $order->number, 'token' => $token])
            ->with('status', 'Tu pedido se registró correctamente.');
    }
}
