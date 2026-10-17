<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\AdminPresenter;
use App\Support\Audit;
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
            // Tanggal acuan filter dihitung di server (WITA), bukan di browser.
            'todayISO' => now('Asia/Makassar')->toDateString(),
        ]);
    }

    public function destroy(Attendance $attendance)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && (int) $attendance->region_id !== $scope, 403, 'Absensi di luar cakupan Anda.');

        $attendance->delete();

        return back()->with('success', 'Absensi dihapus.');
    }

    /**
     * D5: koreksi catatan pekerjaan absen pulang.
     *
     * Karyawan tidak bisa mengubah catatannya sendiri setelah tersimpan —
     * hanya admin dalam cakupannya yang boleh membetulkan salah tulis.
     */
    public function updateCatatan(Request $request, Attendance $attendance)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && (int) $attendance->region_id !== $scope, 403, 'Absensi di luar cakupan Anda.');

        $data = $request->validate([
            'catatan_pulang' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $lama = (string) ($attendance->catatan_pulang ?? '');
        $attendance->update(['catatan_pulang' => $data['catatan_pulang']]);

        Audit::log(
            'attendance.catatan_update',
            subject: $attendance,
            label: $attendance->employee?->name,
            description: 'Koreksi catatan pekerjaan absen pulang',
            meta: [
                'employee_id' => $attendance->employee_id,
                'work_date' => $attendance->work_date instanceof \DateTimeInterface
                    ? $attendance->work_date->format('Y-m-d')
                    : (string) $attendance->work_date,
                'lama' => $lama,
                'baru' => $data['catatan_pulang'],
            ]
        );

        return back()->with('success', 'Catatan pekerjaan diperbarui.');
    }
}
