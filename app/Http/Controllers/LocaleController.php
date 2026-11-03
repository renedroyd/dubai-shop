<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('locales.available', [])), 404);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['storefront', 'admin'])],
        ]);

        $scope = $validated['scope'];
        $field = $scope.'_locale';

        if ($user = $request->user()) {
            $user->forceFill([$field => $locale])->save();
        }

        $request->session()->put($field, $locale);
        Cookie::queue(cookie($field, $locale, 60 * 24 * 365));

        $previous = url()->previous();
        $previousHost = parse_url($previous, PHP_URL_HOST);

        if (! $previousHost || $previousHost !== $request->getHost()) {
            $previous = $scope === 'admin' ? url('/admin') : route('home');
        }

        return redirect()->to($previous);
    }
}
