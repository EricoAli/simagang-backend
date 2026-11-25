<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.api' => \App\Http\Middleware\ApiAuthenticate::class,
            'token.check' => \App\Http\Middleware\TokenCheck::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'master-data.manager' => \App\Http\Middleware\EnsureMasterDataManager::class,
            'master-period.super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })


    /**
     * handling exception
     * if failed login then return unauthenticated isntead redirect to /login
     */
    ->withExceptions(function (Exceptions $exceptions) {

        // Tangani error "Unauthenticated"
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        });
    })

    ->create();

