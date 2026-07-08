<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ganti dari HTTP Basic Auth (popup browser) ke session-based login
 * dengan halaman login sendiri. Fail-open kalau BASIC_AUTH_USER belum
 * di-set sama sekali, supaya tidak ke-lock-out saat setup awal.
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = !empty(env('BASIC_AUTH_USER'))
            && (!empty(env('BASIC_AUTH_PASS_HASH')) || !empty(env('BASIC_AUTH_PASS')));

        if (!$configured) {
            return $next($request);
        }

        if ($request->session()->get('sahamboard_authenticated') !== true) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
