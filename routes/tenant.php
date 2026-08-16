<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Konteks OPERATOR TENANT (internal). Prefix: tenant/{tenant:slug}, name: tenant.*
 * Grup (middleware auth+verified+tenant/SetTenantContext + scopeBindings) didefinisikan tunggal di
 * PortalRoutes::tenant(); route fitur ditambahkan modul di app/Modules/{Modul}/routes/tenant.php.
 */
Route::get('/dashboard', fn (Tenant $tenant) => view('tenant.dashboard', ['tenant' => $tenant]))
    ->name('dashboard');
