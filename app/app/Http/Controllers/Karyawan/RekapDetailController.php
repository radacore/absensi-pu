<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Support\RekapPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RekapDetailController extends Controller
{
    public function show(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($me, $year, $month);

        return Inertia::render('Karyawan/RekapDetail', [
            'rekap' => $rekap,
        ]);
    }
}
