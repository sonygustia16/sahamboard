<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci seluruh aplikasi pakai 1 username + password (HTTP Basic Auth).
 *
 * Ini BUKAN sistem login berbasis session/database — cocok untuk aplikasi
 * single-user seperti SahamBoard yang tidak butuh multi-akun, tapi tetap
 * perlu dikunci dari publik karena berisi data pribadi (watchlist, entry
 * plan, money management).
 *
 * Kredensial diambil dari environment variable, BUKAN di-hardcode:
 *   BASIC_AUTH_USER=...
 *   BASIC_AUTH_PASS=...
 *
 * Kalau salah satu env var itu kosong, middleware ini otomatis "mati"
 * (tidak memblokir apa pun) — supaya tidak tiba-tiba mengunci diri sendiri
 * kalau lupa set env var. Pastikan untuk selalu isi keduanya di production.
 */
class BasicAuthProtect
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = env('BASIC_AUTH_USER');
        $expectedPass = env('BASIC_AUTH_PASS');

        // Kalau belum dikonfigurasi, middleware tidak aktif (fail-open secara sadar,
        // supaya developer tidak ke-lock-out kalau env var belum diisi saat setup awal).
        if (empty($expectedUser) || empty($expectedPass)) {
            return $next($request);
        }

        $user = $request->getUser();
        $pass = $request->getPassword();

        $userMatch = $user !== null && hash_equals($expectedUser, $user);
        $passMatch = $pass !== null && hash_equals($expectedPass, $pass);

        if (!$userMatch || !$passMatch) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Basic realm="SahamBoard"',
            ]);
        }

        return $next($request);
    }
}
