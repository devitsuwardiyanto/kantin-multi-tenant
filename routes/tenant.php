<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks OPERATOR TENANT (internal). Prefix: tenant/{tenant}, name: tenant.*
 * Grup (prefix/name/middleware auth+verified+role:tenant) didefinisikan tunggal di PortalRoutes::tenant();
 * route fitur ditambahkan oleh modul di app/Modules/{Modul}/routes/tenant.php.
 * Katalog/KDS tenant diisi Modul 7 & 12; scopeBindings pada Modul 4.
 */
Route::get('/dashboard', function (string $tenant) {
    return view('tenant.dashboard', ['tenant' => $tenant]);
})->name('dashboard');
