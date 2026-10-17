<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OvertimeClaim;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Persetujuan lembur (A8).
 *
 * Hanya pengajuan yang disetujui yang dihitung di rekap bulanan.
 */
class OvertimeController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();

        $rows = OvertimeClaim::with(['employee.region', 'employee.site', 'approver', 'approvedBy'])
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->orderByRaw("case when status = 'Menunggu' then 0 else 1 end")
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (OvertimeClaim $r) => [
                'id' => $r->id,
                'nama' => $r->employee?->name ?? '—',
                'nik' => $r->employee?->nik ?? '',
                'kantor' => $r->employee?->region?->name ?? '',
                'tanggal' => $r->tanggal instanceof Carbon ? $r->tanggal->toDateString() : (string) $r->tanggal,
                'jamMulai' => substr((string) $r->jam_mulai, 0, 5),
                'jamSelesai' => substr((string) $r->jam_selesai, 0, 5),
                'menit' => (int) $r->menit,
                'keterangan' => $r->keterangan,
                'approverNama' => $r->approver?->name ?? '',
                'status' => $r->status,
                'note' => $r->note ?? '',
                'diprosesOleh' => $r->approvedBy?->name ?? '',
            ])->values()->all();

        return Inertia::render('Admin/Lembur', [
            'requests' => $rows,
            'regions' => AdminPresenter::regionsFor($scope),
        ]);
    }

    public function approve(OvertimeClaim $lembur)
    {
        $this->authorizeRow($lembur);

        if ($lembur->status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa disetujui.');
        }

        $lembur->status = 'Disetujui';
        $lembur->approved_by = Auth::guard('web')->id();
        $lembur->save();

        $nama = $lembur->employee?->name ?? '-';
        Audit::log(
            'lembur.approve',
            subject: $lembur,
            label: "Lembur #{$lembur->id} {$nama}",
            description: "Setujui pengajuan lembur {$nama}",
            meta: ['tanggal' => $lembur->tanggal?->toDateString(), 'menit' => $lembur->menit]
        );

        return back()->with('success', 'Pengajuan lembur disetujui.');
    }

    public function reject(Request $request, OvertimeClaim $lembur)
    {
        $this->authorizeRow($lembur);

        if ($lembur->status !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan yang masih menunggu bisa ditolak.');
        }

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        $lembur->status = 'Ditolak';
        $lembur->note = $data['note'];
        $lembur->approved_by = Auth::guard('web')->id();
        $lembur->save();

        $nama = $lembur->employee?->name ?? '-';
        Audit::log(
            'lembur.reject',
            subject: $lembur,
            label: "Lembur #{$lembur->id} {$nama}",
            description: "Tolak pengajuan lembur {$nama}",
            meta: ['note' => $data['note'], 'tanggal' => $lembur->tanggal?->toDateString()]
        );

        return back()->with('success', 'Pengajuan lembur ditolak.');
    }

    private function authorizeRow(OvertimeClaim $lembur): void
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $lembur->region_id !== $scope, 403, 'Pengajuan ini di luar cakupan Anda.');
    }
}
