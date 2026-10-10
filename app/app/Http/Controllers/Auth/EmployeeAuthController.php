<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeAuthController extends Controller
{
    /**
     * Login karyawan (guard employee) memakai email saja.
     * NIP/NIK tidak lagi diterima sebagai kredensial login.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Pencarian case-insensitive agar Andi@x.id == andi@x.id,
        // konsisten di MySQL (collation _ci) maupun SQLite (tes).
        $employee = Employee::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['login']), 'UTF-8')])
            ->first();

        if (! $employee || ! Hash::check($data['password'], $employee->password)) {
            throw ValidationException::withMessages([
                'login' => 'Email atau kata sandi salah.',
            ]);
        }

        Auth::guard('employee')->login($employee, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect('/karyawan');
    }

    public function destroy(Request $request)
    {
        Auth::guard('employee')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/karyawan/login');
    }
}
