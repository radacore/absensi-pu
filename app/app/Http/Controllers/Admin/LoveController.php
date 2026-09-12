<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
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
        // quota enforcement (loveMax per month): hit if approve would exceed? soft-check
        $love->status = 'approved';
        $love->save();
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
        return back()->with('success', 'Toleransi ditolak');
    }

    public function destroy(ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        $love->delete();
        return back()->with('success', 'Klaim toleransi dihapus');
    }
}
