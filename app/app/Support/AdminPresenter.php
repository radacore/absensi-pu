<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Region;
use App\Models\ShiftSchedule;
use App\Models\Site;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presenter data master admin → bentuk yang dipakai UI mock (camelCase).
 */
class AdminPresenter
{
    public const DEFAULT_AVATAR = '/avatar-default.svg';

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

    /**
     * Dampak menghapus tiap titik, dipakai dialog konfirmasi di halaman wilayah.
     *
     * Menghapus titik tidak menghapus riwayat absensi, tetapi nama titiknya
     * hilang dari rekap karena `attendances.site_id` dan `site_out_id` hanya
     * menyimpan angka. Absensi yang belum absen pulang di titik itu juga
     * kehilangan acuan jarak. Angka-angka ini ditampilkan sebelum admin
     * memutuskan, supaya penghapusan titik aktif tidak terjadi tanpa sadar.
     *
     * @return array<int, array{riwayat: int, hariIni: int, belumPulang: int}>
     */
    public static function siteImpactFor(?int $regionId): array
    {
        $ids = Site::query()
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($ids === []) {
            return [];
        }

        $hariIni = Carbon::now('Asia/Makassar')->toDateString();

        $hitung = function (string $kolom, bool $hanyaHariIni, bool $hanyaTerbuka = false) use ($ids, $hariIni): Collection {
            return Attendance::query()
                ->whereIn($kolom, $ids)
                ->when($hanyaHariIni, fn ($q) => $q->whereDate('work_date', $hariIni))
                ->when($hanyaTerbuka, fn ($q) => $q->whereNull('clock_out_at'))
                ->selectRaw("{$kolom} as sid, count(*) as jumlah")
                ->groupBy($kolom)
                ->pluck('jumlah', 'sid');
        };

        $riwayatMasuk = $hitung('site_id', false);
        $riwayatPulang = $hitung('site_out_id', false);
        $hariIniMasuk = $hitung('site_id', true);
        $hariIniPulang = $hitung('site_out_id', true);
        $belumPulang = $hitung('site_id', true, true);

        $hasil = [];
        foreach ($ids as $id) {
            $hasil[$id] = [
                'riwayat' => (int) ($riwayatMasuk[$id] ?? 0) + (int) ($riwayatPulang[$id] ?? 0),
                'hariIni' => (int) ($hariIniMasuk[$id] ?? 0) + (int) ($hariIniPulang[$id] ?? 0),
                'belumPulang' => (int) ($belumPulang[$id] ?? 0),
            ];
        }

        return $hasil;
    }

