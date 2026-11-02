<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StorefrontPage;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = StorefrontPage::query()->published()->where('slug', $slug)->firstOrFail();

        return view('storefront.page', compact('page'));
    }
}
