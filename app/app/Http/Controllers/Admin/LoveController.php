<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoveController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();
        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();
        return Inertia::render('Admin/Love', [
            'regions' => AdminPresenter::regionsFor($scope),
            'claims' => AdminPresenter::lovesFor($scope),
            'settings' => AdminPresenter::settingsFor(),
        ]);
    }

    public function approve(ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        if ($love->status !== 'pending') return back()->with('error', 'Hanya pending bisa di-approve.');
        $love->status = 'approved';
        $love->save();

        $empName = $love->employee?->name ?? '-';
        Audit::log(
            'love.approve',
            subject: $love,
            label: "Toleransi #{$love->id} {$empName}",
            description: "Approve klaim toleransi {$empName} ({$love->jenis})",
            meta: ['jenis' => $love->jenis, 'claim_date' => (string) $love->claim_date]
        );

        return back()->with('success', 'Toleransi disetujui');
    }

    public function reject(Request $request, ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        if ($love->status !== 'pending') return back()->with('error', 'Hanya pending bisa ditolak.');
        $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $love->status = 'rejected';
        $love->note = $request->input('note');
        $love->save();

        $empName = $love->employee?->name ?? '-';
        Audit::log(
            'love.reject',
            subject: $love,
            label: "Toleransi #{$love->id} {$empName}",
            description: "Tolak klaim toleransi {$empName}",
            meta: ['note' => $love->note, 'jenis' => $love->jenis]
        );

        return back()->with('success', 'Toleransi ditolak');
    }

    public function destroy(ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        $empName = $love->employee?->name ?? '-';
        $snapshot = ['jenis' => $love->jenis, 'status' => $love->status, 'claim_date' => (string) $love->claim_date];
        $love->delete();

        Audit::log(
            'love.delete',
            subject: null,
            label: "Toleransi {$empName}",
            description: "Hapus klaim toleransi {$empName}",
            meta: $snapshot
        );

        return back()->with('success', 'Klaim toleransi dihapus');
    }
}
