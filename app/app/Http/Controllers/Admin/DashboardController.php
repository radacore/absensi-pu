<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\AdminPresenter;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $scope = Auth::guard('web')->user()->role === 'super_admin' ? null : Auth::guard('web')->user()->region_id;

        $today = now('Asia/Makassar')->toDateString();

        $attendToday = Attendance::with('employee')
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->whereDate('work_date', $today)
            ->get();

        // also provide base lists for client filtering (small scale < 500)
        $allAttendances = AdminPresenter::attendancesFor($scope);
        $employees = Employee::when($scope, fn ($q) => $q->where('region_id', $scope))->get(['id', 'region_id', 'site_id']);

        return Inertia::render('Admin/Dashboard', [
            'regions' => AdminPresenter::regionsFor($scope),
            'employees' => AdminPresenter::employeesFor($scope),
            'attendances' => $allAttendances,
            'settings' => AdminPresenter::settingsFor(),
            'cuti' => [],            // placeholder Fase 3
            'love' => [],            // placeholder Fase 3
        ]);
    }
}
