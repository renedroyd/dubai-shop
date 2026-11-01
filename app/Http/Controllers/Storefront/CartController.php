<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function index(Request $request): View
    {
        $cart = $this->carts->current($this->cartSessionKey($request));

        return view('storefront.cart.index', compact('cart'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->carts->add(
            $this->cartSessionKey($request),
            (int) $data['product_id'],
            isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null,
            (int) $data['quantity'],
        );

        return to_route('cart.index')->with('status', 'Producto agregado al carrito.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = $this->carts->current($this->cartSessionKey($request));
        abort_unless($cartItem->cart_id === $cart->id, 404);

        $this->carts->updateQuantity($cart, $cartItem, (int) $data['quantity']);

        return to_route('cart.index')->with('status', 'Carrito actualizado.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->carts->current($this->cartSessionKey($request));
        $this->carts->remove($cart, $cartItem);

        return to_route('cart.index')->with('status', 'Producto eliminado del carrito.');
    }

    private function cartSessionKey(Request $request): string
    {
        if (! $request->session()->has('cart_session_key')) {
            $request->session()->put('cart_session_key', (string) Str::uuid());
        }

        return (string) $request->session()->get('cart_session_key');
    }
}
