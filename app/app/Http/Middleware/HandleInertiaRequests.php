<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
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
            'notifications' => function () {
                $employee = Auth::guard('employee')->user();

                return [
                    'unreadAnnouncements' => $employee ? Announcement::where(fn ($query) => $query
                        ->where('scope', 'Global')
                        ->when($employee->region_id !== null, fn ($query) => $query
                            ->orWhere(fn ($query) => $query
                                ->where('scope', 'Wilayah')
                                ->where('region_id', $employee->region_id))))
                        ->whereDoesntHave('reads', fn ($query) => $query->where('employee_id', $employee->id))
                        ->count() : 0,
                ];
            },
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'reset_password' => $request->session()->get('reset_password'),
            ],
        ]);
    }
}
