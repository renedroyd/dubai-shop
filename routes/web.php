<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\CustomerAccountController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Seo\RobotsController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/', HomeController::class)->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('guest')->group(function () {
    Route::get('/cuenta/registro', [CustomerAuthController::class, 'registerForm'])->name('customer.register');
    Route::post('/cuenta/registro', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1')->name('customer.register.store');
    Route::get('/cuenta/ingresar', [CustomerAuthController::class, 'loginForm'])->name('customer.login');
    Route::post('/cuenta/ingresar', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1')->name('customer.login.store');
    Route::get('/cuenta/recuperar', [CustomerAuthController::class, 'requestPasswordResetForm'])->name('customer.password.request');
    Route::post('/cuenta/recuperar', [CustomerAuthController::class, 'sendPasswordResetLink'])->middleware('throttle:5,1')->name('customer.password.email');
    Route::get('/cuenta/restablecer/{token}', [CustomerAuthController::class, 'resetPasswordForm'])->name('customer.password.reset');
    Route::post('/cuenta/restablecer', [CustomerAuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('customer.password.update');
});

Route::middleware('auth')->prefix('cuenta')->name('customer.')->group(function () {
    Route::post('/salir', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('/', [CustomerAccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/direcciones', [CustomerAccountController::class, 'addresses'])->name('addresses');
    Route::post('/direcciones', [CustomerAccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('/direcciones/{address}', [CustomerAccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('/direcciones/{address}', [CustomerAccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::patch('/direcciones/{address}/predeterminada', [CustomerAccountController::class, 'makeDefault'])->name('addresses.default');
});

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrito/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/carrito/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/carrito/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::get('/checkout/envio', [CheckoutController::class, 'quoteShipping'])->middleware('throttle:30,1')->name('checkout.shipping-quote');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:5,1')->name('checkout.store');
Route::get('/pedido/{order}/{token}', [OrderController::class, 'show'])->name('orders.show');

Route::get('/paginas/{slug}', [PageController::class, 'show'])->where('slug', '[A-Za-z0-9\-]+')->name('pages.show');
