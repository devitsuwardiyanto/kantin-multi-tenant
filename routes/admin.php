<?php

use Illuminate\Support\Facades\Route;

/**
 * Konteks PENGELOLA KANTIN (internal). Prefix: admin, name: admin.*
 * Grup (prefix/name/middleware auth+verified+role:admin) didefinisikan tunggal di PortalRoutes::admin();
 * route fitur ditambahkan oleh modul di app/Modules/{Modul}/routes/admin.php.
 * Administrasi tenant/role/komisi/rekening (Modul 5): app/Modules/Admin/routes/admin.php.
 * Policy per-aksi (TenantPolicy) diperiksa di controller modul.
 */
Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');
