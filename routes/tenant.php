<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks OPERATOR TENANT (internal). Prefix: tenant/{tenant}, name: tenant.*
 * Middleware auth+verified+role:tenant dipasang di bootstrap/app.php.
 * Katalog/KDS tenant diisi Modul 7 & 12; scopeBindings pada Modul 4.
 */
Route::get('/dashboard', function (string $tenant) {
    return view('tenant.dashboard', ['tenant' => $tenant]);
})->name('dashboard');
