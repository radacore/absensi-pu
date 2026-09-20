<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DinasClaim;
use App\Support\AdminPresenter;
use App\Support\Audit;
use App\Support\StreamsDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DinasController extends Controller
{
    use StreamsDokumen;

    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();

        $list = DinasClaim::with('employee.region')
            ->when($scope, fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('region_id', $scope)))
            ->orderByDesc('created_at')
            ->limit(500)->get()
            ->map(function (DinasClaim $d) {
                $emp = $d->employee;
                $mulai = $d->tanggal_mulai->format('Y-m-d');
                $selesai = $d->tanggal_selesai->format('Y-m-d');
                $tglLabel = $mulai === $selesai
                    ? Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                    : Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').' – '.Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');

                return [
                    'id' => $d->id,
                    'employee_id' => $d->employee_id,
                    'nama' => $emp?->name ?? '-',
                    'email' => $emp?->email ?? '',
                    'wilayah' => $emp?->region?->name ?? '',
                    'nomor_surat' => $d->nomor_surat,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'tglLabel' => $tglLabel,
                    'keterangan' => $d->keterangan,
                    'tujuan' => $d->tujuan,
                    'transportasi' => $d->transportasi,
                    'pembebanan_anggaran' => $d->pembebanan_anggaran,
                    'status' => $d->status,
                    'note' => $d->note,
                    'tanggal_pengajuan' => $d->tanggal_pengajuan->format('Y-m-d'),
                    'dokumen_nama' => $d->dokumen_nama,
                    'dokumen_size' => $d->dokumenSizeLabel(),
                    'dokumen_is_image' => $d->dokumenIsImage(),
                ];
            })->all();

        return Inertia::render('Admin/Dinas', [
            'regions' => AdminPresenter::regionsFor($scope),
            'list' => $list,
        ]);
    }

    public function approve(DinasClaim $dinas)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $dinas->employee?->region_id !== $scope, 403);
        if ($dinas->status !== 'Menunggu') {
            return back()->with('error', 'Hanya yang Menunggu bisa di-approve.');
        }
        $dinas->update(['status' => 'Disetujui']);

        Audit::log(
            'dinas.approve',
            subject: $dinas,
            label: "Dinas #{$dinas->id} {$dinas->employee?->name}",
            description: "Approve dinas {$dinas->employee?->name} ke {$dinas->tujuan}",
            meta: ['nomor_surat' => $dinas->nomor_surat]
        );

        return back()->with('success', 'Dinas disetujui.');
    }

    public function reject(Request $request, DinasClaim $dinas)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $dinas->employee?->region_id !== $scope, 403);
        if ($dinas->status !== 'Menunggu') {
            return back()->with('error', 'Hanya yang Menunggu bisa ditolak.');
        }
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $dinas->update(['status' => 'Ditolak', 'note' => $data['note']]);

        Audit::log(
            'dinas.reject',
            subject: $dinas,
            label: "Dinas #{$dinas->id} {$dinas->employee?->name}",
            description: "Tolak dinas {$dinas->employee?->name}",
            meta: ['note' => $data['note']]
        );

        return back()->with('success', 'Dinas ditolak.');
    }

    public function destroy(DinasClaim $dinas)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $dinas->employee?->region_id !== $scope, 403);
        $empName = $dinas->employee?->name ?? '-';
        $snap = ['nomor_surat' => $dinas->nomor_surat, 'status' => $dinas->status, 'tujuan' => $dinas->tujuan];

        if ($dinas->hasDokumen()) {
            Storage::disk(config('filesystems.uploads.private_disk'))->delete($dinas->dokumen_path);
        }

        $dinas->delete();

        Audit::log(
            'dinas.delete',
            subject: null,
            label: "Dinas {$empName}",
            description: "Hapus pengajuan dinas {$empName}",
            meta: $snap
        );

        return back()->with('success', 'Dinas dihapus.');
    }

    /** Pratinjau / unduh dokumen pendukung — dibatasi wilayah admin. */
    public function dokumen(DinasClaim $dinas)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $dinas->employee?->region_id !== $scope, 403);
        abort_unless($dinas->hasDokumen(), 404);

        return $this->streamDokumen($dinas);
    }
}
