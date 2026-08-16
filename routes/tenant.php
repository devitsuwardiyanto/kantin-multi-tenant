<?php

use App\Http\Controllers\Tenant\TenantMenuController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Konteks OPERATOR TENANT (internal). Prefix: tenant/{tenant:slug}, name: tenant.*
 * Middleware auth+verified+tenant (SetTenantContext) + scopeBindings di bootstrap/app.php.
 * {tenant} terikat model Tenant (slug); {menu} scoped di bawah tenant (global scope + relasi).
 */
Route::get('/dashboard', fn (Tenant $tenant) => view('tenant.dashboard', ['tenant' => $tenant]))
    ->name('dashboard');

Route::get('/menus', [TenantMenuController::class, 'index'])->name('menus.index');
Route::get('/menus/{menu}', [TenantMenuController::class, 'show'])->name('menus.show');
Route::patch('/menus/{menu}', [TenantMenuController::class, 'update'])->name('menus.update');
