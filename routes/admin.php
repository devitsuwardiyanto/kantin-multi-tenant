<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks PENGELOLA KANTIN (internal). Prefix: admin, name: admin.*
 * Middleware auth+verified+role:admin dipasang di bootstrap/app.php.
 * Administrasi tenant/role/komisi diisi Modul 5.
 */
Route::get('/dashboard', function () {
    return view('admin.dashboard');
})->name('dashboard');
