<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks PENGELOLA KANTIN (internal). Prefix: admin, name: admin.*
 * Grup (prefix/name/middleware auth+verified+role:admin) didefinisikan tunggal di PortalRoutes::admin();
 * route fitur ditambahkan oleh modul di app/Modules/{Modul}/routes/admin.php.
 * Administrasi tenant/role/komisi diisi Modul 5.
 */
Route::get('/dashboard', function () {
    return view('admin.dashboard');
})->name('dashboard');
