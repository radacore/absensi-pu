<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Support\RekapPdf;
use App\Support\RekapPresenter;
use App\Support\TrenJamSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Ekspor rekap presensi: PDF asli (server) untuk admin, super admin, dan
 * karyawan; tombol cetak; serta rekap yang sadar jadwal shift.
 *
 * Yang dijaga di sini:
 *  - berkas PDF benar-benar dihasilkan (diawali %PDF) dan hanya untuk
 *    karyawan dalam cakupan admin yang meminta;
 *  - karyawan hanya bisa mengunduh rekap dirinya sendiri;
 *  - rekap pekerja shift dinilai dari jadwal shift, bukan akhir pekan;
 *  - template PDF tidak menandai hari tanpa jadwal sebagai "Libur".
 */
class RekapPdfTest extends TestCase
{
    use RefreshDatabase;

    private const BULAN = '2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Holiday::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00', 'Asia/Makassar'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminWilayah(int $regionId = 2): User
    {
        return User::where('role', 'admin_wilayah')->where('region_id', $regionId)->firstOrFail();
    }

    private function karyawan(int $regionId = 2): Employee
    {
        return Employee::where('region_id', $regionId)->whereNotNull('site_id')->firstOrFail();
    }

    private function absen(Employee $e, string $tanggal, string $masuk, string $pulang, array $extra = []): Attendance
    {
        return Attendance::create(array_merge([
            'employee_id' => $e->id,
            'work_date' => $tanggal,
            'clock_in_at' => $masuk,
            'clock_out_at' => $pulang,
            'status' => 'on_time',
            'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 10,
            'site_id' => $e->site_id, 'region_id' => $e->region_id,
        ], $extra));
    }

    /** Jadikan karyawan pekerja shift 3 shift, dengan jadwal pada tanggal-tanggal tertentu. */
    private function jadikanShift(Employee $e, array $jadwal): ShiftPattern
    {
        $p = ShiftPattern::create([
            'nama' => 'Pola 3 Shift', 'jumlah_shift' => 3,
            'jam_mulai_shift_1' => '07:00:00', 'is_active' => true,
        ]);

        $e->forceFill(['is_shift' => true])->save();

        foreach ($jadwal as $tanggal => $shiftKe) {
            ShiftSchedule::create([
                'employee_id' => $e->id, 'tanggal' => $tanggal,
                'shift_ke' => $shiftKe, 'shift_pattern_id' => $p->id,
            ]);
        }

        return $p;
    }

    // ── PDF admin / super admin ─────────────────────────────

    public function test_super_admin_bisa_mengunduh_rekap_pdf_karyawan(): void
    {
        $e = $this->karyawan();
        $this->absen($e, '2026-10-05', '07:25:00', '16:05:00');

        $res = $this->actingAs($this->superAdmin(), 'web')
            ->get("/super-admin/employees/{$e->id}/rekap/pdf?bulan=".self::BULAN);

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString(
            "rekap-presensi-{$e->nik}-".self::BULAN.'.pdf',
            $res->headers->get('content-disposition')
        );
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertGreaterThan(10_000, strlen($res->getContent()), 'PDF terlalu kecil — kemungkinan gagal render.');
    }

