<?php

use App\Core\Middleware\FacilityScopeMiddleware;
use App\Core\Middleware\TenantContextMiddleware;
use App\Http\Middleware\BreakGlassScope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionIdleTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TenantContext/FacilityScope/BreakGlassScope must run BEFORE SubstituteBindings —
        // implicit route-model binding (e.g. {patient}, {invoice}) queries
        // the database as soon as it resolves, and every tenant-scoped
        // model's BelongsToTenant global scope reads TenantContext to
        // decide what to filter by. Registering these via a plain
        // `append` (as this previously did) puts them AFTER
        // SubstituteBindings in the default web group, which meant every
        // route using implicit binding resolved with no tenant scope
        // applied at all. Removing SubstituteBindings from its default
        // position and re-appending it after these two closes that gap.
        $middleware->web(
            remove: [SubstituteBindings::class],
            prepend: [
                SecurityHeaders::class,
            ],
            append: [
                TenantContextMiddleware::class,
                FacilityScopeMiddleware::class,
                BreakGlassScope::class,
                SubstituteBindings::class,
                SessionIdleTimeout::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
