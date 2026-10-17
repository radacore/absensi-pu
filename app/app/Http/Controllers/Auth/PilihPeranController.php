<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Halaman pemilihan peran untuk akun yang memegang dua peran.
 *
 * Satu kali login sudah membuka dua sesi (guard `web` dan `employee`).
 * Halaman ini hanya menanyakan mau masuk ke panel admin atau ke aplikasi
 * karyawan, dan sengaja selalu tampil setiap login supaya orang yang sama
 * tidak "terjebak" di peran yang tidak sedang ia butuhkan.
 */
class PilihPeranController extends Controller
{
    public function __invoke(Request $request)
    {
        $admin = Auth::guard('web')->user();
        $karyawan = Auth::guard('employee')->user();

        if (! $admin && ! $karyawan) {
            return redirect()->route('login');
        }

        // Hanya sesi karyawan: tidak ada yang perlu dipilih.
        if (! $admin) {
            return redirect()->route('karyawan.dashboard');
        }

        // Akun admin tanpa baris karyawan: langsung ke panelnya.
        if (! $karyawan) {
            return redirect($this->panel($admin));
        }

        if ($karyawan->must_change_password) {
            return redirect()
                ->route('karyawan.profil')
                ->with('error', 'Ganti kata sandi dulu sebelum memakai aplikasi.');
        }

        return Inertia::render('Auth/PilihPeran', [
            'admin' => [
                'nama' => $admin->name,
                'email' => $admin->email,
                'peran' => $admin->role === 'super_admin' ? 'Super Admin' : 'Admin Wilayah',
                'wilayah' => $admin->region?->name,
                'tujuan' => $this->panel($admin),
            ],
            'karyawan' => [
                'titik' => $karyawan->site?->nama_lokasi,
                'tujuan' => route('karyawan.dashboard', absolute: false),
            ],
        ]);
    }

    /**
     * Keluar dari kedua sesi sekaligus.
     *
     * Dipakai tombol "Keluar" di halaman pemilihan peran: orang yang salah
     * akun tidak boleh meninggalkan sesi setengah hidup.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        Auth::guard('employee')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function panel(User $admin): string
    {
        return $admin->role === 'super_admin' ? '/super-admin' : '/admin';
    }
}