    public static function employeesFor(?int $regionId): array
    {
        $employees = Employee::with(['region', 'site', 'sites'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('id')
            ->get();

        return $employees->map(fn (Employee $e) => self::employeeRow($e))->values()->all();
    }

    public static function employeeRow(Employee $e): array
    {
        $regionName = $e->region?->name ?? '';
        $kantor = str_replace(['Kab. ', 'Kota '], '', $regionName);

        // Titik utama lebih dulu, lalu titik tambahan. Dipakai UI untuk
        // menghitung anggota per titik dan menampilkan daftar titik karyawan.
        $titik = collect([$e->site])
            ->filter()
            ->concat($e->relationLoaded('sites') ? $e->sites : $e->sites()->get())
            ->unique('id')
            ->values();

        return [
            'id' => $e->id,
            'nik' => $e->nik ?? '',
            // PPK hanya keterangan/label dari berkas PPK; tidak mengatur hak akses.
            'ppk' => $e->ppk ?? '',
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
            // Satu karyawan boleh absen di beberapa titik.
            'site_ids' => $titik->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'site_names' => $titik->pluck('nama_lokasi')->values()->all(),
            'kantor' => $kantor,
            'foto' => $e->foto_url ?: self::DEFAULT_AVATAR,
            // K4: penanda pekerja shift hanya per orang.
            'isShift' => (bool) $e->is_shift,
            // B3: hari kerja khusus per orang (null = ikut pengaturan umum).
            'hariKerja' => is_array($e->hari_kerja) && $e->hari_kerja !== []
                ? array_map('strval', $e->hari_kerja)
                : null,
            // F4: label regu bebas ('' = tanpa regu).
            'regu' => $e->regu ?? '',
            // Izin pulang di titik berbeda; mati = hanya titik masuk.
            'bolehPindahTitik' => (bool) $e->boleh_pindah_titik,
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
            // Akun yang juga terdaftar sebagai karyawan: satu orang, satu
            // kata sandi, dua peran.
            'employeeId' => $u->employee_id,
            'karyawan' => $u->employee_id !== null,
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
            return ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15, 'loveMax' => 4, 'cutiTahunanMax' => AttendanceSetting::CUTI_TAHUNAN_DEFAULT, 'hariKerja' => ['1', '2', '3', '4', '5'], 'timezone' => 'Asia/Makassar', 'absenLiburAktif' => false, 'absenLiburMode' => 'tolak'];
        }

        return [
            'jamMasuk' => substr((string) $s->jam_masuk, 0, 5),
            'jamPulang' => substr((string) $s->jam_pulang, 0, 5),
            'toleransi' => (int) $s->toleransi_late_menit,
            'loveMax' => (int) $s->love_max,
            'cutiTahunanMax' => CutiKuota::max($s),
            'hariKerja' => $s->hari_kerja ?? ['1', '2', '3', '4', '5'],
            'timezone' => $s->timezone,
            'absenLiburAktif' => (bool) $s->absen_libur_aktif,
            'absenLiburMode' => $s->absen_libur_mode ?: 'tolak',
        ];
    }

    public static function attendancesFor(?int $regionId): array
    {
        $rows = Attendance::with(['employee.region', 'site', 'siteOut'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->limit(500)
            ->get();

        $shiftLabels = self::shiftLabelsFor($rows);

        return $rows->map(function (Attendance $a) use ($shiftLabels) {
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
                // Titik pulang bisa berbeda dari titik masuk. UI dan filter
                // memakai keduanya supaya absensi tidak terbaca di titik yang
                // salah (dan tidak tampak "di luar radius").
                'office_location_id_out' => $a->site_out_id,
                'nama_titik' => $a->site?->nama_lokasi ?? $emp?->site?->nama_lokasi ?? '',
                'nama_titik_pulang' => $a->siteOut?->nama_lokasi ?? $a->site?->nama_lokasi ?? $emp?->site?->nama_lokasi ?? '',
                'berpindah_lokasi' => $a->site_id !== null && $a->site_out_id !== null && (int) $a->site_id !== (int) $a->site_out_id,
                // E6: absen di luar radius semua titik tetap tercatat, jadi
                // admin perlu melihat tanda ini di kartu detail.
                'di_luar_titik' => (bool) $a->di_luar_titik,
                'di_luar_titik_pulang' => (bool) $a->di_luar_titik_pulang,
                'tgl' => $tgl,
                'datang' => $datang,
                'pulang' => $pulang,
                'status' => $a->status ?? 'on_time',
                'love' => $a->tolerance_claim_id ? 'approved' : null,
                'jarak' => (int) ($a->distance_in_m ?? 0),
                'lat' => (float) ($a->lat_in ?? 0),
                'lng' => (float) ($a->lng_in ?? 0),
                // Koordinat pulang dipakai kartu detail admin. Jaraknya dihitung
                // ke titik pulang, bukan ke titik masuk.
                'lat_out' => $a->lat_out !== null ? (float) $a->lat_out : null,
                'lng_out' => $a->lng_out !== null ? (float) $a->lng_out : null,
                'jarak_pulang' => ($a->lat_out !== null && $a->lng_out !== null && $a->siteOut)
                    ? self::haversineM((float) $a->lat_out, (float) $a->lng_out, (float) $a->siteOut->lat, (float) $a->siteOut->lng)
                    : null,
                'foto' => $emp?->foto_url ?: self::DEFAULT_AVATAR,
                'selfie' => $a->selfieRouteUrl('masuk') ?: ($emp?->foto_url ?: self::DEFAULT_AVATAR),
                'selfie_pulang' => $a->selfieRouteUrl('pulang'),
                'catatan_pulang' => $a->catatan_pulang ?? '',
                // Label shift terjadwal (null untuk karyawan non-shift).
                'shift' => $shiftLabels[$a->employee_id.'|'.$tgl] ?? null,
                // F2: perlu pembeda agar UI bisa menulis "di luar jadwal"
                // hanya untuk pekerja shift.
                'is_shift' => (bool) ($emp?->is_shift),
            ];
        })->values()->all();
    }

    /**
     * Label shift untuk sekumpulan baris absensi, diambil sekali jalan supaya
     * daftar absensi admin tidak menembak satu query per baris.
     *
     * @param  Collection<int, Attendance>  $rows
     * @return array<string, string|null>
     */
    private static function shiftLabelsFor($rows): array
    {
        $employeeIds = $rows->pluck('employee_id')->unique()->values()->all();
        $tanggal = $rows->map(fn (Attendance $a) => $a->work_date instanceof Carbon
            ? $a->work_date->toDateString()
            : (string) $a->work_date)->unique()->values()->all();

        if (! $employeeIds || ! $tanggal) {
            return [];
        }

        // `tanggal` di-cast `date` sehingga tersimpan sebagai "Y-m-d 00:00:00";
        // whereIn dengan tanggal polos tidak akan cocok, jadi pakai rentang.
        $schedules = ShiftSchedule::with('pattern')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('tanggal', '>=', min($tanggal))
            ->whereDate('tanggal', '<=', max($tanggal))
            ->get();

        $out = [];
        foreach ($schedules as $s) {
            $out[$s->employee_id.'|'.$s->tanggal->toDateString()] = $s->label();
        }

        return $out;
    }

    /**
     * Label hari non-kerja untuk tanggal tertentu, atau null kalau hari kerja.
     *
     * Non-kerja = bukan anggota `hari_kerja` (ISO-8601: 1=Senin … 7=Minggu)
     * ATAU tanggalnya terdaftar di tabel `holidays`. Bila `$employee` diisi dan
     * punya hari kerja sendiri (B3), itu yang dipakai.
     */
    public static function nonWorkDayLabel(AttendanceSetting $s, Carbon $date, ?Employee $employee = null): ?string
    {
        $holiday = Holiday::whereDate('tanggal', $date->toDateString())->first();
        if ($holiday) {
            return 'Hari libur: '.$holiday->nama;
        }

        $hariKerja = $employee?->hariKerjaList($s) ?? $s->hariKerjaList();
        if (! in_array((string) $date->dayOfWeekIso, $hariKerja, true)) {
            return 'Bukan hari kerja ('.$date->locale('id')->isoFormat('dddd').')';
        }

        return null;
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

    /**
     * @param  int|null  $approverId  id admin wilayah yang sedang melihat.
     *                                Null = super admin (lihat semua wilayah).
     */
    public static function leavesFor(?int $regionId, ?int $approverId = null): array
    {
        return Leave::with(['employee.region', 'approver', 'approvedBy'])
            ->when($regionId, fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('region_id', $regionId)))
            // Level 0 masih di meja approver yang ditunjuk karyawan; level
            // berikutnya terbuka untuk admin wilayah mana pun di wilayah itu
            // (lihat CutiController::ensureApprover). Cuti tanpa approver —
            // akunnya sudah dihapus — tetap terlihat agar tidak menggantung.
            ->when($approverId, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('level', '>', 0)
                ->orWhere('approver_id', $approverId)
                ->orWhereNull('approver_id')))
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
                    'approver_id' => $l->approver_id,
                    'approver_nama' => $l->approver?->name ?? '',
                    'approved_by' => $l->approved_by,
                    'approved_by_nama' => $l->approvedBy?->name ?? '',
                    'createdAt' => $l->created_at?->toIsoString(),
                ];
            })->values()->all();
    }

    /**
     * @param  int|null  $approverId  id admin wilayah yang sedang melihat.
     *                                Null = super admin (lihat semua wilayah).
     */
    public static function lovesFor(?int $regionId, ?int $approverId = null): array
    {
        return ToleranceClaim::with(['employee.region', 'approver.region'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            // Hanya klaim yang ditujukan ke admin ini. Klaim tanpa approver
            // (akunnya sudah dihapus) tetap terlihat supaya bisa diurus.
            ->when($approverId, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('approver_id', $approverId)
                ->orWhereNull('approver_id')))
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

    /**
     * @param  int|array<int, int|string>|null  $siteIds  titik absensi karyawan
     *                                                    (utama + tambahan)
     * @return array<int, int>
     */
    private static function normalisasiSiteIds(int|array|null $siteIds): array
    {
        if ($siteIds === null) {
            return [];
        }

        return array_values(array_unique(array_map('intval', (array) $siteIds)));
    }

    public static function approversFor(?int $regionId, int|array|null $siteIds = null): array
    {
        // Approver = User role admin_wilayah (act as atasan) scoped to region +
        // salah satu titik absensi karyawan. Admin wilayah tanpa site_id
        // (kantor wilayah) selalu ikut. Kantor Pusat ditambahkan di akhir.
        $siteIds = self::normalisasiSiteIds($siteIds);

        $q = User::with('region')->where('role', 'admin_wilayah')->where('is_active', true);
        if ($regionId !== null) {
            $q->where(function ($qq) use ($regionId, $siteIds) {
                $qq->where(function ($q2) use ($regionId) {
                    $q2->where('region_id', $regionId)->whereNull('site_id');
                });

                if ($siteIds !== []) {
                    $qq->orWhereIn('site_id', $siteIds);
                }
            });
        }
        // fallback: if no site-scoped approver, still include kantor
        $rows = $q->orderBy('name')->get();
        if ($rows->isEmpty() && $regionId !== null) {
            $rows = User::with('region')->where('role', 'admin_wilayah')->where('region_id', $regionId)->where('is_active', true)->orderBy('name')->get();
        }

        // Pulang cepat & toleransi hanya disetujui Admin Wilayah. Kantor Pusat
        // tidak masuk daftar ini (berbeda dari approver cuti).
        return $rows
            ->unique('id')
            ->map(fn (User $u) => self::presentApprover($u))
            ->values()->all();
    }

    /**
     * Approver CUTI — hanya akun admin (kebijakan: "admin saja").
     *
     * Level 1–2 = admin_wilayah di wilayah/salah satu titik karyawan.
     * Level 3    = Kantor Pusat (super_admin), ikut ditampilkan sebagai approver final.
     * Akun non-admin (mis. akun karyawan) tidak pernah muncul di sini.
     */
    public static function leaveApproversFor(?int $regionId, int|array|null $siteIds = null): array
    {
        $siteIds = self::normalisasiSiteIds($siteIds);

        $wilayah = User::with('region')
            ->where('role', 'admin_wilayah')
            ->where('is_active', true)
            ->when($regionId !== null, fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('name')
            ->get();

        // Admin wilayah tanpa site_id = kantor wilayah → selalu boleh jadi approver.
        // Admin wilayah dengan site_id hanya relevan untuk titik karyawan itu.
        $wilayah = $wilayah->filter(fn (User $u) => $u->site_id === null
            || in_array((int) $u->site_id, $siteIds, true));

        $pusat = User::with('region')
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $wilayah->concat($pusat)
            ->unique('id')
            ->map(fn (User $u) => self::presentApprover($u) + ['level' => $u->role === 'super_admin' ? 3 : null])
            ->values()->all();
    }

    /** Bentuk data approver yang dipakai UI (Love & Cuti). */
    private static function presentApprover(User $u): array
    {
        return [
            'id' => $u->id,
            'nama' => $u->name,
            'nip' => $u->nip ?? '',
            'jabatan' => $u->jabatan ?? $u->role,
            'wilayah' => $u->region?->name ?? '',
            'regionId' => $u->region_id,
            'office_location_id' => $u->site_id,
            'scope' => $u->site_id ? ($u->region?->name ?? '') : ($u->region?->name ? 'Kantor '.str_replace(['Kab. ', 'Kota '], '', $u->region->name) : 'Kantor Pusat'),
            'email' => $u->email,
        ];
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
