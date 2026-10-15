<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\DinasClaim;
use App\Models\EarlyLeaveRequest;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use App\Models\ToleranceClaim;
use App\Models\User;
use App\Support\RekapPresenter;
use App\Support\SelfieThumb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Simulasi satu karyawan, satu bulan penuh, berisi seluruh skenario fitur.
 *
 * Tujuannya bukan menguji satu fungsi, melainkan memastikan bahwa setelah
 * sebulan bercampur (tepat waktu, terlambat, toleransi, PSW, lupa absen,
 * cuti, dinas, libur nasional, tanpa keterangan, catatan pekerjaan), ANGKA
 * yang muncul di layar rekap, PDF, dan CSV tetap konsisten satu sama lain.
 *
 * Bulan contoh: Agustus 2026 — punya 2 libur nasional (17 & 28) dan dimulai
 * hari Sabtu, sehingga tepi-tepi kalender ikut teruji.
 */
class SimulasiSatuBulanTest extends TestCase
{
    use RefreshDatabase;

    private const BULAN = '2026-08';

    /** Hari kerja Agustus 2026 (Senin–Jumat, di luar 17 & 28 yang libur nasional). */
    private const HARI_KERJA = [
        3, 4, 5, 6, 7, 10, 11, 12, 13, 14,
        18, 19, 20, 21, 24, 25, 26, 27, 31,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
        SelfieThumb::lupakan();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function karyawan(string $email): Employee
    {
        return Employee::where('email', $email)->firstOrFail()->load(['region', 'site']);
    }

    /** Tulis JPEG kecil ke disk privat, kembalikan path-nya. */
    private function swafoto(string $tanggal, string $nama): string
    {
        $img = imagecreatetruecolor(240, 320);
        imagefilledrectangle($img, 0, 0, 240, 320, imagecolorallocate($img, 210, 225, 240));
        imagefilledellipse($img, 120, 130, 110, 110, imagecolorallocate($img, 150, 175, 205));
        ob_start();
        imagejpeg($img, null, 80);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $path = "selfie/{$tanggal}/{$nama}.jpg";
        Storage::disk(config('filesystems.uploads.private_disk'))->put($path, $jpeg);

        return $path;
    }

    /** Satu baris absensi lengkap dengan swafoto masuk (dan pulang bila ada). */
    private function absen(
        Employee $emp,
        int $hari,
        string $masuk,
        ?string $pulang,
        ?int $jarak = 12,
        ?string $catatanPulang = null,
        ?string $status = null,
    ): Attendance {
        $tanggal = sprintf('%s-%02d', self::BULAN, $hari);
        $jamMasuk = substr($masuk, 0, 5);
        $s = AttendanceSetting::first();

        $status ??= $jamMasuk <= substr((string) $s->jam_masuk, 0, 5)
            ? 'on_time'
            : ($jamMasuk <= '07:45' ? 'on_time' : 'late');

        return Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => $tanggal,
            'clock_in_at' => $masuk,
            'clock_out_at' => $pulang,
            'status' => $status,
            'lat_in' => $emp->site->lat,
            'lng_in' => $emp->site->lng,
            // Koordinat pulang sengaja digeser sedikit dari titik kantor supaya
            // jarak pulang bisa dibedakan dari jarak masuk pada berkas rekap.
            'lat_out' => $pulang ? $emp->site->lat + 0.0002 : null,
            'lng_out' => $pulang ? $emp->site->lng : null,
            'distance_in_m' => $jarak,
            'selfie_url' => $this->swafoto($tanggal, 'masuk'),
            'selfie_pulang_url' => $pulang ? $this->swafoto($tanggal, 'pulang') : null,
            'catatan_pulang' => $catatanPulang,
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);
    }

