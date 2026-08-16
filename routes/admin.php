<?php

use App\Http\Controllers\Admin\AdminBankAccountController;
use App\Http\Controllers\Admin\AdminCommissionController;
use App\Http\Controllers\Admin\AdminDiningTableController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminTenantRoleController;
use Illuminate\Support\Facades\Route;

/**
 * Konteks PENGELOLA KANTIN (internal). Prefix: admin, name: admin.*
 * Middleware auth+verified+role:admin di bootstrap/app.php. Policy per-aksi (TenantPolicy).
 */
Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');

Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
Route::get('/tenants/create', [AdminTenantController::class, 'create'])->name('tenants.create');
Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
Route::get('/tenants/{tenant}', [AdminTenantController::class, 'edit'])->name('tenants.edit');

Route::post('/tenants/{tenant}/commission', [AdminCommissionController::class, 'store'])->name('tenants.commission.store');

Route::post('/tenants/{tenant}/bank', [AdminBankAccountController::class, 'store'])->name('tenants.bank.store');
Route::post('/tenants/{tenant}/bank/{account}/verify', [AdminBankAccountController::class, 'verify'])->name('tenants.bank.verify');
Route::post('/tenants/{tenant}/bank/{account}/primary', [AdminBankAccountController::class, 'makePrimary'])->name('tenants.bank.primary');

Route::post('/tenants/{tenant}/roles', [AdminTenantRoleController::class, 'store'])->name('tenants.roles.store');
Route::delete('/tenants/{tenant}/roles/{user}', [AdminTenantRoleController::class, 'destroy'])->name('tenants.roles.destroy');

Route::get('/tables', [AdminDiningTableController::class, 'index'])->name('tables.index');
Route::post('/tables', [AdminDiningTableController::class, 'store'])->name('tables.store');
Route::post('/tables/{table}/rotate', [AdminDiningTableController::class, 'rotate'])->name('tables.rotate');
Route::get('/tables/{table}/qr', [AdminDiningTableController::class, 'qr'])->name('tables.qr');
