<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Guard area admin: wajib login via guard `web` dan role sesuai prefix URL.
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = Auth::guard('web')->user();

        $login = str_starts_with($request->path(), 'super-admin')
            ? '/super-admin/login'
            : '/admin/login';

        if (! $user) {
            return redirect($login);
        }

        // Akun yang dinonaktifkan tidak boleh terus memakai sesi yang masih hidup.
        // Login memang sudah menolak akun nonaktif, tetapi tanpa pemeriksaan di
        // sini sesi lama tetap berlaku sampai pengguna logout sendiri.
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect($login)->withErrors([
                'email' => 'Akun ini sudah dinonaktifkan.',
            ]);
        }

        $allowed = explode('|', $roles);

        if (! in_array($user->role, $allowed, true)) {
            $base = $user->role === 'super_admin' ? '/super-admin' : '/admin';

            return redirect($base);
        }

        // Admin wilayah WAJIB punya cakupan wilayah.
        //
        // `users.region_id` nullable (super admin tidak punya wilayah). Bila akun
        // admin_wilayah dibiarkan bernilai NULL, setiap perhitungan cakupan di
        // controller mengembalikan null — dan null diperlakukan sama seperti super
        // admin, sehingga seluruh filter wilayah mati dan akun itu bisa melihat
        // data semua wilayah. Lebih aman menolaknya di satu tempat ini.
        if ($user->role === 'admin_wilayah' && ! $user->region_id) {
            abort(403, 'Akun admin wilayah ini belum memiliki cakupan wilayah. Hubungi Super Admin.');
        }

        return $next($request);
    }
}
