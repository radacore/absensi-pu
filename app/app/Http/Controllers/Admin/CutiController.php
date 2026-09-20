<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CutiController extends Controller
{
    /** Peran yang boleh menyetujui cuti — kebijakan: "admin saja". */
    private const APPROVER_ROLES = ['super_admin', 'admin_wilayah'];

    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    /**
     * Gerbang approver: hanya akun admin, dan admin wilayah hanya untuk
     * cuti di wilayahnya sendiri. Dipakai approve() dan reject().
     */
    private function ensureApprover(Leave $cuti): void
    {
        $u = Auth::guard('web')->user();
        abort_unless(
            in_array($u->role, self::APPROVER_ROLES, true),
            403,
            'Hanya akun admin yang boleh menyetujui cuti.'
        );

        $scope = $u->role === 'super_admin' ? null : $u->region_id;
        abort_if($scope !== null && (int) $cuti->employee?->region_id !== $scope, 403);

        // Kalau karyawan menunjuk approver tertentu, hanya dia (atau Super Admin) yang boleh level 1.
        if ((int) $cuti->level === 0 && $cuti->approver_id && $u->role !== 'super_admin') {
            abort_if((int) $cuti->approver_id !== (int) $u->id, 403, 'Cuti ini ditugaskan ke approver lain.');
        }
    }

    public function index()
    {
        $scope = $this->scope();

        return Inertia::render('Admin/Cuti', [
            'regions' => AdminPresenter::regionsFor($scope),
            'list' => AdminPresenter::leavesFor($scope),
        ]);
    }

    public function show(int $id)
    {
        $scope = $this->scope();
        $leave = Leave::with(['employee.region'])->findOrFail($id);
        $empRegion = $leave->employee?->region_id;
        abort_if($scope !== null && (int) $empRegion !== $scope, 403, 'Cuti di luar cakupan Anda.');

        return Inertia::render('Admin/CutiDetail', [
            'id' => $leave->id,
            'regions' => AdminPresenter::regionsFor($scope),
            'item' => AdminPresenter::leavesFor(null) ? collect(AdminPresenter::leavesFor(null))->firstWhere('id', $leave->id) : null,
            'list' => AdminPresenter::leavesFor($scope),
        ]);
    }

    public function approve(Leave $cuti)
    {
        $this->ensureApprover($cuti);
        if ($cuti->status !== 'Menunggu') {
            return back()->with('error', 'Hanya yang Menunggu bisa di-approve.');
        }

        $a = Auth::guard('web')->user();
        $next = (int) $cuti->level + 1;
        $cuti->level = $next;
        $cuti->status = $next >= 3 ? 'Disetujui' : 'Menunggu';
        $cuti->approved_by = $a->id;
        $cuti->save();

        $empName = $cuti->employee?->name ?? '-';
        Audit::log(
            $next >= 3 ? 'cuti.approve_final' : 'cuti.approve_level',
            subject: $cuti,
            label: "Cuti #{$cuti->id} {$empName}",
            description: $next >= 3
                ? "Approve final cuti {$empName} ({$cuti->jenis}) oleh {$a->name}"
                : "Approve cuti {$empName} ke level {$next} oleh {$a->name}",
            meta: ['level' => $next, 'jenis' => $cuti->jenis, 'status' => $cuti->status, 'approver_role' => $a->role]
        );

        return back()->with('success', $next >= 3 ? 'Cuti disetujui final' : "Cuti naik ke level {$next}");
    }

    public function reject(Request $request, Leave $cuti)
    {
        $this->ensureApprover($cuti);
        if ($cuti->status !== 'Menunggu') {
            return back()->with('error', 'Hanya yang Menunggu bisa ditolak.');
        }

        $a = Auth::guard('web')->user();
        $cuti->status = 'Ditolak';
        $cuti->note = $request->input('note') ?: $cuti->note;
        $cuti->approved_by = $a->id;
        $cuti->save();

        $empName = $cuti->employee?->name ?? '-';
        Audit::log(
            'cuti.reject',
            subject: $cuti,
            label: "Cuti #{$cuti->id} {$empName}",
            description: "Tolak cuti {$empName} ({$cuti->jenis}) oleh {$a->name}",
            meta: ['note' => $cuti->note, 'jenis' => $cuti->jenis, 'approver_role' => $a->role]
        );

        return back()->with('success', 'Cuti ditolak');
    }

    public function destroy(Leave $cuti)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $cuti->employee?->region_id !== $scope, 403);
        $empName = $cuti->employee?->name ?? '-';
        $snapshot = ['jenis' => $cuti->jenis, 'status' => $cuti->status, 'level' => $cuti->level];
        $cuti->delete();

        Audit::log(
            'cuti.delete',
            subject: null,
            label: "Cuti {$empName}",
            description: "Hapus pengajuan cuti {$empName}",
            meta: $snapshot
        );

        return back()->with('success', 'Cuti dihapus');
    }
}
