<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAuth
{
    /**
     * Guard area karyawan: wajib login via guard `employee`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('employee')->check()) {
            return redirect('/karyawan/login');
        }

        return $next($request);
    }
}
