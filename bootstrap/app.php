<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway men-terminasi HTTPS di edge proxy-nya dan meneruskan trafik
        // ke container secara internal via HTTP. Tanpa trust proxy ini, Laravel
        // tidak tahu request aslinya HTTPS — akibatnya redirect/url() bisa salah
        // jadi http://, dan cookie secure bisa gagal ke-set dengan benar.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        // Catatan: proteksi login sekarang ditangani oleh middleware
        // App\Http\Middleware\EnsureAuthenticated, dipasang per-grup route
        // di routes/web.php (bukan global) supaya halaman /login sendiri
        // tidak ikut ke-redirect (mencegah infinite redirect loop).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
