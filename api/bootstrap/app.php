<?php

use App\Http\Middleware\EnsureBusinessPermission;
use App\Http\Middleware\EnsurePlatformStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\ResolveAdminTenant;
use App\Http\Middleware\ResolveSiteTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'feature' => RequireFeature::class,
            'tenant.site' => ResolveSiteTenant::class,
            'tenant.admin' => ResolveAdminTenant::class,
            'super.admin' => EnsureSuperAdmin::class,
            'platform.staff' => EnsurePlatformStaff::class,
            'business.can' => EnsureBusinessPermission::class,
        ]);

        // Route model binding ({lead}, {post:slug}...) must already see the
        // request's tenant, otherwise it would look rows up in the default
        // one. Priority puts both right before SubstituteBindings (and so
        // after authentication, which the admin one needs).
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveSiteTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveAdminTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
