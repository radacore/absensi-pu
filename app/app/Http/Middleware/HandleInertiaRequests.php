<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => Auth::guard('web')->check()
                    ? Auth::guard('web')->user()->only(['id', 'name', 'email', 'role', 'region_id', 'site_id'])
                    : null,
                'employee' => fn () => Auth::guard('employee')->check()
                    ? Auth::guard('employee')->user()->only(['id', 'nama', 'nik', 'nip', 'jabatan', 'region_id', 'site_id'])
                    : null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
