<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Konteks pelanggan (publik, anonim). Model binding canteen di-wire pada Modul 4.
            Route::middleware('web')
                ->prefix('kantin/{canteen}')
                ->name('customer.')
                ->group(base_path('routes/customer.php'));

            // Konteks operator tenant (internal). Resolver mengikat {tenant:slug}, memeriksa
            // membership + status, lalu mengisi TenantContext. scopeBindings mengunci resource anak
            // di bawah tenant induk (Modul 4).
            Route::middleware(['web', 'auth', 'verified', 'tenant'])
                ->prefix('tenant/{tenant:slug}')
                ->name('tenant.')
                ->scopeBindings()
                ->group(base_path('routes/tenant.php'));

            // Konteks pengelola kantin (internal).
            Route::middleware(['web', 'auth', 'verified', 'role:admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => SetTenantContext::class,
        ]);

        // Token pelacakan pesanan = opaque 256-bit (disimpan sebagai hash SHA-256); enkripsi
        // cookie tak menambah kerahasiaan, sedangkan pelacakan stateless butuh nilai stabil.
        $middleware->encryptCookies(except: ['order_tracking']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
