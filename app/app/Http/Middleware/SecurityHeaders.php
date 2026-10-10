<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons.
 *
 * Tidak memakai Content-Security-Policy: aplikasi memuat bundle Vite + inline
 * style dari React, jadi CSP ketat akan mematahkan halaman tanpa nonce.
 * Header di bawah ini aman untuk semuanya dan menutup kelas serangan umum
 * (MIME sniffing, clickjacking, referrer bocor, kamera/mikrofon diizinkan
 * sembarangan).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // camera=(self) wajib: absensi memakai getUserMedia untuk swafoto.
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
