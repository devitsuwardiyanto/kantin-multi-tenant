<?php

namespace App\Support\Routing;

use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Definisi TUNGGAL grup route per portal (prefix + name + middleware). Dipakai oleh
 * routes/{customer,tenant,admin}.php (via bootstrap/app.php) DAN oleh file route milik modul
 * (app/Modules/{Modul}/routes/{portal}.php), sehingga semua route satu portal selalu memakai
 * lapis keamanan yang sama.
 */
final class PortalRoutes
{
    /**
     * Route publik TANPA prefix portal (mis. entry QR meja /q/{token}, webhook provider). Hanya
     * grup `web`; lapis tambahan (throttle, signature) dipasang per route oleh modul pemiliknya.
     */
    public static function web(Closure|string $routes): void
    {
        Route::middleware('web')->group($routes);
    }

    /**
     * Portal pelanggan (publik, anonim).
     */
    public static function customer(Closure|string $routes): void
    {
        Route::middleware('web')
            ->prefix('kantin/{canteen}')
            ->name('customer.')
            ->group($routes);
    }

    /**
     * Portal operator tenant (internal). Resolver SetTenantContext (alias `tenant`) mengikat
     * {tenant:slug}, memeriksa membership + status, lalu mengisi TenantContext; scopeBindings
     * mengunci resource anak di bawah tenant induk (Modul 4).
     */
    public static function tenant(Closure|string $routes): void
    {
        Route::middleware(['web', 'auth', 'verified', 'tenant'])
            ->prefix('tenant/{tenant:slug}')
            ->name('tenant.')
            ->scopeBindings()
            ->group($routes);
    }

    /**
     * Portal pengelola kantin (internal).
     */
    public static function admin(Closure|string $routes): void
    {
        Route::middleware(['web', 'auth', 'verified', 'role:admin'])
            ->prefix('admin')
            ->name('admin.')
            ->group($routes);
    }
}
