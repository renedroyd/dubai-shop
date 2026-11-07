<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\PosLoyaltyWallet;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        abort_if($request->user()->is_admin, 403);

        $orders = $request->user()->orders()->with('items')->latest('placed_at')->paginate(10);
        $loyaltyWallets = PosLoyaltyWallet::query()->where('user_id', $request->user()->id)->orderBy('currency')->get();
        $loyaltyWallets->each(function (PosLoyaltyWallet $wallet): void {
            $wallet->setAttribute('available_points', $wallet->availablePoints());
            $wallet->setAttribute('expiring_points_30_days', $wallet->expiringPointsWithin(30));
            $wallet->setRelation('recentEntries', $wallet->entries()->latest()->limit(6)->get());
        });

        return view('storefront.customer.dashboard', compact('orders', 'loyaltyWallets'));
    }

    public function addresses(Request $request): View
    {
        abort_if($request->user()->is_admin, 403);

        $addresses = $request->user()->customerAddresses()->latest()->get();

        return view('storefront.customer.addresses', compact('addresses'));
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->validatedAddress($request);
        $makeDefault = $request->boolean('is_default') || ! $request->user()->customerAddresses()->exists();

        $address = $request->user()->customerAddresses()->create([
            ...$data,
            'is_default' => false,
        ]);

        if ($makeDefault) {
            $this->setDefault($request, $address);
        }

        return to_route('customer.addresses')->with('status', 'Dirección guardada correctamente.');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $data = $this->validatedAddress($request);
        $address->update($data);

        if ($request->boolean('is_default')) {
            $this->setDefault($request, $address);
        }

        return to_route('customer.addresses')->with('status', 'Dirección actualizada.');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = $request->user()->customerAddresses()->first();
            if ($next) {
                $this->setDefault($request, $next);
            }
        }

        return to_route('customer.addresses')->with('status', 'Dirección eliminada.');
    }

    public function makeDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $this->setDefault($request, $address);

        return to_route('customer.addresses')->with('status', 'Dirección predeterminada actualizada.');
    }

    private function setDefault(Request $request, CustomerAddress $address): void
    {
        $request->user()->customerAddresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:160'],
            'region' => ['required', Rule::in(array_keys(ShippingRateService::provinces()))],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', Rule::in(['CU'])],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
