<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\EarlyLeaveRequest;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ShiftSchedule;
use App\Models\Site;
use App\Support\AdminPresenter;
use App\Support\Audit;
use App\Support\RekapPdf;
use App\Support\RekapPresenter;
use App\Support\ShiftResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site', 'sites']);

        $now = now('Asia/Makassar');
        $today = $now->toDateString();

        $isShift = (bool) $me->is_shift;
        $pattern = $isShift ? ShiftResolver::patternFor($me) : null;

        $history = Attendance::where('employee_id', $me->id)
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->limit(90)
            ->get()
            ->map(fn (Attendance $a) => [
                'id' => $a->id,
                'tgl' => $a->work_date instanceof Carbon ? $a->work_date->format('Y-m-d') : (string) $a->work_date,
                'datang' => $a->clock_in_at ? Carbon::parse($a->clock_in_at)->format('H:i') : '',
                'pulang' => $a->clock_out_at ? Carbon::parse($a->clock_out_at)->format('H:i') : '',
                'status' => $a->status ?? 'on_time',
                'jarak' => (int) ($a->distance_in_m ?? 0),
                'selfie' => $a->selfieRouteUrl('masuk'),
                'shift' => ($pattern && $a->shift_ke) ? $pattern->labelShift((int) $a->shift_ke) : null,
                'jenisKerja' => $a->jenis_kerja ?: 'WFO',
                'wfaStatus' => $a->wfa_status,
                'diLuarTitik' => (bool) $a->di_luar_titik,
                'diLuarTitikPulang' => (bool) $a->di_luar_titik_pulang,
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

        // Semua titik yang sah untuk absen: titik utama lalu titik tambahan.
        // UI memakai daftar ini untuk mencari titik terdekat dari GPS.
        $sites = collect([$me->site])
            ->filter()
            ->concat($me->sites)
            ->unique('id')
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama_lokasi' => $s->nama_lokasi,
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'radius' => (int) $s->radius_m,
                'address' => $s->address,
                'utama' => $s->id === $me->site_id,
            ])->all();

        $settings = AttendanceSetting::first();

        // Info gerbang hari libur → dipakai UI untuk mematikan tombol absen.
        // Pekerja shift tidak terkena gerbang ini (mereka bekerja menurut jadwal).
        $liburLabel = ($settings && $settings->absen_libur_aktif && ! $isShift)
            ? AdminPresenter::nonWorkDayLabel($settings, $now, $me)
            : null;
        $absenLibur = [
            'aktif' => (bool) ($settings?->absen_libur_aktif),
            'mode' => $settings?->absen_libur_mode ?: 'tolak',
            'hariIniLibur' => $liburLabel !== null,
            'label' => $liburLabel,
            'ditolak' => $liburLabel !== null && ($settings?->absen_libur_mode ?: 'tolak') === 'tolak',
        ];

        // Baris absensi yang masih terbuka (belum absen pulang). Untuk shift
        // malam, baris ini bertanggal kemarin.
        $openRow = Attendance::where('employee_id', $me->id)
            ->whereNull('clock_out_at')
            ->whereDate('work_date', '>=', $now->copy()->subDay()->toDateString())
            ->orderByDesc('work_date')
            ->first();

        // ── Info shift untuk halaman absen (hanya pekerja shift).
        //
        // Baris yang masih terbuka jadi acuan utama, bukan `currentShift()`.
        // Pukul 08.05 `currentShift()` sudah menunjuk shift malam berikutnya
        // (mulai 20.00 hari ini), padahal yang belum ditutup adalah shift malam
        // kemarin. Acuan yang dipakai di sini sama dengan gerbang absen pulang
        // di server (gerbangPulangCepat), jadi tombol "Absen pulang" tidak bisa
        // berbeda keputusan dengan server.
        $shift = null;
        $selesaiShift = null;

        if ($isShift) {
            $rentang = $openRow
                ? $this->rentangBaris($me, $openRow)
                : $this->rentangBerjalan($me, $now);

            if ($rentang) {
                $shift = [
                    'label' => $rentang['label'],
                    'tanggal' => $rentang['tanggal'],
                    'jamMulai' => $rentang['mulai']->format('H:i'),
                    'jamSelesai' => $rentang['selesai']->format('H:i'),
                ];
                $selesaiShift = $rentang['selesai'];
            }
        }

        $bolehPulang = $selesaiShift === null || $now->gte($selesaiShift);
        $pulangCepat = null;

        if ($shift !== null) {
            $req = EarlyLeaveRequest::where('employee_id', $me->id)
                ->whereDate('tanggal', $shift['tanggal'])
                ->orderByDesc('id')
                ->first();
            if ($req) {
                $pulangCepat = [
                    'status' => $req->status,
                    'jamRencana' => substr((string) $req->jam_rencana, 0, 5),
                    'alasan' => $req->alasan,
                    'note' => $req->note ?? '',
                ];
                if ($req->status === 'Disetujui') {
                    $bolehPulang = true;
                }
            }
        }

        $todayRow = Attendance::where('employee_id', $me->id)->whereDate('work_date', $today)->first();

        // Tanggal kerja yang relevan sekarang: tanggal shift yang berjalan,
        // tanggal baris terbuka, atau hari ini.
        $workDateAktif = $shift['tanggal']
            ?? ($openRow && $openRow->work_date instanceof Carbon ? $openRow->work_date->toDateString() : $today);

        return Inertia::render('Karyawan/Absensi', [
            'me' => [
                'id' => $me->id,
                'nama' => $me->name,
                'foto' => $me->foto_url ?: AdminPresenter::DEFAULT_AVATAR,
                'region' => $me->region?->name ?? '',
            ],
            'assigned' => $assigned,
            'sites' => $sites,
            'history' => $history,
            'settings' => $settings ? [
                'jamMasuk' => substr((string) $settings->jam_masuk, 0, 5),
                'jamPulang' => substr((string) $settings->jam_pulang, 0, 5),
                'toleransi' => (int) $settings->toleransi_late_menit,
            ] : ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15],
            'absenLibur' => $absenLibur,
            'alreadyToday' => (bool) $todayRow,
            'todayISO' => $today,
            'isShift' => $isShift,
            'shift' => $shift,
            'shiftHariIni' => $isShift ? ShiftResolver::labelFor($me, $now) : null,
            'bolehPulang' => $bolehPulang,
            // Izin pulang di titik berbeda. Mati = UI hanya menawarkan titik
            // tempat absen masuk; server tetap memeriksa ulang.
            'bolehPindahTitik' => (bool) $me->boleh_pindah_titik,
            'titikMasukAktif' => $openRow?->site_id,
            'pulangCepat' => $pulangCepat,
            'workDateAktif' => $workDateAktif,
        ]);
    }

    public function clockIn(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['site', 'region', 'sites']);
        if (! $me->site_id || ! $me->site) {
            throw ValidationException::withMessages(['site' => 'Titik belum di-assign — tidak bisa absen (422).']);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            // Kebijakan: swafoto live dari kamera wajib (bukan foto profil).
            // Disimpan di disk PRIVAT — hanya bisa dilihat lewat route
            // ber-otorisasi (AttendanceSelfieController), bukan URL publik.
            'selfie' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            // WFO (bawaan) atau WFA. WFA dipilih karyawan saat absen (C2).
            'jenis_kerja' => ['nullable', 'in:WFO,WFA'],
            'wfa_alasan' => ['nullable', 'string', 'max:500', 'required_if:jenis_kerja,WFA'],
        ]);

        $jenisKerja = $data['jenis_kerja'] ?? 'WFO';

        // Pekerja shift bekerja menurut jadwal dan titik yang ditetapkan, jadi
        // tidak mengenal WFA (C8).
        if ($jenisKerja === 'WFA' && $me->is_shift) {
            throw ValidationException::withMessages([
                'jenis_kerja' => 'Pekerja shift tidak memakai WFA — absen mengikuti jadwal shift.',
            ]);
        }

        $now = now('Asia/Makassar');
        $settings = AttendanceSetting::first();

        // Titik ditentukan dari koordinat yang dikirim, bukan dari pilihan
        // peramban: karyawan boleh absen di titik utama atau titik tambahan
        // mana pun yang masih dalam radiusnya.
        $terdekat = $this->titikTerdekat($me, (float) $data['lat'], (float) $data['lng']);
        $site = $terdekat['site'];
        $dist = $terdekat['jarak'];

        // WFA: radius kantor tidak berlaku (C1). WFO di luar radius semua titik
        // terdaftar tetap DICATAT, hanya ditandai (E6) — bukan ditolak.
        $diLuarTitik = $jenisKerja === 'WFO' && $dist > (int) $site->radius_m;

        // Pekerja shift mengikuti jadwal shift; karyawan lain mengikuti jam
        // masuk global. `$shift` berisi rentang shift yang sedang berlaku —
        // null berarti karyawan ini tidak sedang dalam sebuah shift.
        // Pola shift dicari memakai titik yang benar-benar dipakai hari ini.
        $shift = ShiftResolver::currentShift($me, $now, (int) $site->id);
        $workDate = $shift ? $shift['schedule']->tanggal->toDateString() : $now->toDateString();

        // Baris shift sebelumnya yang belum ditutup berarti shift itu belum
        // selesai secara administrasi. Absen masuk baru hanya akan menambah
        // baris menggantung, jadi karyawan diarahkan menutupnya dulu. Dibatasi
        // satu hari supaya baris lama yang terlantar tidak mengunci selamanya.
        if ($shift && Attendance::where('employee_id', $me->id)
            ->whereNull('clock_out_at')
            ->whereDate('work_date', '<', $workDate)
            ->whereDate('work_date', '>=', $now->copy()->subDay()->toDateString())
            ->exists()) {
            throw ValidationException::withMessages([
                'work_date' => 'Absen pulang shift sebelumnya belum tercatat. Absen pulang dulu sebelum absen masuk lagi.',
            ]);
        }

        if (Attendance::where('employee_id', $me->id)->whereDate('work_date', $workDate)->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Sudah absen hari ini.']);
        }

        // ── Gerbang hari kerja (Super Admin: absen_libur_aktif / absen_libur_mode)
        //   aktif + 'tolak' → absen di luar hari kerja ditolak (422).
        //   aktif + 'catat' → absen tetap dicatat, status 'libur'.
        //   nonaktif        → perilaku lama: dicatat seperti hari kerja biasa.
        // Pekerja shift TIDAK terkena gerbang ini: mereka bekerja menurut jadwal,
        // dan absen pada hari libur nasional dicatat sebagai absen biasa (P4).
        $liburLabel = null;
        if (! $shift) {
            $liburLabel = ($settings && $settings->absen_libur_aktif)
                ? AdminPresenter::nonWorkDayLabel($settings, $now, $me)
                : null;

            if ($liburLabel !== null && ($settings->absen_libur_mode ?: 'tolak') === 'tolak') {
                throw ValidationException::withMessages([
                    'work_date' => "Absen ditolak — {$liburLabel}. Hubungi Admin Wilayah bila perlu dispensasi.",
                ]);
            }
        }

        $tol = $settings ? (int) $settings->toleransi_late_menit : 15;

        if ($shift) {
            // Terlambat diukur dari jam mulai shift + toleransi global
            // (A4: satu toleransi berlaku untuk semua).
            $status = $now->gt($shift['mulai']->copy()->addMinutes($tol)) ? 'late' : 'on_time';
            $shiftKe = (int) $shift['schedule']->shift_ke;
        } else {
            $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
            [$h, $m] = array_map('intval', explode(':', $jamMasuk));
            $cutoffMin = $h * 60 + $m + $tol;
            $curMin = (int) $now->format('G') * 60 + (int) $now->format('i');
            $status = $curMin > $cutoffMin ? 'late' : 'on_time';
            $shiftKe = null;
            if ($liburLabel !== null) {
                $status = 'libur';
            }
        }

        $disk = config('filesystems.uploads.private_disk');
        $selfiePath = $request->file('selfie')->store("selfie/{$workDate}", $disk);

        try {
            Attendance::create([
                'employee_id' => $me->id,
                'work_date' => $workDate,
                'clock_in_at' => $now->format('H:i:s'),
                'shift_ke' => $shiftKe,
                'jenis_kerja' => $jenisKerja,
                'wfa_alasan' => $jenisKerja === 'WFA' ? $data['wfa_alasan'] : null,
                // WFA dicatat dulu, persetujuan menyusul (C4).
                'wfa_status' => $jenisKerja === 'WFA' ? 'Menunggu' : null,
                'status' => $status,
                'lat_in' => $data['lat'],
                'lng_in' => $data['lng'],
                'distance_in_m' => $dist,
                'di_luar_titik' => $diLuarTitik,
                'selfie_url' => $selfiePath,
                'site_id' => $site->id,
                'region_id' => $me->region_id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua clock-in nyaris bersamaan: unique(employee_id, work_date)
            // menangkap yang kalah balapan di level DB, bukan jadi 500.
            // Berkas yang sudah terunggah dibersihkan agar tidak jadi sampah.
            Storage::disk($disk)->delete($selfiePath);

            throw ValidationException::withMessages(['work_date' => 'Sudah absen hari ini.']);
        }

        $pesan = match ($status) {
            'late' => 'Absen tercatat — Terlambat',
            'libur' => "Absen tercatat — {$liburLabel}",
            default => 'Absen tercatat — Tepat waktu',
        };
        if ($shift) {
            $pesan = 'Absen tercatat — '.$shift['pattern']->labelShift($shiftKe)
                .($status === 'late' ? ' • Terlambat' : ' • Tepat waktu');
        }

        if ($jenisKerja === 'WFA') {
            $pesan = 'Absen WFA tercatat — menunggu persetujuan atasan.';
        } elseif ($diLuarTitik) {
            $pesan .= " • Di luar radius ({$dist} m), tetap dicatat dan ditandai.";
        }

        return back()->with('success', $pesan);
    }

    public function clockOut(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['site', 'sites']);
        if (! $me->site) {
            throw ValidationException::withMessages(['site' => 'Titik belum di-assign.']);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            // Kebijakan: swafoto live dari kamera wajib juga saat pulang.
            'selfie_pulang' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            // Buku catatan pekerjaan: wajib, minimal 1 karakter (spasi saja
            // sudah dinormalkan jadi string kosong oleh middleware TrimStrings).
            'catatan_pulang' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $now = now('Asia/Makassar');
        $today = $now->toDateString();

        $row = Attendance::where('employee_id', $me->id)->whereDate('work_date', $today)->first();
        if ($row && $row->clock_out_at) {
            throw ValidationException::withMessages(['work_date' => 'Sudah absen pulang hari ini.']);
        }

        if (! $row) {
            // Shift malam melewati tengah malam: baris absensinya bertanggal
            // kemarin (A3 dibatalkan — satu shift tetap SATU baris, K10 = YA).
            $row = Attendance::where('employee_id', $me->id)
                ->whereDate('work_date', $now->copy()->subDay()->toDateString())
                ->whereNull('clock_out_at')
                ->first();
        }

        if (! $row) {
            throw ValidationException::withMessages(['work_date' => 'Belum absen masuk hari ini.']);
        }

        // Gerbang pulang cepat (P2/P5): pekerja shift tidak boleh pulang
        // sebelum jam shiftnya berakhir kecuali pengajuan tanggal itu sudah
        // disetujui Admin Wilayah.
        $tolakPulang = $this->gerbangPulangCepat($me, $row, $now);
        if ($tolakPulang !== null) {
            throw ValidationException::withMessages(['clock_out_at' => $tolakPulang]);
        }

        // Titik pulang mengikuti izin per karyawan:
        //   boleh_pindah_titik = mati → hanya titik tempat ia absen masuk;
        //   boleh_pindah_titik = hidup → titik terdaftar mana pun.
        // Titik tetap ditentukan server dari koordinat yang dikirim.
        //
        // WFA tidak terikat titik kantor (C1), jadi pemeriksaan radius dilewati
        // sama sekali. Absen WFO di luar radius tetap dicatat dan ditandai
        // (E6), tidak lagi ditolak.
        $siteKeluar = null;
        $diLuarTitikPulang = false;

        if (! $row->isWfa()) {
            $titikMasuk = $row->site_id ? Site::find($row->site_id) : null;

            if ($titikMasuk !== null && ! $me->boleh_pindah_titik) {
                $siteKeluar = $titikMasuk;
                $dist = AdminPresenter::haversineM(
                    (float) $data['lat'],
                    (float) $data['lng'],
                    (float) $titikMasuk->lat,
                    (float) $titikMasuk->lng
                );

                // Izin pindah titik mati berarti karyawan hanya boleh pulang
                // di titik tempat ia absen masuk. Ini aturan tersendiri,
                // terpisah dari E6: titiknya tetap titik terdaftar, hanya
                // posisinya yang jauh dari titik itu.
                if ($dist > (int) $titikMasuk->radius_m) {
                    throw ValidationException::withMessages([
                        'distance' => 'Absen pulang harus di titik tempat Anda absen masuk ('.$titikMasuk->nama_lokasi.'). '
                            .'Minta Admin Wilayah mengaktifkan izin pindah titik bila memang perlu pulang dari titik lain.',
                    ]);
                }
            } else {
                if ($titikMasuk === null) {
                    // Titik masuknya sudah dihapus admin di tengah hari. Acuan
                    // jarak tidak ada lagi, jadi untuk hari itu karyawan boleh
                    // pulang di titik terdaftar mana pun. Kejadiannya tetap
                    // tercatat.
                    Audit::log(
                        'attendance.clock_out_titik_hilang',
                        subject: $row,
                        label: $me->name,
                        description: 'Absen pulang dilonggarkan karena titik masuk sudah dihapus',
                        meta: ['employee_id' => $me->id, 'site_id' => $row->site_id]
                    );
                }

                $terdekat = $this->titikTerdekat(
                    $me,
                    (float) $data['lat'],
                    (float) $data['lng'],
                    $titikMasuk !== null ? [$titikMasuk] : []
                );
                $siteKeluar = $terdekat['site'];
                $dist = $terdekat['jarak'];
                $diLuarTitikPulang = $dist > (int) $siteKeluar->radius_m;
            }
        }

        $tanggalKerja = $row->work_date instanceof Carbon
            ? $row->work_date->toDateString()
            : (string) $row->work_date;

        $disk = config('filesystems.uploads.private_disk');
        $pulangPath = $request->file('selfie_pulang')->store("selfie/{$tanggalKerja}", $disk);
        $row->update([
            'clock_out_at' => $now->format('H:i:s'),
            'lat_out' => $data['lat'],
            'lng_out' => $data['lng'],
            'selfie_pulang_url' => $pulangPath,
            'catatan_pulang' => $data['catatan_pulang'],
            'site_out_id' => $siteKeluar?->id,
            'di_luar_titik_pulang' => $diLuarTitikPulang,
        ]);

        $pesan = 'Absen pulang tercatat';
        if ($row->isWfa()) {
            $pesan .= ' — WFA';
        } elseif ($diLuarTitikPulang) {
            $pesan .= ' — di luar radius titik, tetap dicatat dan ditandai';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Titik absensi terdekat dari posisi karyawan.
     *
     * Kandidatnya titik utama ditambah titik tambahan. Yang dikembalikan
     * selalu titik terdekat — pemanggil yang memutuskan apakah jaraknya masih
     * dalam radius, supaya pesan galat bisa menyebut titik terdekat tersebut.
     *
     * @return array{site: Site, jarak: int}
     */
    private function titikTerdekat(Employee $me, float $lat, float $lng, array $tambahan = []): array
    {
        $kandidat = collect([$me->site])
            ->filter()
            ->concat($me->relationLoaded('sites') ? $me->sites : $me->sites()->get())
            // Titik masuk boleh ikut jadi kandidat walau sudah tidak terdaftar
            // lagi untuk karyawan ini (mis. admin mencabut titik tambahannya
            // di tengah hari).
            ->concat($tambahan)
            ->unique('id')
            ->values();

        $terdekat = null;

        foreach ($kandidat as $site) {
            $jarak = AdminPresenter::haversineM($lat, $lng, (float) $site->lat, (float) $site->lng);

            if ($terdekat === null || $jarak < $terdekat['jarak']) {
                $terdekat = ['site' => $site, 'jarak' => $jarak];
            }
        }

        return $terdekat;
    }

    /**
     * Pesan penolakan bila karyawan shift belum boleh pulang, atau null bila
     * sudah boleh (bukan pekerja shift, sudah lewat jam shift, atau pengajuan
     * pulang cepatnya disetujui).
     */
    private function gerbangPulangCepat($me, Attendance $row, Carbon $now): ?string
    {
        if (! $me->is_shift || ! $row->shift_ke) {
            return null;
        }

        // Pola shift mengikuti titik absen masuk hari itu, bukan selalu titik
        // utama — pekerja bisa berpindah titik antar hari.
        $pattern = ShiftResolver::patternFor($me, null, $row->site_id ? (int) $row->site_id : null);
        if (! $pattern) {
            return null;
        }

        $tanggal = $row->work_date instanceof Carbon
            ? $row->work_date->copy()->startOfDay()
            : Carbon::parse((string) $row->work_date)->startOfDay();

        $selesai = $pattern->windowFor($tanggal, (int) $row->shift_ke)['selesai'];
        if ($now->gte($selesai)) {
            return null;
        }

        $disetujui = EarlyLeaveRequest::where('employee_id', $me->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('status', 'Disetujui')
            ->exists();

        if ($disetujui) {
            return null;
        }

        return 'Belum waktunya pulang — shift berakhir pukul '.$selesai->format('H:i')
            .'. Ajukan pulang cepat lalu tunggu persetujuan Admin Wilayah.';
    }

    /**
     * Rentang shift untuk satu baris absensi.
     *
     * Pola diambil dari titik tempat karyawan absen masuk hari itu — sama
     * seperti gerbang absen pulang — supaya halaman absen dan server memakai
     * acuan yang sama.
     *
     * @return array{label: string, tanggal: string, mulai: Carbon, selesai: Carbon}|null
     */
    private function rentangBaris(Employee $me, Attendance $row): ?array
    {
        if (! $me->is_shift || ! $row->shift_ke) {
            return null;
        }

        $pattern = ShiftResolver::patternFor($me, null, $row->site_id ? (int) $row->site_id : null);
        if (! $pattern) {
            return null;
        }

        $tanggal = $row->work_date instanceof Carbon
            ? $row->work_date->copy()->startOfDay()
            : Carbon::parse((string) $row->work_date)->startOfDay();

        $w = $pattern->windowFor($tanggal, (int) $row->shift_ke);

        return [
            'label' => $pattern->labelShift((int) $row->shift_ke),
            'tanggal' => $tanggal->toDateString(),
            'mulai' => $w['mulai'],
            'selesai' => $w['selesai'],
        ];
    }

    /**
     * Rentang shift yang sedang berjalan menurut jadwal — dipakai hanya bila
     * belum ada baris absensi yang terbuka.
     *
     * @return array{label: string, tanggal: string, mulai: Carbon, selesai: Carbon}|null
     */
    private function rentangBerjalan(Employee $me, Carbon $now): ?array
    {
        $current = ShiftResolver::currentShift($me, $now);
        if (! $current) {
            return null;
        }

        return [
            'label' => $current['pattern']->labelShift((int) $current['schedule']->shift_ke),
            'tanggal' => $current['schedule']->tanggal->toDateString(),
            'mulai' => $current['mulai'],
            'selesai' => $current['selesai'],
        ];
    }

    /** Rekap: data untuk kalender + ringkasan satu bulan (bawaan: bulan berjalan). */
    public function rekap(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);
        $settings = AttendanceSetting::first();
        $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
        $jamPulang = $settings ? substr((string) $settings->jam_pulang, 0, 5) : '16:00';
        $loveMax = $settings ? (int) $settings->love_max : 4;

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Makassar');
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $rows = Attendance::where('employee_id', $me->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get();

        // Pekerja shift: label shift per baris + jadwal sebulan (A7 — karyawan
        // bisa melihat jadwalnya sendiri).
        $pattern = $me->is_shift ? ShiftResolver::patternFor($me) : null;
        $jadwalBulan = $me->is_shift
            ? ShiftSchedule::with('pattern')
                ->where('employee_id', $me->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->orderBy('tanggal')
                ->get()
                ->map(fn (ShiftSchedule $s) => [
                    'tgl' => $s->tanggal->toDateString(),
                    'shift' => $s->label(),
                    'jamMulai' => $s->jamMulai(),
                ])->values()->all()
            : [];

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
            'bulan' => sprintf('%04d-%02d', $year, $month),
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
                'shift' => ($pattern && $a->shift_ke) ? $pattern->labelShift((int) $a->shift_ke) : null,
            ])->values()->all(),
            'jadwalBulan' => $jadwalBulan,
            'isShift' => (bool) $me->is_shift,
            'cutiDates' => array_keys($cutiDates),
            'cutiDays' => $cutiDays,
            'hadir' => $rows->count(),
            'terlambat' => $rows->where('status', 'late')->count(),
        ]);
    }

    /**
     * Unduh rekap presensi diri sendiri sebagai berkas PDF (A4 landscape).
     *
     * Cakupan selalu milik karyawan yang sedang masuk — tidak ada parameter
     * karyawan, sehingga tidak mungkin mengunduh rekap orang lain.
     */
    public function rekapPdf(Request $request)
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($me, $year, $month);

        Audit::log(
            'karyawan.rekap.pdf',
            subject: $me,
            label: $me->name,
            description: "Karyawan unduh PDF rekap presensi sendiri untuk {$rekap['periode']['label']}",
            meta: ['periode' => $bulan]
        );

        return RekapPdf::unduh($rekap, $bulan);
    }
}
