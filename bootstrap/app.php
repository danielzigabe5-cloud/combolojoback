<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // CORS ማዋቀር
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // 🆕 Middleware aliases — ሁሉም በአንድ ቦታ
        $middleware->alias([
            'owner'   => \App\Http\Middleware\OwnerMiddleware::class,
            'admin'   => \App\Http\Middleware\AdminMiddleware::class,
            'user'    => \App\Http\Middleware\UserMiddleware::class,
            'partner' => \App\Http\Middleware\PartnerMiddleware::class, // 🆕
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();