<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Payments\PaymentDriverManager;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutService;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly PaymentDriverManager $drivers,
        private readonly ShippingRateService $shippingRates,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current((string) $request->session()->get('cart_session_key'));

        if ($cart->items->isEmpty()) {
            return to_route('cart.index')->withErrors(['cart' => 'Agrega productos antes de continuar.']);
        }

        $paymentMethods = PaymentMethod::query()->enabled()
            ->when($cart->currency !== 'USD', fn ($query) => $query->where('driver', '!=', 'zelle_manual'))
            ->orderBy('sort_order')->get()
            ->map(fn (PaymentMethod $method) => [
                'method' => $method,
                'instructions' => $this->drivers->driver($method->driver)->checkoutInstructions($method),
            ]);
        $provinces = ShippingRateService::provinces();

        return view('storefront.checkout.create', compact('cart', 'paymentMethods', 'provinces'));
    }

    public function quoteShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipping_region' => ['required', Rule::in(array_keys(ShippingRateService::provinces()))],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_locality' => ['nullable', 'string', 'max:160'],
            'shipping_country' => ['required', Rule::in(['CU'])],
        ]);

        $cart = $this->carts->current((string) $request->session()->get('cart_session_key'));
        $zone = $this->shippingRates->resolve($data, $cart->currency);

        return response()->json([
            'amount' => number_format((float) $zone->shipping_amount, 2, '.', ''),
            'currency' => $zone->currency,
            'zone' => $zone->label(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_locality' => ['nullable', 'string', 'max:160'],
            'shipping_region' => ['required', Rule::in(array_keys(ShippingRateService::provinces()))],
            'shipping_postal_code' => ['nullable', 'string', 'max:30'],
            'shipping_country' => ['required', Rule::in(['CU'])],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $this->carts->current((string) $request->session()->get('cart_session_key'));
        $method = PaymentMethod::query()->enabled()->findOrFail($data['payment_method_id']);

        if ($method->driver === 'zelle_manual' && $cart->currency !== 'USD') {
            throw ValidationException::withMessages(['payment_method_id' => 'Zelle solo admite pagos en USD. Selecciona efectivo contra entrega para esta moneda.']);
        }

        if ($method->driver === 'zelle_manual' && ! $request->hasFile('payment_proof')) {
            throw ValidationException::withMessages(['payment_proof' => 'Adjunta el comprobante de tu transferencia Zelle.']);
        }

        $customer = collect($data)->only([
            'customer_name', 'customer_email', 'customer_phone', 'shipping_address',
            'shipping_address_line_2', 'shipping_city', 'shipping_locality', 'shipping_region',
            'shipping_postal_code', 'shipping_country', 'customer_note',
        ])->all();

        $paymentData = [
            'proof' => $request->file('payment_proof'),
            'reference' => $data['payment_reference'] ?? null,
            'customer_note' => $data['payment_note'] ?? null,
        ];
        $token = Str::random(64);
        $order = $this->checkout->placeOrder($cart, $customer, $method, $paymentData, $token);

        if ($request->user() && $request->boolean('save_address')) {
            $addressData = collect($customer)->only([
                'customer_name', 'customer_phone', 'shipping_address', 'shipping_address_line_2',
                'shipping_city', 'shipping_locality', 'shipping_region', 'shipping_postal_code', 'shipping_country',
            ])->all();

            $request->user()->customerAddresses()->firstOrCreate(
                [
                    'address' => $addressData['shipping_address'],
                    'city' => $addressData['shipping_city'],
                    'region' => $addressData['shipping_region'],
                    'country' => $addressData['shipping_country'],
                ],
                [
                    'label' => 'Dirección de entrega',
                    'recipient_name' => $addressData['customer_name'],
                    'phone' => $addressData['customer_phone'],
                    'address_line_2' => $addressData['shipping_address_line_2'] ?? null,
                    'locality' => $addressData['shipping_locality'] ?? null,
                    'postal_code' => $addressData['shipping_postal_code'] ?? null,
                    'is_default' => ! $request->user()->customerAddresses()->exists(),
                ],
            );
        }

        return to_route('orders.show', ['order' => $order->number, 'token' => $token])
            ->with('status', 'Tu pedido se registró correctamente.');
    }
}
