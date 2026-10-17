<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keluar dari aplikasi karyawan.
 *
 * Masuk ditangani satu pintu bersama: App\Http\Controllers\Auth\LoginController.
 */
class EmployeeAuthController extends Controller
{
    public function destroy(Request $request)
    {
        Auth::guard('employee')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
