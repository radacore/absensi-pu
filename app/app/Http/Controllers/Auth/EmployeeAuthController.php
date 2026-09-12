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
     * Login karyawan (guard employee) memakai NIP atau NIK.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $employee = Employee::where('nip', $data['login'])
            ->orWhere('nik', $data['login'])
            ->first();

        if (! $employee || ! Hash::check($data['password'], $employee->password)) {
            throw ValidationException::withMessages([
                'login' => 'NIP/NIK atau kata sandi salah.',
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