    public function test_admin_wilayah_bisa_mengunduh_rekap_pdf_karyawan_wilayahnya(): void
    {
        $e = $this->karyawan(2);

        $this->actingAs($this->adminWilayah(2), 'web')
            ->get("/admin/employees/{$e->id}/rekap/pdf?bulan=".self::BULAN)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_wilayah_tidak_bisa_mengunduh_rekap_pdf_luar_wilayahnya(): void
    {
        $luar = Employee::where('region_id', 3)->whereNotNull('site_id')->firstOrFail();

        $this->actingAs($this->adminWilayah(2), 'web')
            ->get("/admin/employees/{$luar->id}/rekap/pdf?bulan=".self::BULAN)
            ->assertForbidden();
    }

    public function test_unduhan_pdf_mencatat_jejak_audit(): void
    {
        $e = $this->karyawan(2);

        $this->actingAs($this->adminWilayah(2), 'web')
            ->get("/admin/employees/{$e->id}/rekap/pdf?bulan=".self::BULAN)
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.rekap.pdf',
            'subject_id' => $e->id,
        ]);
    }

    // ── PDF karyawan ────────────────────────────────────────

    public function test_karyawan_bisa_mengunduh_rekap_pdf_dirinya(): void
    {
        $e = $this->karyawan(2);
        $this->absen($e, '2026-10-05', '07:25:00', '16:05:00');

        $res = $this->actingAs($e, 'employee')->get('/karyawan/rekap/pdf?bulan='.self::BULAN);

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'karyawan.rekap.pdf', 'subject_id' => $e->id]);
    }

    public function test_karyawan_tidak_bisa_mengunduh_rekap_orang_lain(): void
    {
        $e = $this->karyawan(2);
        $lain = Employee::where('region_id', 2)->where('id', '!=', $e->id)->whereNotNull('site_id')->firstOrFail();

        // Endpoint karyawan tidak menerima parameter karyawan sama sekali.
        $res = $this->actingAs($e, 'employee')
            ->get('/karyawan/rekap/pdf?bulan='.self::BULAN.'&employee_id='.$lain->id);

        $res->assertOk();
        // Data yang dipakai tetap milik pengunduh (dicek dari audit log).
        $this->assertDatabaseHas('audit_logs', ['action' => 'karyawan.rekap.pdf', 'subject_id' => $e->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'karyawan.rekap.pdf', 'subject_id' => $lain->id]);
    }

    public function test_halaman_rekap_karyawan_membawa_bulan_aktif(): void
    {
        $e = $this->karyawan(2);

        $this->actingAs($e, 'employee')->get('/karyawan/rekap')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Karyawan/Rekap')->where('bulan', '2026-10'));

        $this->actingAs($e, 'employee')->get('/karyawan/rekap?bulan=2026-08')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Karyawan/Rekap')->where('bulan', '2026-08'));
    }

    // ── Template PDF ────────────────────────────────────────

    public function test_template_pdf_memuat_identitas_dan_tanda_tangan(): void
    {
        $e = $this->karyawan(2);
        $this->absen($e, '2026-10-05', '07:25:00', '16:05:00');

        $html = View::make('pdf.rekap-presensi', RekapPdf::data(RekapPresenter::buildRekap($e, 2026, 10)))->render();

        $this->assertStringContainsString('REKAP PRESENSI PERORANGAN', $html);
        $this->assertStringContainsString($e->nik, $html);
        $this->assertStringContainsString('Admin Wilayah', $html);
        $this->assertStringContainsString('Oktober 2026', $html);
    }

    public function test_template_pdf_pekerja_shift_memuat_kolom_shift_dan_legenda_libur(): void
    {
        $e = $this->karyawan(2);
        $this->jadikanShift($e, ['2026-10-05' => 1, '2026-10-08' => null]);

        $html = View::make('pdf.rekap-presensi', RekapPdf::data(RekapPresenter::buildRekap($e, 2026, 10)))->render();

        $this->assertStringContainsString('Pagi', $html);
        $this->assertStringContainsString('Libur', $html);
        $this->assertStringContainsString('Belum dijadwalkan', $html);
        $this->assertStringContainsString('Pola kerja', $html);
    }

    // ── Rekap sadar shift ───────────────────────────────────

    public function test_rekap_pekerja_shift_dinilai_dari_jadwal_bukan_akhir_pekan(): void
    {
        $e = $this->karyawan(2);
        // 2026-10-10 = Sabtu. Non-shift = akhir pekan; pekerja shift = hari kerja.
        $this->jadikanShift($e, ['2026-10-10' => 1]);
        $this->absen($e, '2026-10-10', '07:05:00', '19:05:00', ['shift_ke' => 1]);

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $sabtu = collect($rekap['harian'])->firstWhere('iso', '2026-10-10');
        $this->assertTrue($sabtu['is_hari_kerja'], 'Sabtu terjadwal shift harus dihitung hari kerja.');
        $this->assertSame('Pagi', $sabtu['shift']);
        $this->assertSame('07:00', $sabtu['shift_jam']);

        // 10 & 11 Okt tidak dijadwalkan → tidak dihitung "tanpa keterangan".
        $minggu = collect($rekap['harian'])->firstWhere('iso', '2026-10-11');
        $this->assertFalse($minggu['is_hari_kerja']);
        $this->assertFalse($minggu['is_tanpa_keterangan']);
    }

    public function test_rekap_pekerja_shift_menghitung_keterlambatan_dari_jam_shift(): void
    {
        $e = $this->karyawan(2);
        // Shift 2 (Siang) mulai 15:00. Absen 15:20 → terlambat 20 menit
        // meski jam kerja global (07:30) sudah jauh lewat.
        $this->jadikanShift($e, ['2026-10-05' => 2]);
        $this->absen($e, '2026-10-05', '15:20:00', '23:05:00', ['shift_ke' => 2, 'status' => 'late']);

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $this->assertSame(1, $rekap['metrikHadir']['terlambat']);
        $this->assertSame(20, $rekap['keterlambatan'][0]['menit_terlambat']);
    }

    public function test_libur_nasional_bukan_hari_kerja_bagi_non_shift_tapi_biasa_bagi_shift(): void
    {
        Holiday::create(['tanggal' => '2026-10-05', 'nama' => 'Uji Libur', 'cuti_bersama' => false]);

        $nonShift = $this->karyawan(2);
        $nonShiftRekap = RekapPresenter::buildRekap($nonShift, 2026, 10);
        $hariNonShift = collect($nonShiftRekap['harian'])->firstWhere('iso', '2026-10-05');
        $this->assertFalse($hariNonShift['is_hari_kerja']);

        $shift = Employee::where('region_id', 2)->whereNotNull('site_id')->where('id', '!=', $nonShift->id)->first() ?? $nonShift;
        $this->jadikanShift($shift, ['2026-10-05' => 1]);
        $shiftRekap = RekapPresenter::buildRekap($shift, 2026, 10);
        $hariShift = collect($shiftRekap['harian'])->firstWhere('iso', '2026-10-05');
        $this->assertTrue($hariShift['is_hari_kerja'], 'Pekerja shift tetap bekerja di hari libur nasional (P4).');
    }

    public function test_rekap_non_shift_tidak_berubah(): void
    {
        $e = $this->karyawan(2);
        $this->absen($e, '2026-10-05', '07:25:00', '16:05:00');
        $this->absen($e, '2026-10-06', '08:10:00', '15:30:00', ['status' => 'late']);

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $this->assertFalse($rekap['settings']['isShift']);
        $this->assertFalse($rekap['karyawan']['is_shift']);
        $this->assertNull($rekap['karyawan']['pola_shift']);

        $senin = collect($rekap['harian'])->firstWhere('iso', '2026-10-05');
        $this->assertNull($senin['shift']);
        $this->assertSame('Hadir tepat waktu', $senin['catatan']);

        $this->assertSame(1, $rekap['metrikHadir']['terlambat']);
        $this->assertSame(40, $rekap['keterlambatan'][0]['menit_terlambat']);
        $this->assertSame(30, $rekap['keterlambatan'][0]['menit_psw']);
    }

    public function test_cuti_dan_dinas_tetap_menang_atas_jadwal_shift(): void
    {
        $e = $this->karyawan(2);
        $this->jadikanShift($e, ['2026-10-05' => 1]);
        Leave::create([
            'employee_id' => $e->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-05', 'selesai' => '2026-10-05',
            'alasan' => 'Uji cuti shift', 'status' => 'Disetujui', 'level' => 1,
        ]);

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);
        $hari = collect($rekap['harian'])->firstWhere('iso', '2026-10-05');

        $this->assertTrue($hari['is_cuti']);
        $this->assertSame('Cuti Tahunan', $hari['catatan']);
        $this->assertFalse($hari['is_tanpa_keterangan']);
        $this->assertSame(1, $rekap['distribusi']['cuti']);
    }

    // ── Grafik tren versi PDF ───────────────────────────────

    public function test_grafik_tren_pdf_kosong_bila_tidak_ada_absensi(): void
    {
        $e = $this->karyawan(2);
        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $this->assertSame('', TrenJamSvg::render($rekap['tren'], 'masuk', $rekap['settings']));
    }

    public function test_grafik_tren_pdf_menggambar_titik_dan_rentang_shift(): void
    {
        $e = $this->karyawan(2);
        $this->jadikanShift($e, ['2026-10-05' => 3]);
        // Shift 3 (Malam) mulai 23:00 — di luar rentang tetap 06:00–10:00.
        $this->absen($e, '2026-10-05', '23:05:00', '07:00:00', ['shift_ke' => 3, 'status' => 'late']);

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);
        $svg = TrenJamSvg::render($rekap['tren'], 'masuk', $rekap['settings']);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<circle', $svg);
        // 23:05 harus punya label jam sendiri pada sumbu-Y (rentang mengikuti data).
        $this->assertStringContainsString('23:00', $svg);
    }

    public function test_grafik_non_shift_memakai_rentang_tetap(): void
    {
        $e = $this->karyawan(2);
        $this->absen($e, '2026-10-05', '07:25:00', '16:05:00');

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);
        $svg = TrenJamSvg::render($rekap['tren'], 'masuk', $rekap['settings']);

        // Rentang tetap: garis acuan jam masuk & batas toleransi ikut tampil.
        $this->assertStringContainsString('07:30', $svg);
        $this->assertStringContainsString('07:45', $svg);
    }

    // ── Data pendukung ──────────────────────────────────────

    public function test_pola_shift_pada_rekap_diambil_dari_cakupan_bertingkat(): void
    {
        $e = $this->karyawan(2);
        ShiftPattern::create([
            'nama' => 'Global', 'jumlah_shift' => 2,
            'jam_mulai_shift_1' => '08:00:00', 'is_active' => true,
        ]);
        $polaWilayah = ShiftPattern::create([
            'nama' => 'Wilayah Gowa', 'jumlah_shift' => 3,
            'jam_mulai_shift_1' => '07:00:00', 'region_id' => 2, 'is_active' => true,
        ]);
        $e->forceFill(['is_shift' => true])->save();

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $this->assertSame($polaWilayah->ringkasan(), $rekap['karyawan']['pola_shift']);
    }
}
