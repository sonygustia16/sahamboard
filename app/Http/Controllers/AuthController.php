<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login sederhana single-akun (bukan sistem multi-user).
 * Kredensial diambil dari .env (BASIC_AUTH_USER / BASIC_AUTH_PASS_HASH),
 * bukan dari tabel `users` — tidak ada pendaftaran akun baru.
 *
 * Kalau nanti butuh multi-user beneran, ini tinggal diganti pakai
 * Laravel Breeze/Fortify yang baca dari tabel users.
 */
class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        if ($request->session()->get('sahamboard_authenticated') === true) {
            return redirect()->intended('/');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $expectedUser = env('BASIC_AUTH_USER');
        $expectedPassHash = env('BASIC_AUTH_PASS_HASH');

        // Fallback: kalau BASIC_AUTH_PASS_HASH belum di-set tapi BASIC_AUTH_PASS (plain) ada,
        // tetap bisa jalan (memudahkan migrasi dari setup Basic Auth sebelumnya).
        $expectedPassPlain = env('BASIC_AUTH_PASS');

        $userMatch = $expectedUser && hash_equals($expectedUser, (string) $request->input('username'));

        $passMatch = false;
        if ($expectedPassHash) {
            $passMatch = Hash::check($request->input('password'), $expectedPassHash);
        } elseif ($expectedPassPlain) {
            $passMatch = hash_equals($expectedPassPlain, (string) $request->input('password'));
        }

        if (!$userMatch || !$passMatch) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('sahamboard_authenticated', true);

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('sahamboard_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
