<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Leave;
use App\Models\Site;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);

        $history = Attendance::where('employee_id', $me->id)
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->limit(90)
            ->get()
            ->map(fn (Attendance $a) => [
                'id' => $a->id,
                'tgl' => $a->work_date instanceof \Illuminate\Support\Carbon ? $a->work_date->format('Y-m-d') : (string) $a->work_date,
                'datang' => $a->clock_in_at ? \Illuminate\Support\Carbon::parse($a->clock_in_at)->format('H:i') : '',
                'pulang' => $a->clock_out_at ? \Illuminate\Support\Carbon::parse($a->clock_out_at)->format('H:i') : '',
                'status' => $a->status ?? 'on_time',
                'jarak' => (int) ($a->distance_in_m ?? 0),
                'selfie' => $a->selfie_url,
            ])->values()->all();

        $assigned = $me->site ? [
            'id' => $me->site->id,
            'nama_lokasi' => $me->site->nama_lokasi,
            'lat' => (float) $me->site->lat,
            'lng' => (float) $me->site->lng,
            'radius' => (int) $me->site->radius_m,
            'address' => $me->site->address,
            'regionName' => $me->region?->name ?? '',
            'kantor' => $me->region?->kantor_name ?? '',
        ] : null;

        $settings = AttendanceSetting::first();
        $today = now('Asia/Makassar')->toDateString();
        $todayRow = Attendance::where('employee_id', $me->id)->whereDate('work_date', $today)->first();

        return Inertia::render('Karyawan/Absensi', [
            'me' => [
                'id' => $me->id,
                'nama' => $me->name,
                'foto' => $me->foto_url ?: AdminPresenter::DEFAULT_AVATAR,
                'region' => $me->region?->name ?? '',
            ],
            'assigned' => $assigned,
            'history' => $history,
            'settings' => $settings ? [
                'jamMasuk' => substr((string) $settings->jam_masuk, 0, 5),
                'jamPulang' => substr((string) $settings->jam_pulang, 0, 5),
                'toleransi' => (int) $settings->toleransi_late_menit,
            ] : ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15],
            'alreadyToday' => (bool) $todayRow,
            'todayISO' => $today,
        ]);
    }

    public function clockIn(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['site', 'region']);
        if (! $me->site_id || ! $me->site) {
            throw ValidationException::withMessages(['site' => 'Titik belum di-assign — tidak bisa absen (422).']);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'selfie_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $today = now('Asia/Makassar')->toDateString();
        if (Attendance::where('employee_id', $me->id)->whereDate('work_date', $today)->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Sudah absen hari ini.']);
        }

        $site = $me->site;
        $dist = AdminPresenter::haversineM((float) $data['lat'], (float) $data['lng'], (float) $site->lat, (float) $site->lng);
        if ($dist > (int) $site->radius_m) {
            throw ValidationException::withMessages(['distance' => "{$dist} m / {$site->radius_m} m — di luar radius."]);
        }

        $settings = AttendanceSetting::first();
        $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
        $tol = $settings ? (int) $settings->toleransi_late_menit : 15;
        [$h, $m] = array_map('intval', explode(':', $jamMasuk));
        $cutoffMin = $h * 60 + $m + $tol;
        $now = now('Asia/Makassar');
        $curMin = (int) $now->format('G') * 60 + (int) $now->format('i');
        $status = $curMin > $cutoffMin ? 'late' : 'on_time';

        Attendance::create([
            'employee_id' => $me->id,
            'work_date' => $today,
            'clock_in_at' => $now->format('H:i:s'),
            'status' => $status,
            'lat_in' => $data['lat'],
            'lng_in' => $data['lng'],
            'distance_in_m' => $dist,
            'selfie_url' => $data['selfie_url'] ?? null,
            'site_id' => $site->id,
            'region_id' => $me->region_id,
        ]);

        return back()->with('success', $status === 'late' ? 'Absen tercatat — Terlambat' : 'Absen tercatat — Tepat waktu');
    }

    public function clockOut(Request $request)
    {
        $me = Auth::guard('employee')->user()->load('site');
        if (! $me->site) {
            throw ValidationException::withMessages(['site' => 'Titik belum di-assign.']);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $today = now('Asia/Makassar')->toDateString();
        $row = Attendance::where('employee_id', $me->id)->whereDate('work_date', $today)->first();
        if (! $row) {
            throw ValidationException::withMessages(['work_date' => 'Belum absen masuk hari ini.']);
        }
        if ($row->clock_out_at) {
            throw ValidationException::withMessages(['work_date' => 'Sudah absen pulang hari ini.']);
        }

        $dist = AdminPresenter::haversineM((float) $data['lat'], (float) $data['lng'], (float) $me->site->lat, (float) $me->site->lng);
        if ($dist > (int) $me->site->radius_m) {
            throw ValidationException::withMessages(['distance' => "{$dist} m / {$me->site->radius_m} m — di luar radius."]);
        }

        $now = now('Asia/Makassar');
        $row->update([
            'clock_out_at' => $now->format('H:i:s'),
            'lat_out' => $data['lat'],
            'lng_out' => $data['lng'],
        ]);

        return back()->with('success', 'Absen pulang tercatat');
    }

    /** Rekap: data untuk kalender + ringkasan bulan ini. */
    public function rekap()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);
        $settings = AttendanceSetting::first();
        $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
        $jamPulang = $settings ? substr((string) $settings->jam_pulang, 0, 5) : '16:00';
        $loveMax = $settings ? (int) $settings->love_max : 4;

        $now = now('Asia/Makassar');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Makassar');
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $rows = Attendance::where('employee_id', $me->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get();

        $approvedLeaves = Leave::where('employee_id', $me->id)
            ->where('status', 'Disetujui')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('mulai', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('selesai', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($qq) use ($startOfMonth, $endOfMonth) {
                        $qq->where('mulai', '<', $startOfMonth)->where('selesai', '>', $endOfMonth);
                    });
            })->get();

        $cutiDates = [];
        foreach ($approvedLeaves as $leave) {
            $start = Carbon::parse($leave->mulai)->max($startOfMonth);
            $end = Carbon::parse($leave->selesai)->min($endOfMonth);
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $cutiDates[$d->toDateString()] = true;
            }
        }
        $cutiDays = count($cutiDates);

        $assigned = $me->site ? [
            'id' => $me->site->id,
            'nama_lokasi' => $me->site->nama_lokasi,
            'lat' => (float) $me->site->lat,
            'lng' => (float) $me->site->lng,
            'radius' => (int) $me->site->radius_m,
            'address' => $me->site->address,
            'regionName' => $me->region?->name ?? '',
        ] : null;

        return Inertia::render('Karyawan/Rekap', [
            'me' => ['id' => $me->id, 'nama' => $me->name, 'region' => $me->region?->name ?? ''],
            'assigned' => $assigned,
            'settings' => [
                'jamMasuk' => $jamMasuk,
                'jamPulang' => $jamPulang,
                'toleransi' => $settings ? (int) $settings->toleransi_late_menit : 15,
                'loveMax' => $loveMax,
                'loveQuota' => AdminPresenter::loveQuota($me->id, $loveMax),
            ],
            'monthRows' => $rows->map(fn (Attendance $a) => [
                'tgl' => $a->work_date instanceof Carbon ? $a->work_date->format('Y-m-d') : (string) $a->work_date,
                'status' => $a->status,
            ])->values()->all(),
            'cutiDates' => array_keys($cutiDates),
            'cutiDays' => $cutiDays,
            'hadir' => $rows->count(),
            'terlambat' => $rows->where('status', 'late')->count(),
        ]);
    }
}
