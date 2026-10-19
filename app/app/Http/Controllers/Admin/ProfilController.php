<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Profil akun pengelola (Super Admin dan Admin Wilayah).
 *
 * Halaman ini hanya mengurus kata sandi akun sendiri. Akun yang tertaut ke
 * baris karyawan memakai kata sandi karyawan (lihat LoginController), jadi
 * perubahan di sini harus menulis ke baris karyawan — bukan ke users.password
 * yang memang sengaja diacak. Dengan begitu satu kata sandi tetap berlaku
 * untuk panel admin maupun aplikasi karyawan.
 */
class ProfilController extends Controller
{
    public function index()
    {
        $user = Auth::guard('web')->user()->loadMissing(['region', 'employee.region', 'employee.site']);
        $karyawan = $user->employee;

        return Inertia::render('Admin/Profil', [
            'me' => [
                'nama' => $user->name,
                'email' => $user->email,
                'peran' => $user->role === 'super_admin' ? 'Super Admin' : 'Admin Wilayah',
                'wilayah' => $user->region?->name ?? '',
                'tertaut' => $karyawan !== null,
                'karyawan' => $karyawan ? [
                    'nama' => $karyawan->name,
                    'nik' => $karyawan->nik,
                    'nip' => $karyawan->nip ?? '',
                    'jabatan' => $karyawan->jabatan,
                    'unit' => $karyawan->unit_kerja,
                    'titik' => $karyawan->site?->nama_lokasi ?? '',
                    'wilayah' => $karyawan->region?->name ?? '',
                ] : null,
            ],
            // Sama seperti formulir karyawan: sandi bawaan tidak boleh dipakai
            // lagi sebagai sandi baru.
            'sandiBawaan' => Employee::SANDI_BAWAAN,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::guard('web')->user();

        // Pemilik sandi mengikuti aturan login: akun tertaut memakai sandi
        // karyawan, akun mandiri memakai users.password.
        $pemilikSandi = $user->employee ?? $user;

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/\d/',
            ],
        ], [
            'password.confirmed' => 'Konfirmasi tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf besar, huruf kecil, dan angka.',
        ]);

        if (! Hash::check($data['current_password'], $pemilikSandi->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi lama salah.',
            ]);
        }

        if ($data['password'] === Employee::SANDI_BAWAAN) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi baru tidak boleh sama dengan sandi bawaan aplikasi.',
            ]);
        }

        $pemilikSandi->password = $data['password'];

        // Akun tertaut: penanda wajib ganti sandi dimatikan sekaligus, supaya
        // panel admin langsung terbuka tanpa perlu mampir ke aplikasi karyawan.
        if ($pemilikSandi instanceof Employee) {
            $pemilikSandi->must_change_password = false;
        }

        $pemilikSandi->save();

        Audit::log(
            'admin.change_password',
            subject: $user,
            label: $user->name,
            description: 'Ganti kata sandi sendiri dari halaman Profil panel admin',
            meta: [
                'role' => $user->role,
                'tertaut_karyawan' => $user->employee_id !== null,
                'employee_id' => $user->employee_id,
            ]
        );

        return back()->with('success', 'Kata sandi berhasil diperbarui');
    }
}
