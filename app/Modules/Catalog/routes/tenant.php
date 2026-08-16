<?php

use App\Modules\Catalog\Http\Controllers\TenantMenuController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Catalog. {menu} ter-scope di bawah {tenant} (scopeBindings
 * dari PortalRoutes) dan difilter global scope TenantContext.
 */
PortalRoutes::tenant(function (): void {
    Route::get('/menus', [TenantMenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/{menu}', [TenantMenuController::class, 'show'])->name('menus.show');
    Route::patch('/menus/{menu}', [TenantMenuController::class, 'update'])->name('menus.update');
});
