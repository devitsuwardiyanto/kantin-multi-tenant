<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetTenantContext;
use App\Support\Routing\PortalRoutes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Route inti tiap portal (dashboard). Route fitur disumbangkan oleh modul lewat
            // app/Modules/{Modul}/routes/{portal}.php memakai grup PortalRoutes yang sama.
            // Modul 4: grup tenant mengikat {tenant:slug}, scopeBindings, dan resolver SetTenantContext.
            PortalRoutes::customer(base_path('routes/customer.php'));
            PortalRoutes::tenant(base_path('routes/tenant.php'));
            PortalRoutes::admin(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