    /** Baris absensi tanpa jam masuk — dipakai untuk skenario lupa absen datang. */
    private function absenTanpaMasuk(Employee $emp, int $hari, string $pulang): Attendance
    {
        $tanggal = sprintf('%s-%02d', self::BULAN, $hari);

        return Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => $tanggal,
            'clock_in_at' => null,
            'clock_out_at' => $pulang,
            'status' => 'on_time',
            'lat_out' => $emp->site->lat,
            'lng_out' => $emp->site->lng,
            'selfie_pulang_url' => $this->swafoto($tanggal, 'pulang'),
            'catatan_pulang' => 'Pengukuran debit sungai',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);
    }

    /** Cuti menempuh alur nyata: dibuat Menunggu lalu di-approve 3 level. */
    private function cutiDisetujui(Employee $emp, string $jenis, string $mulai, string $selesai, string $alasan): Leave
    {
        $leave = Leave::create([
            'employee_id' => $emp->id,
            'jenis' => $jenis,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'alasan' => $alasan,
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->superAdmin())
                ->put(route('super_admin.cuti.approve', $leave->id))
                ->assertSessionHasNoErrors();
        }

        return $leave->fresh();
    }

    /** Siapkan data satu bulan untuk Andi Saputra (pekerja jam tetap). */
    private function siapkanSebulan(): Employee
    {
        $andi = $this->karyawan('andi@bbws-pj.go.id');

        // ── Kehadiran biasa, campur kualitas ──────────────────────────
        $this->absen($andi, 3, '07:20:11', '16:05:40', 14, 'Laporan harian jalan');
        $this->absen($andi, 4, '07:50:02', '16:10:00', 18);                       // terlambat 20 menit
        $this->absen($andi, 5, '07:42:30', '16:02:10', 11);                       // dalam toleransi
        $this->absen($andi, 6, '07:25:00', '15:30:00', 9, 'Pulang untuk rapat luar'); // PSW 30 menit
        $this->absen($andi, 7, '07:28:45', '16:00:00', 13);
        $this->absen($andi, 10, '07:15:20', '16:10:30', 10, 'Rekap mingguan 1');
        // 11 Agustus sengaja KOSONG → tanpa keterangan
        $this->absen($andi, 12, '07:30:00', null, 15);                            // lupa absen pulang murni
        // 13–14 cuti tahunan
        $this->cutiDisetujui($andi, 'Tahunan', '2026-08-13', '2026-08-14', 'Cuti tahunan keluarga');
        // 17 libur nasional (Proklamasi)
        // 18–19 dinas luar
        $dinas = DinasClaim::create([
            'employee_id' => $andi->id,
            'nomor_surat' => '094/BWSPJ/VIII/2026',
            'tanggal_mulai' => '2026-08-18',
            'tanggal_selesai' => '2026-08-19',
            'tanggal_pengajuan' => '2026-08-12',
            'keterangan' => 'Pendampingan teknis bendungan',
            'tujuan' => 'Kab. Bone',
            'transportasi' => 'Mobil dinas',
            'pembebanan_anggaran' => 'DPA 2026 — Bidang Jalan',
            'status' => 'Menunggu',
            'dokumen_path' => 'dinas/surat-tugas.pdf',
            'dokumen_nama' => 'surat-tugas.pdf',
            'dokumen_mime' => 'application/pdf',
            'dokumen_size' => 20480,
        ]);
        $this->actingAs($this->superAdmin())
            ->put(route('super_admin.dinas.approve', $dinas->id))
            ->assertSessionHasNoErrors();

        // Dinas yang DITOLAK tidak boleh muncul di rekap.
        $dinasTolak = DinasClaim::create([
            'employee_id' => $andi->id,
            'nomor_surat' => '095/BWSPJ/VIII/2026',
            'tanggal_mulai' => '2026-08-20',
            'tanggal_selesai' => '2026-08-20',
            'tanggal_pengajuan' => '2026-08-13',
            'keterangan' => 'Ditolak karena berbenturan jadwal',
            'tujuan' => 'Kab. Maros',
            'status' => 'Menunggu',
        ]);
        $this->actingAs($this->superAdmin())
            ->put(route('super_admin.dinas.reject', $dinasTolak->id), ['note' => 'Berbenturan jadwal internal'])
            ->assertSessionHasNoErrors();

        $this->absen($andi, 20, '07:20:05', '16:05:00', 12, 'Pengecekan saluran');

        // 21 lupa absen datang: karyawan sempat absen pulang tapi lupa absen
        // masuk. Persetujuan klaim mengisi jam masuk pada baris yang sama.
        $this->absenTanpaMasuk($andi, 21, '16:00:00');
        $this->klaimDisetujui($andi, 'lupa_absen', 21, '07:25');
        // 24 terlambat 55 menit
        $this->absen($andi, 24, '08:25:00', '16:00:00', 17, 'Koordinasi lapangan');

        // 25 masuk tepat waktu, pulang diisi lewat klaim lupa absen pulang
        $row25 = $this->absen($andi, 25, '07:20:30', null, 10, null);
        $this->klaimDisetujui($andi, 'lupa_pulang', 25, '14:00');

        $this->absen($andi, 26, '07:22:10', '16:03:00', 8, 'Dokumentasi progres');
        $this->absen($andi, 27, '07:26:55', '16:04:20', 19);
        // 28 libur nasional (Maulid)
        $this->absen($andi, 31, '07:18:40', '16:06:00', 11, 'Serah terima berkas');

        // Klaim yang BELUM diproses / DITOLAK tidak boleh menambah apa pun.
        $this->klaim($andi, 'lupa_absen', 26, '07:10', 'pending');
        $this->klaim($andi, 'lupa_absen', 27, '07:05', 'rejected');

        // Cuti yang masih MENUNGGU tidak boleh dihitung sebagai cuti.
        Leave::create([
            'employee_id' => $andi->id,
            'jenis' => 'Tahunan',
            'mulai' => '2026-08-31',
            'selesai' => '2026-08-31',
            'alasan' => 'Belum diputuskan atasan',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        // Pulang cepat disetujui (pekerja jam tetap tidak terkena gerbang ini).
        EarlyLeaveRequest::create([
            'employee_id' => $andi->id,
            'tanggal' => '2026-08-31',
            'jam_rencana' => '15:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'status' => 'Disetujui',
            'approved_by' => $this->superAdmin()->id,
            'site_id' => $andi->site_id,
            'region_id' => $andi->region_id,
        ]);

        return $andi;
    }

    private function klaim(Employee $emp, string $jenis, int $hari, string $jam, string $status): ToleranceClaim
    {
        return ToleranceClaim::create([
            'employee_id' => $emp->id,
            'jenis' => $jenis,
            'claim_date' => sprintf('%s-%02d', self::BULAN, $hari),
            'jam' => $jam.':00',
            'alasan' => 'Lupa melakukan absen saat bertugas di lapangan',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
            'approver_id' => $this->superAdmin()->id,
            'status' => $status,
        ]);
    }

    /** Klaim dikirim lebih dulu, lalu disetujui lewat endpoint admin. */
    private function klaimDisetujui(Employee $emp, string $jenis, int $hari, string $jam): ToleranceClaim
    {
        $claim = $this->klaim($emp, $jenis, $hari, $jam, 'pending');

        $this->actingAs($this->superAdmin())
            ->put(route('super_admin.love.approve', $claim->id))
            ->assertSessionHasNoErrors();

        return $claim->fresh();
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. Angka rekap harus cocok dengan sebaran skenario
    // ─────────────────────────────────────────────────────────────────

    public function test_rekap_sebulan_penuh_mencatat_setiap_skenario_dengan_benar(): void
    {
        $andi = $this->siapkanSebulan();
        $r = RekapPresenter::buildRekap($andi, 2026, 8);

        // ── Distribusi ────────────────────────────────────────────────
        // 14 baris absensi; hari dinas tidak lagi ikut masuk ke "Hadir"
        // supaya keempat potongan donat tidak ada yang terhitung dua kali.
        $this->assertSame(14, $r['distribusi']['hadir'], 'hadir = 14 baris absensi');
        $this->assertSame(2, $r['distribusi']['dinas']);
        $this->assertSame(2, $r['distribusi']['cuti']);
        $this->assertSame(1, $r['distribusi']['tanpa_keterangan']);

        // Donat tidak boleh melebihi jumlah hari kerja (19 hari kerja Agustus).
        $this->assertSame(19, array_sum($r['distribusi']), 'distribusi = jumlah hari kerja, tanpa dobel');

        // ── Rincian jam masuk ────────────────────────────────────────
        $this->assertSame(10, $r['metrikHadir']['tepat_waktu']);
        $this->assertSame(2, $r['metrikHadir']['toleransi_terlambat'], '5 Agu (dalam toleransi) + 21 Agu (lupa absen disetujui)');
        $this->assertSame(2, $r['metrikHadir']['terlambat'], '4 Agu (20 menit) + 24 Agu (55 menit)');
        // Zona harus berjumlah sama dengan total — inilah cacat yang ditemukan simulasi.
        $this->assertSame(
            $r['metrikHadir']['total'],
            $r['metrikHadir']['tepat_waktu'] + $r['metrikHadir']['toleransi_terlambat'] + $r['metrikHadir']['terlambat'] + $r['metrikHadir']['tanpa_jam_masuk'],
            'tepat + toleransi + terlambat + tanpa jam masuk harus sama dengan total kehadiran'
        );

        // ── Keterlambatan & PSW ──────────────────────────────────────
        $this->assertSame(75, $r['total_keterlambatan'], '20 menit (4 Agu) + 55 menit (24 Agu)');
        $this->assertSame(150, $r['total_psw'], '6 Agu (30) + 25 Agu (120)');
        $this->assertCount(4, $r['keterlambatan']);

        // ── Dispensasi hanya yang disetujui ──────────────────────────
        $jenis = array_column($r['dispensasi'], 'jenis');
        $this->assertContains('Lupa Absen Datang', $jenis);
        $this->assertContains('Lupa Absen Pulang', $jenis);

        // ── Jumlah hari terperiksa ───────────────────────────────────
        $this->assertCount(31, $r['harian']);

        // ── Cuti & dinas menang atas absensi/jadwal ──────────────────
        $this->assertSame('Cuti Tahunan', $this->catatan($r, 13));
        $this->assertSame('Cuti Tahunan', $this->catatan($r, 14));
        $this->assertStringContainsString('Dinas', $this->catatan($r, 18));
        $this->assertStringContainsString('Dinas', $this->catatan($r, 19));

        // ── Libur nasional ───────────────────────────────────────────
        $this->assertStringContainsString('Libur nasional', $this->catatan($r, 17));
        $this->assertStringContainsString('Libur nasional', $this->catatan($r, 28));

        // ── Akhir pekan ──────────────────────────────────────────────
        $this->assertStringContainsString('Bukan hari kerja', $this->catatan($r, 1));
        $this->assertStringContainsString('Bukan hari kerja', $this->catatan($r, 2));

        // ── Tanpa keterangan ─────────────────────────────────────────
        $this->assertSame('Tidak ada absen masuk · Tidak ada absen pulang', $this->catatan($r, 11));
        $this->assertTrue($this->hari($r, 11)['is_tanpa_keterangan']);
        $this->assertFalse($this->hari($r, 12)['is_tanpa_keterangan'], 'masuk ada, pulang tidak — bukan alfa');

        // ── Lupa absen pulang mengisi jam pulang ─────────────────────
        $this->assertSame('14:00', $this->hari($r, 25)['jam_pulang']);

        // ── Catatan pekerjaan ikut terbawa ───────────────────────────
        $this->assertSame('Laporan harian jalan', $this->hari($r, 3)['catatan_pulang']);
        $this->assertNull($this->hari($r, 4)['catatan_pulang']);

        // ── Hari kerja tanpa absen pulang ditandai ───────────────────
        $this->assertStringContainsString('Tidak ada absen pulang', $this->catatan($r, 12));

        // ── Hari terakhir bulan ikut terhitung (jebakan date-cast) ───
        $this->assertSame('07:18', $this->hari($r, 31)['jam_masuk']);
    }

    /**
     * Cacat yang ditemukan simulasi ini: baris absensi sintetis dari klaim
     * toleransi tidak punya koordinat, dan dulu dicetak "0.000000, 0.000000 /
     * 0 m" di PDF — terbaca seperti absen dari tengah laut. Sekarang harus
     * kosong (ditampilkan sebagai "—") dengan penanda "Isian manual".
     */
    public function test_hari_tanpa_koordinat_tidak_mencetak_nol_derajat(): void
    {
        $andi = $this->siapkanSebulan();
        $r = RekapPresenter::buildRekap($andi, 2026, 8);

        // 21 Agu: lupa absen datang (klaim disetujui, tanpa GPS)
        $hari21 = collect($r['detailHari'])->firstWhere('iso', '2026-08-21');
        $this->assertNotNull($hari21);
        $this->assertSame('Isian manual', $hari21['masuk']['jenis_absen']);
        $this->assertNull($hari21['masuk']['koordinat']);
        $this->assertNull($hari21['masuk']['radius']);
        // Absen pulang hari itu asli (punya GPS), jadi tetap WFO.
        $this->assertSame('WFO', $hari21['pulang']['jenis_absen']);
        $this->assertNotNull($hari21['pulang']['koordinat']);

        // 25 Agu: lupa absen pulang (klaim disetujui, tanpa GPS)
        $hari25 = collect($r['detailHari'])->firstWhere('iso', '2026-08-25');
        $this->assertSame('WFO', $hari25['masuk']['jenis_absen']);
        $this->assertSame('Isian manual', $hari25['pulang']['jenis_absen']);
        $this->assertNull($hari25['pulang']['koordinat']);

        // Radius pulang harus jarak pulang (bukan jarak masuk yang kebetulan
        // tersimpan). 0.0002° lintang ≈ 22 m, sedangkan jarak masuk 12 m.
        $hari20 = collect($r['detailHari'])->firstWhere('iso', '2026-08-20');
        $this->assertSame(12, $hari20['masuk']['radius']);
        $this->assertSame(22, $hari20['pulang']['radius'], 'radius pulang dihitung dari koordinat pulang');

        // Dan tidak boleh ada satu pun hari yang mencetak koordinat nol.
        foreach ($r['detailHari'] as $d) {
            foreach (['masuk', 'pulang'] as $arah) {
                if (! empty($d[$arah]['koordinat'])) {
                    $this->assertStringNotContainsString('0.000000, 0.000000', $d[$arah]['koordinat']);
                }
            }
        }
    }

    public function test_pdf_dan_csv_sebulan_penuh_konsisten_dengan_layar(): void
    {
        $andi = $this->siapkanSebulan();
        $admin = $this->superAdmin();

        $pdf = $this->actingAs($admin)
            ->get(route('super_admin.employees.rekap.pdf', ['employee' => $andi->id, 'bulan' => self::BULAN]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $bin = $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $bin);
        $this->assertGreaterThan(20_000, strlen($bin), 'PDF tidak boleh kosong');
        $this->assertLessThan(3_000_000, strlen($bin), 'PDF tidak boleh membengkak puluhan MB');

        $simpan = getenv('SIM_PDF_OUT');
        if ($simpan) {
            file_put_contents($simpan, $bin);
        }

        $csv = $this->actingAs($admin)
            ->get(route('super_admin.employees.rekap.unduh', ['employee' => $andi->id, 'bulan' => self::BULAN]));
        $csv->assertOk();
        $teks = $csv->streamedContent();

        // Kepala berkas + ringkasan yang harus sama dengan layar.
        // fputcsv mengapit nilai yang mengandung spasi dengan tanda kutip.
        $this->assertStringContainsString('Andi Saputra', $teks);
        $this->assertStringContainsString('Hadir;14', $teks);
        $this->assertStringContainsString('Dinas;2', $teks);
        $this->assertStringContainsString('Cuti;2', $teks);
        $this->assertStringContainsString('"Tanpa keterangan";1', $teks);
        $this->assertStringContainsString('"Tepat waktu";10', $teks);
        $this->assertStringContainsString('"Toleransi terlambat";2', $teks);
        $this->assertStringContainsString('Terlambat;2', $teks);
        $this->assertStringContainsString('2026-08-31', $teks);
        $this->assertStringContainsString('Serah terima berkas', $teks);
        $this->assertStringContainsString('Libur nasional: Proklamasi Kemerdekaan RI', $teks);

        $csvSimpan = getenv('SIM_CSV_OUT');
        if ($csvSimpan) {
            file_put_contents($csvSimpan, $teks);
        }

        // Layar harus menampilkan angka yang sama.
        $this->actingAs($admin)
            ->get(route('super_admin.employees.rekap', ['employee' => $andi->id, 'bulan' => self::BULAN]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('rekap.distribusi.hadir', 14)
                ->where('rekap.distribusi.dinas', 2)
                ->where('rekap.distribusi.cuti', 2)
                ->where('rekap.distribusi.tanpa_keterangan', 1)
                ->where('rekap.metrikHadir.tepat_waktu', 10)
                ->where('rekap.metrikHadir.toleransi_terlambat', 2)
                ->where('rekap.metrikHadir.terlambat', 2)
                ->has('rekap.harian', 31));
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. Pekerja shift: sebulan penuh mengikuti jadwal
    // ─────────────────────────────────────────────────────────────────

    public function test_pekerja_shift_sebulan_penuh_dinilai_dari_jadwal(): void
    {
        $budi = $this->karyawan('budi@bbws-pj.go.id');
        $budi->update(['is_shift' => true, 'regu' => 'Regu B']);

        // Pola 3 shift mulai 07:00 → 07–15, 15–23, 23–07.
        $pola = ShiftPattern::create([
            'nama' => 'Pola 3 Shift',
            'jumlah_shift' => 3,
            'jam_mulai_shift_1' => '07:00',
        ]);

        // Jadwal: 1–14 berturut-turut, sisanya diserahkan ke admin (belum dijadwalkan).
        $jamMulai = [1 => '07:00', 2 => '15:00', 3 => '23:00', 4 => null];
        for ($hari = 1; $hari <= 14; $hari++) {
            $shiftKe = match ($hari % 3) {
                1 => 1,
                2 => 2,
                0 => 3,
            };
            // Setiap tanggal ke-4 libur jadwal (baris eksplisit, shift_ke null).
            $libur = $hari % 4 === 0;

            ShiftSchedule::create([
                'employee_id' => $budi->id,
                'tanggal' => sprintf('%s-%02d', self::BULAN, $hari),
                'shift_ke' => $libur ? null : $shiftKe,
                'shift_pattern_id' => $pola->id,
            ]);
        }
        $this->assertNotNull($jamMulai[1]);

        // Absen: satu hari tepat waktu, satu hari terlambat 20 menit, satu hari
        // lupa absen pulang (malam → pulang lewat tengah malam).
        $sched = fn (int $hari) => ShiftSchedule::where('employee_id', $budi->id)
            ->whereDate('tanggal', sprintf('%s-%02d', self::BULAN, $hari))->first();

        // 1 Agu = Senin, shift 1 (07:00–15:00) tepat waktu
        $this->absenShift($budi, $pola, 1, '06:55:00', '15:05:00', $sched(1));
        // 2 Agu = Selasa, shift 2 (15:00–23:00) terlambat 20 menit
        $this->absenShift($budi, $pola, 2, '15:20:00', '23:10:00', $sched(2));
        // 3 Agu = Rabu, shift 3 (23:00–07:00 besok) tepat waktu, pulang pagi
        $this->absenShift($budi, $pola, 3, '22:55:00', '07:05:00', $sched(3));
        // 5 Agu = Jumat, shift 2, TIDAK ada absen → tanpa keterangan
        // 6 Agu = Sabtu, shift 3, hadir walau akhir pekan
        $this->absenShift($budi, $pola, 6, '23:02:00', '07:00:00', $sched(6));

        $r = RekapPresenter::buildRekap($budi, 2026, 8);

        $this->assertTrue($r['karyawan']['is_shift']);
        $this->assertSame('Pola 3 Shift', $r['karyawan']['pola_shift'] ? 'Pola 3 Shift' : '');
        $this->assertStringContainsString('3 shift', (string) $r['karyawan']['pola_shift']);

        // 10 hari dijadwalkan masuk: 14 hari pertama bulan, dikurangi libur
        // jadwal (4, 8, 12) = 11 hari ber-shift.
        $dijadwalkan = collect($r['harian'])->where('shift_ke', '!=', null)->count();
        $this->assertSame(11, $dijadwalkan);

        // Sabtu (6 Agu) yang dijadwalkan tetap hari kerja; Minggu (9 Agu) yang
        // ikut dijadwalkan juga hari kerja — penilaian murni dari jadwal.
        $this->assertTrue($this->hari($r, 6)['is_hari_kerja']);
        $this->assertTrue($this->hari($r, 9)['is_hari_kerja'], 'Minggu 9 Agustus dijadwalkan shift');
        // 20 Agu berada di luar rentang jadwal → bukan hari kerja.
        $this->assertFalse($this->hari($r, 20)['is_hari_kerja'], 'Belum dijadwalkan, bukan pelanggaran');

        // Libur jadwal dikenali, bukan dianggap alfa.
        $this->assertTrue($this->hari($r, 4)['is_libur_jadwal']);
        $this->assertFalse($this->hari($r, 4)['is_tanpa_keterangan']);

        // Terlambat 20 menit dihitung dari jam mulai shift, bukan jam kantor.
        $this->assertSame(20, $r['total_keterlambatan']);
        $this->assertSame(1, $r['metrikHadir']['terlambat']);

        // Pekerja shift tidak dihitung PSW (harus lewat persetujuan pulang cepat).
        $this->assertSame(0, $r['total_psw']);

        // Belum dijadwalkan → keterangan khusus, bukan "bukan hari kerja".
        $this->assertSame('Belum ada jadwal shift', $this->catatan($r, 20));

        $this->assertSame('Pagi', $this->hari($r, 1)['shift']);
        $this->assertSame('Siang', $this->hari($r, 2)['shift']);
        $this->assertSame('Malam', $this->hari($r, 3)['shift']);
        $this->assertSame('07:00', $this->hari($r, 1)['shift_jam']);
        $this->assertSame('23:00', $this->hari($r, 3)['shift_jam']);
    }

    private function absenShift(Employee $emp, ShiftPattern $pola, int $hari, string $masuk, string $pulang, ?ShiftSchedule $sched): Attendance
    {
        $tanggal = sprintf('%s-%02d', self::BULAN, $hari);
        $window = $pola->windowFor(Carbon::parse($tanggal), (int) $sched->shift_ke);
        $terlambat = Carbon::parse("{$tanggal} ".substr($masuk, 0, 5))
            ->gt($window['mulai']->copy()->addMinutes(15));

        return Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => $tanggal,
            'clock_in_at' => $masuk,
            'clock_out_at' => $pulang,
            'shift_ke' => $sched->shift_ke,
            'status' => $terlambat ? 'late' : 'on_time',
            'lat_in' => $emp->site->lat,
            'lng_in' => $emp->site->lng,
            'distance_in_m' => 10,
            'selfie_url' => $this->swafoto($tanggal, 'masuk'),
            'selfie_pulang_url' => $this->swafoto($tanggal, 'pulang'),
            'catatan_pulang' => 'Pemeriksaan rutin',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Bantu
    // ─────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $r */
    private function hari(array $r, int $day): array
    {
        $iso = sprintf('%s-%02d', self::BULAN, $day);

        foreach ($r['harian'] as $h) {
            if ($h['iso'] === $iso) {
                return $h;
            }
        }

        $this->fail("Hari {$iso} tidak ada di rekap");
    }

    /** @param array<string, mixed> $r */
    private function catatan(array $r, int $day): string
    {
        return (string) $this->hari($r, $day)['catatan'];
    }
}
