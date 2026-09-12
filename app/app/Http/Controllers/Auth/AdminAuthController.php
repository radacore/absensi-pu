<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Login akun admin (guard web) sesuai area yang dituju.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();
        $isSuper = str_starts_with($request->path(), 'super-admin');
        $needed = $isSuper ? ['super_admin'] : ['admin_wilayah'];

        if (! in_array($user->role, $needed, true)) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses ke area tersebut.',
            ]);
        }

        return redirect($isSuper ? '/super-admin' : '/admin');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
