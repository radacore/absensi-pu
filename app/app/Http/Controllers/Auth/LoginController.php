<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Satu pintu masuk untuk admin dan karyawan.
 *
 * Dulu ada tiga halaman login terpisah dan orang yang memegang dua peran
 * harus punya dua akun dengan dua kata sandi. Sekarang cukup satu: akun admin
 * yang tertaut ke baris karyawan memakai kata sandi karyawan, dan formulir
 * yang sama melayani keduanya.
 */
class LoginController extends Controller
{
    public function create()
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            // Pemilik dua peran selalu ditanya dulu mau masuk sebagai apa,
            // sama seperti sesudah login. Hanya berlaku bila sesi karyawannya
            // juga masih hidup; kalau tidak, halaman pemilihan akan
            // mengalihkan balik ke panel dan terjadi pantulan tanpa ujung.
            if ($user->employee_id !== null && Auth::guard('employee')->check()) {
                return redirect('/pilih-peran');
            }

            return redirect($this->tujuanAdmin($user));
        }

        if (Auth::guard('employee')->check()) {
            return redirect()->route('karyawan.dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        // Formulir lama mengirim `login` (aplikasi karyawan) atau `email`
        // (panel admin). Satu pintu menerima keduanya, dan pesan galat pun
        // dikembalikan pada kolom yang benar-benar dikirim supaya PWA yang
        // masih terpasang di ponsel tetap bisa menampilkannya.
        $kunci = $request->filled('login') && ! $request->filled('email') ? 'login' : 'email';

        $data = $request->validate([
            $kunci => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            "{$kunci}.required" => 'Email wajib diisi.',
            "{$kunci}.email" => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $email = mb_strtolower(trim((string) $data[$kunci]), 'UTF-8');
        $ingat = $request->boolean('remember');

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $employee = Employee::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            return $this->masukSebagaiAdmin($request, $user, (string) $data['password'], $ingat, $kunci);
        }

        if ($employee) {
            return $this->masukSebagaiKaryawan($request, $employee, (string) $data['password'], $ingat, $kunci);
        }

        throw ValidationException::withMessages([
            $kunci => 'Email atau kata sandi salah.',
        ]);
    }

    /**
     * Akun admin. Bila tertaut ke karyawan, kata sandi yang berlaku adalah
     * kata sandi karyawan — kolom users.password sengaja tidak bisa dipakai.
     */
    private function masukSebagaiAdmin(Request $request, User $user, string $sandi, bool $ingat, string $kunci)
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                $kunci => 'Akun ini sudah dinonaktifkan.',
            ]);
        }

        $pemilikSandi = $user->employee ?? $user;

        if (! Hash::check($sandi, $pemilikSandi->password)) {
            throw ValidationException::withMessages([
                $kunci => 'Email atau kata sandi salah.',
            ]);
        }

        // Sandi bawaan tidak boleh menjadi kunci panel admin. Pemilik akun
        // diarahkan mengganti sandi dulu lewat aplikasi karyawan; sesi admin
        // belum dibuka sama sekali.
        if ($user->employee && $user->employee->must_change_password) {
            Auth::guard('employee')->login($user->employee, $ingat);
            $request->session()->regenerate();

            return redirect()
                ->route('karyawan.profil')
                ->with('error', 'Ganti kata sandi dulu sebelum membuka panel admin.');
        }

        Auth::guard('web')->login($user, $ingat);

        // Pemilik dua peran langsung mendapat sesi karyawan juga, jadi menu
        // absensi dan cuti bisa dibuka tanpa login ulang.
        if ($user->employee) {
            Auth::guard('employee')->login($user->employee, $ingat);
        }

        $request->session()->regenerate();

        // Dua peran: berhenti di halaman pemilihan, bukan langsung ke panel.
        if ($user->employee) {
            return redirect('/pilih-peran');
        }

        return redirect($this->tujuanAdmin($user));
    }

    private function masukSebagaiKaryawan(Request $request, Employee $employee, string $sandi, bool $ingat, string $kunci)
    {
        if (! Hash::check($sandi, $employee->password)) {
            throw ValidationException::withMessages([
                $kunci => 'Email atau kata sandi salah.',
            ]);
        }

        Auth::guard('employee')->login($employee, $ingat);
        $request->session()->regenerate();

        return redirect()->route('karyawan.dashboard');
    }

    private function tujuanAdmin(User $user): string
    {
        return $user->role === 'super_admin' ? '/super-admin' : '/admin';
    }
}
