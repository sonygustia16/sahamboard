<?php

use App\Http\Middleware\BasicAuthProtect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
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

        // Kunci seluruh web dengan Basic Auth (kecuali /up health check,
        // yang perlu tetap bisa diakses Railway tanpa auth untuk cek status).
        $middleware->web(append: [
            BasicAuthProtect::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
