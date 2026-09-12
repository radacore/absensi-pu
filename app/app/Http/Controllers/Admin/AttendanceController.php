<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private function scopeRegion(): ?int
    {
        $user = Auth::guard('web')->user();

        return $user->role === 'super_admin' ? null : $user->region_id;
    }

    public function index(Request $request)
    {
        $scope = $this->scopeRegion();

        return Inertia::render('Admin/Attendances', [
            'regions' => AdminPresenter::regionsFor($scope),
            'attendances' => AdminPresenter::attendancesFor($scope),
            'settings' => AdminPresenter::settingsFor(),
        ]);
    }

    public function destroy(Attendance $attendance)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && (int) $attendance->region_id !== $scope, 403, 'Absensi di luar cakupan Anda.');

        $attendance->delete();

        return back()->with('success', 'Absensi dihapus.');
    }
}
