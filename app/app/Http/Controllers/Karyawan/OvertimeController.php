<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OvertimeClaim;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Pengajuan lembur dari sisi karyawan (A8).
 *
 * Lembur dicatat sebagai pengajuan tersendiri, bukan tambahan otomatis pada
 * jam kerja. Setelah disetujui Admin Wilayah, menitnya masuk rekap bulanan
 * sebagai angka terpisah.
 */
class OvertimeController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site', 'sites']);
        $now = now('Asia/Makassar');

        $rows = OvertimeClaim::with(['approver', 'approvedBy'])
            ->where('employee_id', $me->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (OvertimeClaim $r) => [
                'id' => $r->id,
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

        $bulanIni = $rows
            ->filter(fn ($r) => $r['status'] === 'Disetujui' && str_starts_with($r['tanggal'], $now->format('Y-m')))
            ->sum('menit');

        return Inertia::render('Karyawan/Lembur', [
            'me' => [
                'id' => $me->id,
                'nama' => $me->name,
                'region' => $me->region?->name ?? '',
            ],
            'requests' => $rows,
            'hariIni' => $now->toDateString(),
            'batasMundur' => $now->copy()->subDays(30)->toDateString(),
            'menitBulanIni' => (int) $bulanIni,
            'approvers' => AdminPresenter::approversFor($me->region_id, $me->siteIds()),
        ]);
    }

    public function store(Request $request)
    {
        $me = Auth::guard('employee')->user()->load('sites');
        $now = now('Asia/Makassar');

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:'.$now->toDateString(), 'after_or_equal:'.$now->copy()->subDays(30)->toDateString()],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'keterangan' => ['required', 'string', 'min:5', 'max:1000'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $allow = collect(AdminPresenter::approversFor($me->region_id, $me->siteIds()))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (! in_array((int) $data['approver_id'], $allow, true)) {
            return back()->withErrors(['approver_id' => 'Atasan tidak valid — pilih dari daftar'])->withInput();
        }

        // Lembur boleh melewati tengah malam (mis. 22:00–01:00).
        $mulai = Carbon::createFromFormat('H:i', $data['jam_mulai']);
        $selesai = Carbon::createFromFormat('H:i', $data['jam_selesai']);
        if ($selesai->lte($mulai)) {
            $selesai->addDay();
        }
        $menit = (int) $mulai->diffInMinutes($selesai);

        if ($menit < 15) {
            return back()->withErrors(['jam_selesai' => 'Durasi lembur minimal 15 menit.'])->withInput();
        }
        if ($menit > 720) {
            return back()->withErrors(['jam_selesai' => 'Durasi lembur maksimal 12 jam per pengajuan.'])->withInput();
        }

        $menunggu = OvertimeClaim::where('employee_id', $me->id)
            ->whereDate('tanggal', $data['tanggal'])
            ->where('status', 'Menunggu')
            ->exists();
        if ($menunggu) {
            return back()->withErrors([
                'tanggal' => 'Sudah ada pengajuan lembur yang menunggu persetujuan untuk tanggal itu.',
            ])->withInput();
        }

        $siteId = Attendance::where('employee_id', $me->id)
            ->whereDate('work_date', $data['tanggal'])
            ->value('site_id') ?: $me->site_id;

        OvertimeClaim::create([
            'employee_id' => $me->id,
            'region_id' => $me->region_id,
            'site_id' => $siteId,
            'tanggal' => $data['tanggal'],
            'jam_mulai' => $data['jam_mulai'].':00',
            'jam_selesai' => $data['jam_selesai'].':00',
            'menit' => $menit,
            'keterangan' => $data['keterangan'],
            'approver_id' => $data['approver_id'],
            'status' => 'Menunggu',
        ]);

        return back()->with('success', 'Pengajuan lembur terkirim — menunggu persetujuan.');
    }

    public function destroy(OvertimeClaim $lembur)
    {
        $me = Auth::guard('employee')->user();
        abort_if((int) $lembur->employee_id !== (int) $me->id, 403);

        if ($lembur->status !== 'Menunggu') {
            return back()->with('error', 'Pengajuan yang sudah diproses tidak bisa dibatalkan.');
        }

        $lembur->delete();

        return back()->with('success', 'Pengajuan lembur dibatalkan.');
    }
}
