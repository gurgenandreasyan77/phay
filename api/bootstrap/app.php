<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AllowSiteOrigins;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureBusinessPermission;
use App\Http\Middleware\EnsurePlatformStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
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
        // The admin's server (BFF) and the host's nginx sit in front of the
        // API: trust their X-Forwarded-For so sign-in limits and the security
        // log see the visitor's IP, not theirs. Only private networks, so a
        // visitor can't fake it from outside.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        // Before HandleCors, which is part of the global stack.
        $middleware->prepend(AllowSiteOrigins::class);

        // Outermost, so every answer (errors and CORS preflights included)
        // carries the request's id.
        $middleware->prepend(AssignRequestId::class);

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
            'two-factor' => EnsureTwoFactorEnrolled::class,
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
            fn (Request $request) => ApiExceptionRenderer::wantsJson($request),
        );

        // One JSON shape for every API error (code, message, request id;
        // file, line and trace only with APP_DEBUG), see ApiExceptionRenderer.
        $exceptions->render(
            fn (Throwable $e, Request $request) => (new ApiExceptionRenderer((bool) config('app.debug')))($e, $request),
        );
    })->create();
