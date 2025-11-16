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
    ->withMiddleware(function (Middleware $middleware): void {
        // Daftarin alias middleware
        $middleware->alias([
            'inactivity' => \App\Http\Middleware\InactivityTimeout::class,
        ]);
        
        // Enable CORS untuk React frontend
        // Gunakan custom CORS middleware untuk memastikan headers di-set dengan benar
        $middleware->api(prepend: [
            \App\Http\Middleware\CustomCors::class,
        ]);
        
        // Daftarin middleware groups untuk Laravel 12
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        
        // Tambahkan session middleware untuk API routes yang memerlukan session (registration)
        // CORS middleware harus dijalankan SEBELUM session middleware
        $middleware->group('api.session', [
            \App\Http\Middleware\CustomCors::class, // CORS harus di awal
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
