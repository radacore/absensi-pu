<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $scope = Auth::guard('web')->user()->role === 'super_admin' ? null : Auth::guard('web')->user()->region_id;

        return Inertia::render('Admin/Dashboard', [
            'regions' => AdminPresenter::regionsFor($scope),
            'employees' => AdminPresenter::employeesFor($scope),
            'attendances' => AdminPresenter::attendancesFor($scope),
            'settings' => AdminPresenter::settingsFor(),
            'cuti' => AdminPresenter::leavesFor($scope),
            'love' => AdminPresenter::lovesFor($scope),
            'activities' => $this->activities($scope),
        ]);
    }

    private function activities(?int $scope): array
    {
        $today = now('Asia/Makassar')->toDateString();

        $attendances = Attendance::with('employee.region')
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->whereDate('work_date', $today)
            ->orderByDesc('clock_in_at')
            ->limit(10)->get()
            ->map(function (Attendance $a) {
                $emp = $a->employee;
                $region = $emp?->region?->name ?? '';
                $shortRegion = str_replace(['Kab. ', 'Kota '], '', $region);
                $time = $a->clock_in_at ? Carbon::parse($a->clock_in_at)->format('H:i') : '';
                $dist = (int) ($a->distance_in_m ?? 0);
                $status = $a->status;
                $label = match ($status) {
                    'late' => 'terlambat',
                    'excused_love' => 'hadir dengan toleransi',
                    'early_leave' => 'pulang awal',
                    default => 'hadir tepat waktu',
                };

                return [
                    't' => trim("{$time} — {$emp?->name} ({$shortRegion}) {$label} — {$dist} m"),
                    'tag' => $status ?: 'on_time',
                    'color' => match ($status) {
                        'late' => 'amber',
                        'excused_love' => 'gold',
                        'early_leave' => 'amber',
                        default => 'emerald',
                    },
                    'ts' => $a->created_at?->toIsoString(),
                ];
            });

        $leaves = Leave::with('employee.region')
            ->when($scope, fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('region_id', $scope)))
            ->orderByDesc('created_at')
            ->limit(10)->get()
            ->map(function (Leave $l) {
                $emp = $l->employee;
                $shortRegion = str_replace(['Kab. ', 'Kota '], '', $emp?->region?->name ?? '');
                $time = $l->created_at ? $l->created_at->timezone('Asia/Makassar')->format('H:i') : '';
                $status = strtolower($l->status);

                return [
                    't' => trim("{$time} — Cuti {$l->jenis} oleh {$emp?->name} ({$shortRegion}) — {$l->status} • level {$l->level}"),
                    'tag' => 'cuti',
                    'color' => $status === 'ditolak' ? 'amber' : 'sky',
                    'ts' => $l->created_at?->toIsoString(),
                ];
            });

        $love = ToleranceClaim::with('employee.region')
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->orderByDesc('created_at')
            ->limit(10)->get()
            ->map(function (ToleranceClaim $c) {
                $emp = $c->employee;
                $shortRegion = str_replace(['Kab. ', 'Kota '], '', $emp?->region?->name ?? '');
                $time = $c->created_at ? $c->created_at->timezone('Asia/Makassar')->format('H:i') : '';
                $jenis = $c->jenis === 'lupa_absen' ? 'lupa absen datang' : 'lupa absen pulang';

                return [
                    't' => trim("{$time} — Toleransi ({$jenis}) oleh {$emp?->name} ({$shortRegion}) — {$c->status}"),
                    'tag' => 'love',
                    'color' => $c->status === 'rejected' ? 'amber' : 'gold',
                    'ts' => $c->created_at?->toIsoString(),
                ];
            });

        return $attendances->concat($leaves)->concat($love)
            ->sortByDesc('ts')->values()
            ->take(10)
            ->map(fn ($row) => collect($row)->except('ts')->all())
            ->all();
    }
}
