<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks PELANGGAN (publik, anonim). Prefix: kantin/{canteen}, name: customer.*
 * Katalog & pemesanan diisi Modul 7–9; model binding canteen pada Modul 4.
 */
Route::get('/', function (string $canteen) {
    return view('customer.home', ['canteen' => $canteen]);
})->name('home');
