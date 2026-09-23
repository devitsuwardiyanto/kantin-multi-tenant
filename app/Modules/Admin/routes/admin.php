<?php

use App\Modules\Admin\Http\Controllers\AdminBankAccountController;
use App\Modules\Admin\Http\Controllers\AdminCommissionController;
use App\Modules\Admin\Http\Controllers\AdminTenantController;
use App\Modules\Admin\Http\Controllers\AdminTenantRoleController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal admin milik modul Admin: tenant, skema komisi, rekening, dan role anggota.
 * Middleware auth+verified+role:admin berasal dari PortalRoutes::admin(); policy per-aksi
 * (TenantPolicy) diperiksa di controller.
 */
PortalRoutes::admin(function (): void {
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
});
