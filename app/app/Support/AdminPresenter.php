<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Region;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Presenter data master admin → bentuk yang dipakai UI mock (camelCase).
 */
class AdminPresenter
{
    public const DEFAULT_AVATAR = 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face&auto=format';

    public static function regionsFor(?int $regionId): array
    {
        $regions = Region::with('sites')
            ->when($regionId, fn ($q) => $q->where('id', $regionId))
            ->orderBy('id')
            ->get();

        return $regions->map(fn (Region $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'kantor' => $r->kantor_name,
            'tipe' => $r->tipe,
            'address' => $r->address,
            'locations' => $r->sites->sortBy('id')->values()->map(fn ($s) => [
                'id' => $s->id,
                'nama_lokasi' => $s->nama_lokasi,
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'radius' => (int) $s->radius_m,
                'address' => $s->address,
            ])->values()->all(),
        ])->values()->all();
    }

    public static function employeesFor(?int $regionId): array
    {
        $employees = Employee::with('region')
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('id')
            ->get();

        return $employees->map(fn (Employee $e) => self::employeeRow($e))->values()->all();
    }

    public static function employeeRow(Employee $e): array
    {
        $regionName = $e->region?->name ?? '';
        $kantor = str_replace(['Kab. ', 'Kota '], '', $regionName);

        return [
            'id' => $e->id,
            'nik' => $e->nik,
            'nip' => $e->nip ?? '',
            'nama' => $e->name,
            'email' => $e->email ?? '',
            'gol' => $e->golongan ?? '-',
            'jabatan' => $e->jabatan,
            'unit' => $e->unit_kerja,
            'status' => $e->status_kepegawaian,
            'region' => $regionName,
            'regionId' => $e->region_id,
            'office_location_id' => $e->site_id,
            'kantor' => $kantor,
            'foto' => $e->foto_url ?: self::DEFAULT_AVATAR,
        ];
    }

    public static function adminsFor(): array
    {
        $admins = User::with('region')
            ->where('role', 'admin_wilayah')
            ->orderBy('id')
            ->get();

        return $admins->map(fn (User $u) => [
            'id' => $u->id,
            'nama' => $u->name,
            'email' => $u->email,
            'region' => $u->region?->name ?? '—',
            'regionId' => $u->region_id,
            'status' => $u->is_active ? 'Aktif' : 'Nonaktif',
            'avatar' => self::DEFAULT_AVATAR,
        ])->values()->all();
    }

    public static function regionNames(): array
    {
        return Region::orderBy('id')->pluck('name')->all();
    }

    public static function settingsFor(): array
    {
        $s = AttendanceSetting::first();
        if (! $s) {
            return ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15, 'loveMax' => 4, 'hariKerja' => ['1','2','3','4','5'], 'timezone' => 'Asia/Makassar'];
        }

