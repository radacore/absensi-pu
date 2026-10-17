<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\Site;
use App\Models\User;
use App\Support\AdminPresenter;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * WFA (C1–C8) dan lembur (A8), plus halaman jadwal karyawan (A7).
 *
 * Tanggal acuan: 2026-09-15 = Selasa (hari kerja), 2026-09-19 = Sabtu.
 */
class WfaLemburTest extends TestCase
{
    use RefreshDatabase;

    private const HARI_KERJA = '2026-09-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function empGowa(): Employee
    {
        return Employee::where('email', 'andi@bbws-pj.go.id')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function adminBone(): User
    {
        return User::where('email', 'admin.bone@bbws-pj.go.id')->firstOrFail();
    }

    private function jamKe(string $jam): void
    {
        Carbon::setTestNow(Carbon::parse(self::HARI_KERJA.' '.$jam, 'Asia/Makassar'));
    }

    private function clockInWfa(Employee $emp, array $override = [])
    {
        $site = Site::findOrFail(201);

        return $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-in', array_merge([
            'lat' => $site->lat + 0.2,
            'lng' => $site->lng + 0.2,
            'selfie' => UploadedFile::fake()->image('wfa.jpg', 200, 200),
            'jenis_kerja' => 'WFA',
            'wfa_alasan' => 'Mendampingi rapat dinas di luar kantor',
        ], $override));
    }

    // ── WFA ────────────────────────────────────────────────────────────────

    public function test_wfa_dicatat_menunggu_persetujuan_tanpa_cek_radius(): void
    {
        $this->jamKe('08:00:00');
        $emp = $this->empGowa();

        $this->clockInWfa($emp)->assertSessionHasNoErrors();

        // Koordinat di luar semua radius tetap diterima karena WFA tidak
        // terikat titik kantor (C1), dan absennya menunggu persetujuan (C4).
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $emp->id,
            'jenis_kerja' => 'WFA',
            'wfa_status' => 'Menunggu',
            'di_luar_titik' => false,
        ]);

        $att = Attendance::where('employee_id', $emp->id)->firstOrFail();
        $this->assertNotNull($att->lat_in);
        $this->assertNotNull($att->selfie_url, 'WFA tetap wajib swafoto (C1).');
    }

    public function test_alasan_wfa_wajib_diisi(): void
    {
        $this->jamKe('08:00:00');

        $this->clockInWfa($this->empGowa(), ['wfa_alasan' => ''])
            ->assertSessionHasErrors('wfa_alasan');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_pekerja_shift_tidak_boleh_wfa(): void
    {
        $this->jamKe('08:00:00');
        $emp = $this->empGowa();
        $emp->is_shift = true;
        $emp->save();

        $this->clockInWfa($emp)->assertSessionHasErrors('jenis_kerja');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_admin_wilayah_menyetujui_wfa(): void
    {
        $emp = $this->empGowa();
        $att = Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:00:00',
            'jenis_kerja' => 'WFA',
            'wfa_alasan' => 'Bekerja dari rumah',
            'wfa_status' => 'Menunggu',
            'status' => 'on_time',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/wilayah/wfa/{$att->id}/approve")
            ->assertSessionHas('success');

        $att->refresh();
        $this->assertSame('Disetujui', $att->wfa_status);
        $this->assertSame((int) $this->adminGowa()->id, (int) $att->wfa_approved_by);
        $this->assertNotNull($att->wfa_approved_at);
    }

    public function test_admin_wilayah_lain_tidak_boleh_memutuskan_wfa(): void
    {
        $emp = $this->empGowa();
        $att = Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:00:00',
            'jenis_kerja' => 'WFA',
            'wfa_alasan' => 'Bekerja dari rumah',
            'wfa_status' => 'Menunggu',
            'status' => 'on_time',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);

        $this->actingAs($this->adminBone(), 'web')
            ->put("/wilayah/wfa/{$att->id}/approve")
            ->assertForbidden();

        $this->assertSame('Menunggu', $att->refresh()->wfa_status);
    }

    public function test_penolakan_wfa_wajib_alasan(): void
    {
        $emp = $this->empGowa();
        $att = Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:00:00',
            'jenis_kerja' => 'WFA',
            'wfa_alasan' => 'Bekerja dari rumah',
            'wfa_status' => 'Menunggu',
            'status' => 'on_time',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/wilayah/wfa/{$att->id}/reject", ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/wilayah/wfa/{$att->id}/reject", ['note' => 'Tidak ada bukti pekerjaan'])
            ->assertSessionHas('success');

        $att->refresh();
        $this->assertSame('Ditolak', $att->wfa_status);
        $this->assertSame('Tidak ada bukti pekerjaan', $att->wfa_note);
    }

    // ── Lembur ─────────────────────────────────────────────────────────────

    public function test_lembur_diajukan_karyawan_lalu_disetujui_dan_masuk_rekap(): void
    {
        $this->jamKe('20:00:00');
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/lembur', [
                'tanggal' => self::HARI_KERJA,
                'jam_mulai' => '22:00',
                'jam_selesai' => '01:00',
                'keterangan' => 'Menutup laporan keuangan bulanan',
                'approver_id' => $this->adminGowa()->id,
            ])
            ->assertSessionHasNoErrors();

        $claim = OvertimeClaim::firstOrFail();
        // Lembur boleh melewati tengah malam: 22:00–01:00 = 180 menit.
        $this->assertSame(180, (int) $claim->menit);
        $this->assertSame('Menunggu', $claim->status);

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/wilayah/lembur/{$claim->id}/approve")
            ->assertSessionHas('success');

        $this->assertSame('Disetujui', $claim->refresh()->status);

        $rekap = RekapPresenter::buildRekap($emp, 2026, 9);
        $this->assertSame(180, $rekap['total_lembur_menit']);

        $hari = collect($rekap['harian'])->firstWhere('iso', self::HARI_KERJA);
        $this->assertNotNull($hari);
        $this->assertSame(180, $hari['lembur_menit']);
        $this->assertStringContainsString('Lembur 180 menit', (string) $hari['catatan']);
    }

    public function test_lembur_kurang_dari_lima_belas_menit_ditolak(): void
    {
        $this->jamKe('20:00:00');

        $this->actingAs($this->empGowa(), 'employee')
            ->post('/karyawan/lembur', [
                'tanggal' => self::HARI_KERJA,
                'jam_mulai' => '22:00',
                'jam_selesai' => '22:10',
                'keterangan' => 'Rapat singkat',
                'approver_id' => $this->adminGowa()->id,
            ])
            ->assertSessionHasErrors('jam_selesai');

        $this->assertDatabaseCount('overtime_claims', 0);
    }

    public function test_satu_pengajuan_menunggu_per_tanggal(): void
    {
        $this->jamKe('20:00:00');
        $emp = $this->empGowa();
        $payload = [
            'tanggal' => self::HARI_KERJA,
            'jam_mulai' => '20:00',
            'jam_selesai' => '22:00',
            'keterangan' => 'Pekerjaan tambahan',
            'approver_id' => $this->adminGowa()->id,
        ];

        $this->actingAs($emp, 'employee')->post('/karyawan/lembur', $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($emp, 'employee')->post('/karyawan/lembur', $payload)
            ->assertSessionHasErrors('tanggal');

        $this->assertDatabaseCount('overtime_claims', 1);
    }

    public function test_karyawan_hanya_bisa_membatalkan_pengajuan_menunggu(): void
    {
        $emp = $this->empGowa();
        $menunggu = OvertimeClaim::create([
            'employee_id' => $emp->id,
            'region_id' => $emp->region_id,
            'site_id' => $emp->site_id,
            'tanggal' => self::HARI_KERJA,
            'jam_mulai' => '20:00:00',
            'jam_selesai' => '22:00:00',
            'menit' => 120,
            'keterangan' => 'Pekerjaan tambahan',
            'approver_id' => $this->adminGowa()->id,
            'status' => 'Menunggu',
        ]);

        $this->actingAs($emp, 'employee')
            ->delete("/karyawan/lembur/{$menunggu->id}")
            ->assertSessionHas('success');
        $this->assertDatabaseCount('overtime_claims', 0);

        $disetujui = OvertimeClaim::create([
            'employee_id' => $emp->id,
            'region_id' => $emp->region_id,
            'site_id' => $emp->site_id,
            'tanggal' => self::HARI_KERJA,
            'jam_mulai' => '20:00:00',
            'jam_selesai' => '22:00:00',
            'menit' => 120,
            'keterangan' => 'Pekerjaan tambahan',
            'approver_id' => $this->adminGowa()->id,
            'status' => 'Disetujui',
        ]);

        $this->actingAs($emp, 'employee')
            ->delete("/karyawan/lembur/{$disetujui->id}")
            ->assertSessionHas('error');
        $this->assertDatabaseHas('overtime_claims', ['id' => $disetujui->id]);
    }

    public function test_lembur_tidak_menambah_hitungan_kehadiran(): void
    {
        $this->jamKe('20:00:00');
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')->post('/karyawan/lembur', [
            'tanggal' => self::HARI_KERJA,
            'jam_mulai' => '20:00',
            'jam_selesai' => '22:00',
            'keterangan' => 'Pekerjaan tambahan',
            'approver_id' => $this->adminGowa()->id,
        ])->assertSessionHasNoErrors();

        $claim = OvertimeClaim::firstOrFail();
        $claim->update(['status' => 'Disetujui']);

        $rekap = RekapPresenter::buildRekap($emp, 2026, 9);
        $this->assertSame(0, $rekap['distribusi']['hadir']);
        $this->assertSame(120, $rekap['total_lembur_menit']);
    }

    // ── Jadwal (A7) ────────────────────────────────────────────────────────

    public function test_halaman_jadwal_menampilkan_empat_minggu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Makassar'));

        $this->actingAs($this->empGowa(), 'employee')
            ->get('/karyawan/jadwal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Karyawan/Jadwal')
                ->where('isShift', false)
                ->has('minggu', 4)
                ->has('minggu.0.hari', 7)
                ->where('minggu.0.mulai', '2026-09-14'));
    }

    public function test_halaman_jadwal_pekerja_shift_tanpa_jadwal_ditandai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00', 'Asia/Makassar'));
        $emp = $this->empGowa();
        $emp->is_shift = true;
        $emp->save();

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/jadwal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Karyawan/Jadwal')
                ->where('isShift', true)
                ->has('minggu', 4)
                ->where('minggu.0.hari.0.label', 'Belum dijadwalkan'));
    }

    // ── Regresi tampilan ───────────────────────────────────────────────────

    public function test_halaman_lembur_karyawan_terbuka_dengan_riwayat_kosong(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/lembur')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Karyawan/Lembur')
                ->where('requests', [])
                ->where('menitBulanIni', 0));
    }

    public function test_riwayat_absen_menandai_pulang_di_luar_radius(): void
    {
        $emp = $this->empGowa();
        $emp->update(['boleh_pindah_titik' => true]);
        $site = Site::findOrFail(201);

        $this->jamKe('08:00:00');
        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $site->lat,
                'lng' => $site->lng,
                'selfie' => UploadedFile::fake()->image('masuk.jpg', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $this->jamKe('16:30:00');
        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-out', [
                'lat' => $site->lat + 0.2,
                'lng' => $site->lng + 0.2,
                'selfie_pulang' => UploadedFile::fake()->image('pulang.jpg', 200, 200),
                'catatan_pulang' => 'Pemeriksaan pintu air di luar titik',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Karyawan/Absensi')
                ->where('history.0.diLuarTitikPulang', true));
    }

    public function test_daftar_absensi_admin_memuat_tanda_di_luar_radius(): void
    {
        $emp = $this->empGowa();
        $emp->update(['boleh_pindah_titik' => true]);
        $site = Site::findOrFail(201);

        $this->jamKe('08:00:00');
        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $site->lat + 0.2,
                'lng' => $site->lng + 0.2,
                'selfie' => UploadedFile::fake()->image('masuk.jpg', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $this->jamKe('16:30:00');
        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-out', [
                'lat' => $site->lat + 0.2,
                'lng' => $site->lng + 0.2,
                'selfie_pulang' => UploadedFile::fake()->image('pulang.jpg', 200, 200),
                'catatan_pulang' => 'Pemeriksaan pintu air di luar titik',
            ])
            ->assertSessionHasNoErrors();

        $rows = AdminPresenter::attendancesFor(null);
        $baris = collect($rows)->firstWhere('employee_id', $emp->id);

        $this->assertNotNull($baris);
        $this->assertTrue($baris['di_luar_titik']);
        $this->assertTrue($baris['di_luar_titik_pulang']);
    }
}
