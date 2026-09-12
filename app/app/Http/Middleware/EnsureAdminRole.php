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

        if (! $user) {
            $login = str_starts_with($request->path(), 'super-admin')
                ? '/super-admin/login'
                : '/admin/login';

            return redirect($login);
        }

        $allowed = explode('|', $roles);

        if (! in_array($user->role, $allowed, true)) {
            $base = $user->role === 'super_admin' ? '/super-admin' : '/admin';

            return redirect($base);
        }

        return $next($request);
    }
}
