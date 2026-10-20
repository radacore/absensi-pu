<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EarlyLeaveRequest;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Persetujuan pulang cepat (P2/P5).
 *
 * Pekerja shift tidak bisa absen pulang sebelum jam shift berakhir kecuali
 * pengajuannya disetujui di sini.
 */
class EarlyLeaveController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    /** Admin wilayah hanya melihat pengajuan yang ditujukan kepadanya. */
    private function approverScope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : (int) $u->id;
    }

    public function index()
    {
        $scope = $this->scope();
        $approver = $this->approverScope();

        $rows = EarlyLeaveRequest::with(['employee.region', 'employee.site', 'approver', 'approvedBy'])
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->when($approver, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('approver_id', $approver)
                ->orWhereNull('approver_id')))
            ->orderByRaw("case when status = 'Menunggu' then 0 else 1 end")
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (EarlyLeaveRequest $r) => [
                'id' => $r->id,
                'nama' => $r->employee?->name ?? '—',
                'nik' => $r->employee?->nik ?? '',
                'kantor' => $r->employee?->region?->name ?? '',
                'lokasi' => $r->employee?->site?->nama_lokasi ?? '',
                'tanggal' => $r->tanggal instanceof \Illuminate\Support\Carbon
                    ? $r->tanggal->toDateString()
                    : (string) $r->tanggal,
                'jamRencana' => substr((string) $r->jam_rencana, 0, 5),
                'alasan' => $r->alasan,
                'approverNama' => $r->approver?->name ?? '',
                'status' => $r->status,
                'note' => $r->note ?? '',
                'diprosesOleh' => $r->approvedBy?->name ?? '',
            ])->values()->all();

        return Inertia::render('Admin/PulangAwal', [
            'requests' => $rows,
            'regions' => AdminPresenter::regionsFor($scope),
        ]);
    }

    public function approve(Request $request, EarlyLeaveRequest $pulangCepat)
    {
        $this->authorizeRow($pulangCepat);

        if ($pulangCepat->status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa disetujui.');
        }

        $pulangCepat->status = 'Disetujui';
        $pulangCepat->approved_by = Auth::guard('web')->id();
        $pulangCepat->save();

        $nama = $pulangCepat->employee?->name ?? '-';
        Audit::log(
            'pulang_cepat.approve',
            subject: $pulangCepat,
            label: "Pulang cepat #{$pulangCepat->id} {$nama}",
            description: "Setujui pengajuan pulang cepat {$nama}",
            meta: [
                'tanggal' => (string) $pulangCepat->tanggal,
                'jam_rencana' => (string) $pulangCepat->jam_rencana,
            ]
        );

        return back()->with('success', 'Pengajuan pulang cepat disetujui.');
    }

    public function reject(Request $request, EarlyLeaveRequest $pulangCepat)
    {
        $this->authorizeRow($pulangCepat);

        if ($pulangCepat->status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa ditolak.');
        }

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        $pulangCepat->status = 'Ditolak';
        $pulangCepat->note = $data['note'];
        $pulangCepat->approved_by = Auth::guard('web')->id();
        $pulangCepat->save();

        $nama = $pulangCepat->employee?->name ?? '-';
        Audit::log(
            'pulang_cepat.reject',
            subject: $pulangCepat,
            label: "Pulang cepat #{$pulangCepat->id} {$nama}",
            description: "Tolak pengajuan pulang cepat {$nama}",
            meta: ['note' => $data['note'], 'tanggal' => (string) $pulangCepat->tanggal]
        );

        return back()->with('success', 'Pengajuan pulang cepat ditolak.');
    }

    private function authorizeRow(EarlyLeaveRequest $pulangCepat): void
    {
        $u = Auth::guard('web')->user();
        $scope = $u->role === 'super_admin' ? null : $u->region_id;

        abort_if($scope !== null && (int) $pulangCepat->region_id !== $scope, 403, 'Pengajuan ini di luar cakupan Anda.');

        if ($u->role !== 'super_admin' && $pulangCepat->approver_id && (int) $pulangCepat->approver_id !== (int) $u->id) {
            abort(403, 'Pengajuan ini ditugaskan ke approver lain.');
        }
    }
}
