<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Audit;
use App\Support\RekapPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EmployeeRekapController extends Controller
{
    public function show(Request $request, Employee $employee)
    {
        $u = Auth::guard('web')->user();
        $scope = $u->role === 'super_admin' ? null : $u->region_id;
        abort_if($scope !== null && (int) $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $employee->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($employee, $year, $month);

        Audit::log(
            'employee.rekap.view',
            subject: $employee,
            label: $employee->name,
            description: "Buka rekap presensi {$employee->name} untuk {$rekap['periode']['label']}",
            meta: ['periode' => $bulan]
        );

        return Inertia::render('Admin/EmployeeRekap', [
            'rekap' => $rekap,
        ]);
    }
}
