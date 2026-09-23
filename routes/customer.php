<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks PELANGGAN (publik, anonim). Prefix: kantin/{canteen}, name: customer.*
 * Grup didefinisikan tunggal di PortalRoutes::customer(); route fitur ditambahkan oleh modul
 * di app/Modules/{Modul}/routes/customer.php.
 * Katalog & pemesanan diisi Modul 7–9; model binding canteen pada Modul 4.
 */
Route::get('/', function (string $canteen) {
    return view('customer.home', ['canteen' => $canteen]);
})->name('home');
