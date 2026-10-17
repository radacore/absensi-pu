<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Persetujuan WFA (C4).
 *
 * WFA dicatat karyawan saat absen masuk, lalu Admin Wilayah menyetujui atau
 * menolaknya. Absennya sendiri tidak dihapus saat ditolak — keputusan dan
 * alasannya tetap terbaca di rekap.
 */
class WfaController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();

        $rows = Attendance::with(['employee.region', 'employee.site', 'site', 'wfaApprovedBy'])
            ->where('jenis_kerja', 'WFA')
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->orderByRaw("case when wfa_status = 'Menunggu' then 0 else 1 end")
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Attendance $a) => [
                'id' => $a->id,
                'nama' => $a->employee?->name ?? '—',
                'nik' => $a->employee?->nik ?? '',
                'kantor' => $a->employee?->region?->name ?? '',
                'lokasi' => $a->site?->nama_lokasi ?? $a->employee?->site?->nama_lokasi ?? '',
                'tanggal' => $a->work_date instanceof Carbon
                    ? $a->work_date->toDateString()
                    : (string) $a->work_date,
                'jamMasuk' => $a->clock_in_at ? Carbon::parse($a->clock_in_at)->format('H:i') : '',
                'alasan' => $a->wfa_alasan ?? '',
                'koordinat' => ($a->lat_in !== null && $a->lng_in !== null)
                    ? sprintf('%.6f, %.6f', (float) $a->lat_in, (float) $a->lng_in)
                    : '',
                'status' => $a->wfa_status ?: 'Menunggu',
                'note' => $a->wfa_note ?? '',
                'diprosesOleh' => $a->wfaApprovedBy?->name ?? '',
            ])->values()->all();

        return Inertia::render('Admin/Wfa', [
            'requests' => $rows,
            'regions' => AdminPresenter::regionsFor($scope),
        ]);
    }

    public function approve(Attendance $attendance)
    {
        $this->authorizeRow($attendance);

        if (! $attendance->isWfa()) {
            return back()->with('error', 'Baris ini bukan pengajuan WFA.');
        }
        if ($attendance->wfa_status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa disetujui.');
        }

        $attendance->wfa_status = 'Disetujui';
        $attendance->wfa_approved_by = Auth::guard('web')->id();
        $attendance->wfa_approved_at = now();
        $attendance->save();

        $nama = $attendance->employee?->name ?? '-';
        Audit::log(
            'wfa.approve',
            subject: $attendance,
            label: "WFA #{$attendance->id} {$nama}",
            description: "Setujui pengajuan WFA {$nama}",
            meta: ['tanggal' => $attendance->work_date?->toDateString()]
        );

        return back()->with('success', 'Pengajuan WFA disetujui.');
    }

    public function reject(Request $request, Attendance $attendance)
    {
        $this->authorizeRow($attendance);

        if (! $attendance->isWfa()) {
            return back()->with('error', 'Baris ini bukan pengajuan WFA.');
        }
        if ($attendance->wfa_status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa ditolak.');
        }

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        $attendance->wfa_status = 'Ditolak';
        $attendance->wfa_note = $data['note'];
        $attendance->wfa_approved_by = Auth::guard('web')->id();
        $attendance->wfa_approved_at = now();
        $attendance->save();

        $nama = $attendance->employee?->name ?? '-';
        Audit::log(
            'wfa.reject',
            subject: $attendance,
            label: "WFA #{$attendance->id} {$nama}",
            description: "Tolak pengajuan WFA {$nama}",
            meta: ['note' => $data['note'], 'tanggal' => $attendance->work_date?->toDateString()]
        );

        return back()->with('success', 'Pengajuan WFA ditolak.');
    }

    private function authorizeRow(Attendance $attendance): void
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $attendance->region_id !== $scope, 403, 'Pengajuan ini di luar cakupan Anda.');
    }
}