        return [
            'jamMasuk' => substr((string) $s->jam_masuk, 0, 5),
            'jamPulang' => substr((string) $s->jam_pulang, 0, 5),
            'toleransi' => (int) $s->toleransi_late_menit,
            'loveMax' => (int) $s->love_max,
            'hariKerja' => $s->hari_kerja ?? ['1','2','3','4','5'],
            'timezone' => $s->timezone,
        ];
    }

    public static function attendancesFor(?int $regionId): array
    {
        $rows = Attendance::with(['employee.region'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->limit(500)
            ->get();

        return $rows->map(function (Attendance $a) {
            $emp = $a->employee;
            $regionName = $emp?->region?->name ?? Region::where('id', $a->region_id)->value('name') ?? '';
            $kantor = str_replace(['Kab. ', 'Kota '], '', $regionName);
            $tgl = $a->work_date instanceof Carbon ? $a->work_date->format('Y-m-d') : (string) $a->work_date;
            // clock_in_at may be stored as "07:52:00" string or Carbon
            $datang = $a->clock_in_at ? Carbon::parse($a->clock_in_at)->format('H:i') : '';
            $pulang = $a->clock_out_at ? Carbon::parse($a->clock_out_at)->format('H:i') : '';

            return [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'nama' => $emp?->name ?? '-',
                'email' => $emp?->email ?? '',
                'wilayah' => $regionName,
                'kantor' => $kantor ?: $regionName,
                'office_location_id' => $a->site_id,
                'tgl' => $tgl,
                'datang' => $datang,
                'pulang' => $pulang,
                'status' => $a->status ?? 'on_time',
                'love' => $a->tolerance_claim_id ? 'approved' : null,
                'jarak' => (int) ($a->distance_in_m ?? 0),
                'lat' => (float) ($a->lat_in ?? 0),
                'lng' => (float) ($a->lng_in ?? 0),
                'foto' => $emp?->foto_url ?: self::DEFAULT_AVATAR,
                'selfie' => $a->selfie_url ?: ($emp?->foto_url ?: self::DEFAULT_AVATAR),
            ];
        })->values()->all();
    }

    /** Haversine distance in meters. */
    public static function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $R = 6371000;
        $toRad = fn (float $x) => $x * M_PI / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $R * asin(sqrt($a)));
    }

    // ── Leaves / Tolerance / Announcements ──────────────────

    public static function leavesFor(?int $regionId): array
    {
        return Leave::with('employee.region')
            ->when($regionId, fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('region_id', $regionId)))
            ->orderByDesc('created_at')
            ->limit(500)->get()
            ->map(function (Leave $l) {
                $emp = $l->employee;
                $regionName = $emp?->region?->name ?? '';
                $mulai = $l->mulai instanceof Carbon ? $l->mulai->format('Y-m-d') : (string) $l->mulai;
                $selesai = $l->selesai instanceof Carbon ? $l->selesai->format('Y-m-d') : (string) $l->selesai;
                $tglLabel = $mulai === $selesai
                    ? Carbon::parse($mulai)->locale('id')->isoFormat('D MMM YYYY')
                    : Carbon::parse($mulai)->locale('id')->isoFormat('D MMM').'–'.Carbon::parse($selesai)->locale('id')->isoFormat('D MMM YYYY');

                return [
                    'id' => $l->id,
                    'employee_id' => $l->employee_id,
                    'nama' => $emp?->name ?? '-',
                    'email' => $emp?->email ?? '',
                    'wilayah' => $regionName,
                    'regionId' => $emp?->region_id,
                    'office_location_id' => $emp?->site_id,
                    'jenis' => $l->jenis,
                    'tgl' => $tglLabel,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'alasan' => $l->alasan,
                    'dokumen' => $l->dokumen_path,
                    'status' => $l->status,
                    'level' => (int) $l->level,
                    'note' => $l->note,
                    'createdAt' => $l->created_at?->toIsoString(),
                ];
            })->values()->all();
    }

    public static function lovesFor(?int $regionId): array
    {
        return ToleranceClaim::with(['employee.region', 'approver'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderByDesc('created_at')
            ->limit(500)->get()
            ->map(function (ToleranceClaim $c) {
                $emp = $c->employee;
                $regionName = $emp?->region?->name ?? Region::where('id', $c->region_id)->value('name') ?? '';
                $tgl = $c->claim_date instanceof Carbon ? $c->claim_date->format('Y-m-d') : (string) $c->claim_date;
                $jam = $c->jam ? substr((string) $c->jam, 0, 5) : '';
                $approver = $c->approver;

                return [
                    'id' => $c->id,
                    'employee_id' => $c->employee_id,
                    'nama' => $emp?->name ?? '-',
                    'wilayah' => $regionName,
                    'kantor' => str_replace(['Kab. ', 'Kota '], '', $regionName) ?: $regionName,
                    'office_location_id' => $c->site_id,
                    'jenis' => $c->jenis,
                    'tgl' => $tgl,
                    'jam' => $jam,
                    'alasan' => $c->alasan,
                    'approver_id' => $c->approver_id,
                    'approver_nama' => $approver?->name ?? '',
                    'approver_nip' => $approver?->nip ?? '',
                    'approver_scope' => $approver?->region?->name ?? '',
                    'status' => $c->status,
                    'note' => $c->note,
                    'createdAt' => $c->created_at?->toIsoString(),
                ];
            })->values()->all();
    }

    public static function announcementsFor(?int $regionId, bool $isSuperAdmin = false): array
    {
        // Super admin lihat semua; wilayah hanya Global + own region
        $rows = Announcement::with('region')
            ->when(! $isSuperAdmin && $regionId, fn ($q) => $q->where(fn ($qq) => $qq->where('scope', 'Global')->orWhere('region_id', $regionId)))
            ->orderByDesc('pin')->orderByDesc('created_at')->limit(200)->get();

        return $rows->map(fn (Announcement $a) => [
            'id' => $a->id,
            'judul' => $a->judul,
            'konten' => $a->konten,
            'scope' => $a->scope,
            'region' => $a->region?->name ?? '',
            'region_id' => $a->region_id,
            'pin' => (bool) $a->pin,
            'tgl' => $a->created_at?->locale('id')->isoFormat('D MMM YYYY') ?? '',
            'stat' => $a->scope === 'Global' ? 'Terkirim 24 kantor' : 'Terkirim '.($a->region?->name ?? ''),
            'createdBy' => $a->created_by,
        ])->values()->all();
    }

    public static function announcementsForKaryawan(Employee $emp, array $readIds = []): array
    {
        $rows = Announcement::with('region')
            ->where(fn ($q) => $q->where('scope', 'Global')->orWhere('region_id', $emp->region_id))
            ->orderByDesc('pin')->orderByDesc('created_at')->limit(200)->get();

        return $rows->map(fn (Announcement $a) => [
            'id' => $a->id,
            'judul' => $a->judul,
            'konten' => $a->konten,
            'scope' => $a->scope,
            'region' => $a->region?->name ?? '',
            'pin' => (bool) $a->pin,
            'tgl' => $a->created_at?->locale('id')->isoFormat('D MMM YYYY') ?? '',
            'stat' => $a->scope === 'Global' ? 'Terkirim 24 kantor' : 'Terkirim '.($a->region?->name ?? ''),
            'read' => in_array($a->id, $readIds, true),
        ])->values()->all();
    }

    public static function approversFor(?int $regionId, ?int $siteId): array
    {
        // Approver = User role admin_wilayah (act as atasan) scoped to region + optional site
        // Kantor wilayah = admin without site_id; site admin = site_id match
        $q = User::with('region')->where('role', 'admin_wilayah')->where('is_active', true);
        if ($regionId !== null) {
            $q->where(function ($qq) use ($regionId, $siteId) {
                $qq->where('region_id', $regionId)->whereNull('site_id')
                    ->when($siteId !== null, fn ($qq2) => $qq2->orWhere('site_id', $siteId));
            });
        }
        // fallback: if no site-scoped approver, still include kantor
        $rows = $q->orderBy('name')->get();
        if ($rows->isEmpty() && $regionId !== null) {
            $rows = User::with('region')->where('role', 'admin_wilayah')->where('region_id', $regionId)->where('is_active', true)->orderBy('name')->get();
        }

        return $rows->map(fn (User $u) => [
            'id' => $u->id,
            'nama' => $u->name,
            'nip' => $u->nip ?? '',
            'jabatan' => $u->jabatan ?? $u->role,
            'wilayah' => $u->region?->name ?? '',
            'regionId' => $u->region_id,
            'office_location_id' => $u->site_id,
            'scope' => $u->site_id ? ($u->region?->name ?? '') : ($u->region?->name ? 'Kantor '.str_replace(['Kab. ','Kota '], '', $u->region->name) : 'Kantor'),
            'email' => $u->email,
        ])->values()->all();
    }

    public static function loveQuota(int $employeeId, int $loveMax): array
    {
        $now = now('Asia/Makassar');
        $claims = ToleranceClaim::where('employee_id', $employeeId)
            ->whereYear('claim_date', $now->year)->whereMonth('claim_date', $now->month)
            ->get();
        $approved = $claims->where('status', 'approved')->count();
        $pending = $claims->where('status', 'pending')->count();
        $sisa = max(0, $loveMax - $approved - $pending);

        return ['max' => $loveMax, 'approved' => $approved, 'pending' => $pending, 'sisa' => $sisa];
    }
}
