<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa ganti kata sandi.
 *
 * Karyawan yang baru dibuat / direset Admin Wilayah memakai NIK sebagai kata
 * sandi awal (lihat EmployeeController). Tanpa gerbang ini, flag
 * `must_change_password` hanya menjadi hiasan: karyawan bisa memakai NIK
 * selamanya. Middleware ini mengalihkan semua halaman karyawan ke halaman
 * profil sampai kata sandi diganti — hanya profil, ganti sandi, dan logout
 * yang tetap boleh diakses.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $me = Auth::guard('employee')->user();

        if ($me && $me->must_change_password) {
            // Halaman yang tetap boleh dibuka agar karyawan bisa mengganti
            // sandi dan keluar. Semua route karyawan.profil* termasuk form
            // ganti sandi dan unggah foto.
            if (! $request->routeIs('karyawan.profil*', 'karyawan.logout')) {
                return redirect()
                    ->route('karyawan.profil')
                    ->with('error', 'Wajib ganti kata sandi dulu sebelum memakai aplikasi.');
            }
        }

        return $next($request);
    }
}
