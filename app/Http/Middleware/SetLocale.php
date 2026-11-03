<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = $request->is('admin') || $request->is('admin/*');
        $field = ($isAdmin ? 'admin' : 'storefront').'_locale';
        $locale = $request->user()?->getAttribute($field)
            ?: $request->cookie($field)
            ?: $request->session()->get($field)
            ?: config('locales.default', 'es');

        if (! array_key_exists($locale, config('locales.available', []))) {
            $locale = config('locales.default', 'es');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
