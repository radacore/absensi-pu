<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons.
 *
 * Content-Security-Policy hanya dipasang saat debug mati (produksi). Di mode
 * dev Vite menyuntikkan skrip inline (preamble HMR) yang akan diblokir CSP,
 * sedangkan pada hasil `npm run build` semua skrip/gaya keluar sebagai berkas
 * sendiri.
 *
 * `style-src` tetap butuh 'unsafe-inline': React dan Leaflet menulis atribut
 * style langsung ke elemen, bukan lewat berkas. Karena itu skrip tetap dibatasi
 * ketat ('self') — justru di situ XSS dieksekusi.
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

        // Service worker ada di /build/sw.js, sedangkan cakupannya harus root
        // supaya halaman di luar /build/ tetap terkontrol (prompt "Install
        // aplikasi" bergantung padanya). Baris ini hanya berlaku bila berkasnya
        // dilayani lewat PHP; pada Nginx/Apache berkas statis dilayani langsung
        // sehingga header harus dipasang di konfigurasi server — lihat
        // docs/panduan-deploy-keamanan.md bagian "Service worker".
        if ($request->is('build/sw.js')) {
            $headers->set('Service-Worker-Allowed', '/');
        }

        // CSP dipasang saat debug mati — yaitu saat aplikasi tidak dilayani
        // Vite dev server. Vite dev menyuntikkan skrip inline (preamble HMR)
        // yang pasti diblokir CSP; pada hasil `npm run build` semua skrip dan
        // gaya keluar sebagai berkas terpisah.
        if (! config('app.debug')) {
            // img-src: data:/blob: untuk swafoto & grafik PDF, tile OSM untuk peta.
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https://*.tile.openstreetmap.org",
                "font-src 'self' data:",
                "connect-src 'self'",
                "worker-src 'self'",
                "manifest-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        if ($request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
