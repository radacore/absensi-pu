<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\EarlyLeaveRequest;
use App\Support\AdminPresenter;
use App\Support\ShiftResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Pengajuan pulang cepat dari sisi karyawan (P2).
 *
 * Setelah disetujui Admin Wilayah, karyawan boleh absen pulang sebelum jam
 * shiftnya berakhir pada tanggal tersebut.
 */
class EarlyLeaveController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site', 'sites']);
        $now = now('Asia/Makassar');

        $rows = EarlyLeaveRequest::with(['approver', 'approvedBy'])
            ->where('employee_id', $me->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (EarlyLeaveRequest $r) => [
                'id' => $r->id,
                'tanggal' => $r->tanggal instanceof Carbon ? $r->tanggal->toDateString() : (string) $r->tanggal,
                'jamRencana' => substr((string) $r->jam_rencana, 0, 5),
                'alasan' => $r->alasan,
                'approverNama' => $r->approver?->name ?? '',
                'status' => $r->status,
                'note' => $r->note ?? '',
                'diprosesOleh' => $r->approvedBy?->name ?? '',
            ])->values()->all();

        // Shift yang sedang berjalan → dipakai untuk mengisi tanggal secara
        // otomatis (shift malam bertanggal kemarin).
        $current = ShiftResolver::currentShift($me, $now);
        $shift = $current ? [
            'label' => $current['pattern']->labelShift((int) $current['schedule']->shift_ke),
            'tanggal' => $current['schedule']->tanggal->toDateString(),
            'jamMulai' => $current['mulai']->format('H:i'),
            'jamSelesai' => $current['selesai']->format('H:i'),
        ] : null;

        return Inertia::render('Karyawan/PulangAwal', [
            'me' => [
                'id' => $me->id,
                'nama' => $me->name,
                'region' => $me->region?->name ?? '',
            ],
            'requests' => $rows,
            'shift' => $shift,
            'isShift' => (bool) $me->is_shift,
            'hariIni' => $now->toDateString(),
            'batasMundur' => $now->copy()->subDays(7)->toDateString(),
            'approvers' => AdminPresenter::approversFor($me->region_id, $me->siteIds()),
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user()->load('sites');
        $now = now('Asia/Makassar');

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:'.$now->toDateString(), 'after_or_equal:'.$now->copy()->subDays(7)->toDateString()],
            'jamRencana' => ['required', 'date_format:H:i'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $allow = collect(AdminPresenter::approversFor($me->region_id, $me->siteIds()))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (! in_array((int) $data['approver_id'], $allow, true)) {
            return back()->withErrors(['approver_id' => 'Atasan tidak valid — pilih dari daftar'])->withInput();
        }

        $menunggu = EarlyLeaveRequest::where('employee_id', $me->id)
            ->whereDate('tanggal', $data['tanggal'])
            ->where('status', 'Menunggu')
            ->exists();
        if ($menunggu) {
            return back()->withErrors([
                'tanggal' => 'Sudah ada pengajuan yang menunggu persetujuan untuk tanggal itu.',
            ])->withInput();
        }

        // Titik pengajuan mengikuti baris absensi tanggal itu bila sudah ada.
        $sitePulang = Attendance::where('employee_id', $me->id)
            ->whereDate('work_date', $data['tanggal'])
            ->value('site_id') ?: $me->site_id;

        EarlyLeaveRequest::create([
            'employee_id' => $me->id,
            'tanggal' => $data['tanggal'],
            'jam_rencana' => $data['jamRencana'].':00',
            'alasan' => $data['alasan'],
            'approver_id' => $data['approver_id'],
            'status' => 'Menunggu',
            'site_id' => $sitePulang,
            'region_id' => $me->region_id,
        ]);

        return back()->with('success', 'Pengajuan pulang cepat terkirim — menunggu persetujuan.');
    }

    public function destroy(EarlyLeaveRequest $pulangCepat)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $pulangCepat->employee_id !== (int) $me->id, 403);

        if ($pulangCepat->status !== 'Menunggu') {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak bisa dibatalkan.');
        }

        $pulangCepat->delete();

        return back()->with('success', 'Pengajuan dibatalkan.');
    }
}
