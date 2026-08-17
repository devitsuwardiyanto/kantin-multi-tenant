<?php

use App\Http\Controllers\Customer\OrderStatusController;
use Illuminate\Support\Facades\Route;

/**
 * Konteks PELANGGAN (publik, anonim). Prefix: kantin/{canteen}, name: customer.*
 * Katalog & pemesanan diisi Modul 7–9; model binding canteen pada Modul 4.
 */
Route::get('/', function (string $canteen) {
    return view('customer.home', ['canteen' => $canteen]);
})->name('home');

// Status pesanan pasca-checkout (dikenali via cookie pelacakan opaque).
Route::get('/order', OrderStatusController::class)->name('order.show');
