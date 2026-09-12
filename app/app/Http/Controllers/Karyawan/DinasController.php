<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\DinasClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DinasController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user();
        $list = DinasClaim::where('employee_id', $me->id)
            ->orderByDesc('tanggal_pengajuan')
            ->get()
            ->map(function (DinasClaim $d) {
                $mulai = $d->tanggal_mulai->format('Y-m-d');
                $selesai = $d->tanggal_selesai->format('Y-m-d');
                $tglLabel = $mulai === $selesai
                    ? Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                    : Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').' – '.Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');

                return [
                    'id' => $d->id,
                    'nomor_surat' => $d->nomor_surat,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'tanggal_pengajuan' => $d->tanggal_pengajuan->format('Y-m-d'),
                    'tglLabel' => $tglLabel,
                    'keterangan' => $d->keterangan,
                    'tujuan' => $d->tujuan,
                    'transportasi' => $d->transportasi,
                    'pembebanan_anggaran' => $d->pembebanan_anggaran,
                    'status' => $d->status,
                    'note' => $d->note,
                ];
            })->all();

        return Inertia::render('Karyawan/Dinas', [
            'me' => ['id' => $me->id, 'nama' => $me->name, 'region' => $me->region?->name ?? ''],
            'list' => $list,
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user();
        $data = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string', 'min:5', 'max:1000'],
            'tujuan' => ['required', 'string', 'max:200'],
            'transportasi' => ['nullable', 'string', 'max:100'],
            'pembebanan_anggaran' => ['nullable', 'string', 'max:150'],
        ]);

        DinasClaim::create($data + [
            'employee_id' => $me->id,
            'tanggal_pengajuan' => now('Asia/Makassar')->toDateString(),
            'status' => 'Menunggu',
        ]);

        return back()->with('success', 'Pengajuan dinas dikirim — menunggu persetujuan.');
    }

    public function destroy(DinasClaim $dinas)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $dinas->employee_id !== (int) $me->id, 403);
        abort_if($dinas->status !== 'Menunggu', 403, 'Hanya pengajuan menunggu yang bisa dibatalkan.');
        $dinas->delete();

        return back()->with('success', 'Pengajuan dinas dibatalkan.');
    }
}
