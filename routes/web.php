<?php

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrito/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/carrito/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/carrito/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
