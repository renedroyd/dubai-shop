<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Seo\RobotsController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/', HomeController::class)->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrito/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/carrito/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/carrito/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::get('/checkout/envio', [CheckoutController::class, 'quoteShipping'])->middleware('throttle:30,1')->name('checkout.shipping-quote');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:5,1')->name('checkout.store');
Route::get('/pedido/{order}/{token}', [OrderController::class, 'show'])->name('orders.show');

Route::get('/paginas/{slug}', [PageController::class, 'show'])->where('slug', '[A-Za-z0-9\-]+')->name('pages.show');
