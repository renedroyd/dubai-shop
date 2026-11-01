<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Storefront\StorefrontSettings;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(StorefrontSettings $settings): View
    {
        return view('storefront.home', [
            'settings' => $settings->all(),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->limit(4)
                ->get(),
        ]);
    }
}
