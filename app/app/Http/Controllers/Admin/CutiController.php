<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CutiController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();
        return $u->role === 'super_admin' ? null : $u->region_id;
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
        $scope = $this->scope();
        abort_if($scope !== null && (int) $cuti->employee?->region_id !== $scope, 403);
        if ($cuti->status !== 'Menunggu') return back()->with('error', 'Hanya yang Menunggu bisa di-approve.');
        $next = (int) $cuti->level + 1;
        $cuti->level = $next;
        $cuti->status = $next >= 3 ? 'Disetujui' : 'Menunggu';
        $cuti->save();
        return back()->with('success', $next >= 3 ? 'Cuti disetujui final' : "Cuti naik ke level {$next}");
    }

    public function reject(Request $request, Leave $cuti)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $cuti->employee?->region_id !== $scope, 403);
        if ($cuti->status !== 'Menunggu') return back()->with('error', 'Hanya yang Menunggu bisa ditolak.');
        $cuti->status = 'Ditolak';
        $cuti->note = $request->input('note') ?: $cuti->note;
        $cuti->save();
        return back()->with('success', 'Cuti ditolak');
    }

    public function destroy(Leave $cuti)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $cuti->employee?->region_id !== $scope, 403);
        $cuti->delete();
        return back()->with('success', 'Cuti dihapus');
    }
}
