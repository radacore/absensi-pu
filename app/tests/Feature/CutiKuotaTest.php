<?php

namespace Tests\Feature;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Support\CutiKuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kuota cuti tahunan (C9).
 *
 * Aturan yang dijaga berkas ini:
 *  - Hanya jenis "Tahunan" yang memotong kuota.
 *  - Kuota dihitung dalam HARI KERJA: akhir pekan (sesuai `hari_kerja`) dan
 *    hari libur nasional tidak dihitung.
 *  - "Menunggu" dan "Disetujui" memotong kuota, "Ditolak" tidak.
 *  - Pengajuan lintas tahun divalidasi terhadap kuota tahun masing-masing.
 *  - Besaran kuota diatur Super Admin lewat /super-admin/settings.
 *
 * Tanggal acuan: 2026-09-14 = Senin (lihat $this->jamKe()).
 *   2026-09-14..18 = Senin..Jumat (5 hari kerja)
 *   2026-09-19..20 = Sabtu..Minggu (0 hari kerja)
 */
class CutiKuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->jamKe('2026-09-14');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function jamKe(string $tanggal, string $jam = '08:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse("{$tanggal} {$jam}", 'Asia/Makassar'));
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function karyawan(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    /** Ubah kuota lewat HTTP, seperti Super Admin dari /super-admin/settings. */
    private function setKuota(int $max): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->put('/super-admin/settings', [
                'jamMasuk' => '07:30',
                'jamPulang' => '16:00',
                'toleransi' => 15,
                'loveMax' => 4,
                'cutiTahunanMax' => $max,
            ])
            ->assertSessionHasNoErrors();
    }

    /** Pengajuan cuti lewat HTTP sebagai karyawan. */
    private function ajukan(Employee $emp, string $jenis, string $mulai, string $selesai)
    {
        return $this->actingAs($emp, 'employee')
            ->from('/karyawan/cuti')
            ->post('/karyawan/cuti', [
                'jenis' => $jenis,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'alasan' => 'Keperluan keluarga yang penting',
            ]);
    }

    // ── 1. Perhitungan hari kerja ──────────────────────────────────────────

    public function test_akhir_pekan_dan_hari_libur_nasional_tidak_dihitung_hari_kerja(): void
    {
        $this->assertSame(5, CutiKuota::hariKerja('2026-09-14', '2026-09-18'), 'Senin–Jumat = 5 hari kerja');
        $this->assertSame(0, CutiKuota::hariKerja('2026-09-19', '2026-09-20'), 'Sabtu–Minggu = 0 hari kerja');
        $this->assertSame(6, CutiKuota::hariKerja('2026-09-14', '2026-09-21'), 'Senin–Senin = 6 hari kerja');

        Holiday::create(['tanggal' => '2026-09-16', 'nama' => 'Libur Uji Kuota', 'cuti_bersama' => false]);
        $this->assertSame(4, CutiKuota::hariKerja('2026-09-14', '2026-09-18'), 'Rabu libur → tersisa 4');
    }

    public function test_rentang_terbalik_menghasilkan_nol(): void
    {
        $this->assertSame(0, CutiKuota::hariKerja('2026-09-18', '2026-09-14'));
    }

    // ── 2. Kuota dasar & tampilan ──────────────────────────────────────────

    public function test_default_kuota_dua_belas_hari_kerja_terbaca_dari_seeder(): void
    {
        $this->assertSame(12, (int) AttendanceSetting::firstOrFail()->cuti_tahunan_max);
        $this->assertSame(12, CutiKuota::max());
    }

    public function test_halaman_cuti_mengirim_ringkasan_kuota_ke_inertia(): void
    {
        $emp = $this->karyawan();

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/cuti')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Karyawan/Cuti')
                ->where('kuota.max', 12)
                ->where('kuota.terpakai', 0)
                ->where('kuota.sisa', 12)
                ->where('kuota.tahun', 2026)
                ->where('kuota.jenisBerkuota', ['Tahunan']));
    }

    // ── 3. Penegakan kuota ────────────────────────────────────────────────

    public function test_pengajuan_tahunan_dalam_batas_kuota_diterima(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(5);

        $this->ajukan($emp, 'Tahunan', '2026-09-15', '2026-09-17')->assertSessionHasNoErrors();

        $this->assertSame('Tahunan', Leave::where('employee_id', $emp->id)->firstOrFail()->jenis);
    }

    public function test_pengajuan_tahunan_melebihi_sisa_kuota_ditolak_422(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(2);

        $this->ajukan($emp, 'Tahunan', '2026-09-15', '2026-09-17') // Selasa–Kamis = 3 hari kerja
            ->assertSessionHasErrors('selesai');

        $this->assertSame(0, Leave::count(), 'Pengajuan yang melebihi kuota tidak boleh tersimpan');
    }

    public function test_pengajuan_menunggu_sudah_memotong_kuota(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(3);

        Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Tahunan',
            'mulai' => '2026-09-15',
            'selesai' => '2026-09-17',
            'alasan' => 'Sudah menghabiskan kuota',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $this->assertSame(0, CutiKuota::ringkasan($emp->id, 2026)['sisa']);

        $this->ajukan($emp, 'Tahunan', '2026-09-18', '2026-09-18')->assertSessionHasErrors('selesai');
    }

    public function test_cuti_ditolak_tidak_memotong_kuota(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(1);

        Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Tahunan',
            'mulai' => '2026-09-14',
            'selesai' => '2026-09-18',
            'alasan' => 'Sudah ditolak',
            'status' => 'Ditolak',
            'level' => 1,
        ]);

        $this->assertSame(1, CutiKuota::ringkasan($emp->id, 2026)['sisa']);

        $this->ajukan($emp, 'Tahunan', '2026-09-15', '2026-09-15')->assertSessionHasNoErrors();
    }

    public function test_jenis_sakit_besar_dan_melahirkan_tidak_dibatasi_kuota(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(1);

        foreach (['Sakit', 'Besar', 'Melahirkan'] as $jenis) {
            $this->ajukan($emp, $jenis, '2026-09-14', '2026-09-18')->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Leave::where('employee_id', $emp->id)->count());
        $this->assertSame(1, CutiKuota::ringkasan($emp->id, 2026)['sisa'], 'Kuota tahunan tetap utuh');
    }

    public function test_membatalkan_pengajuan_mengembalikan_kuota(): void
    {
        $emp = $this->karyawan();
        $this->setKuota(3);

        $this->ajukan($emp, 'Tahunan', '2026-09-15', '2026-09-17')->assertSessionHasNoErrors();
        $this->assertSame(0, CutiKuota::ringkasan($emp->id, 2026)['sisa']);

        $cuti = Leave::where('employee_id', $emp->id)->firstOrFail();
        $this->actingAs($emp, 'employee')->delete("/karyawan/cuti/{$cuti->id}")->assertSessionHas('success');

        $this->assertSame(3, CutiKuota::ringkasan($emp->id, 2026)['sisa']);
    }

    // ── 4. Lintas tahun ───────────────────────────────────────────────────

    public function test_pengajuan_lintas_tahun_divalidasi_per_tahun(): void
    {
        $this->jamKe('2026-12-28'); // Senin
        $emp = $this->karyawan();
        $this->setKuota(1);

        // 2026-12-30 (Rabu) & 12-31 (Kamis) = 2 hari kerja di 2026 → melebihi 1.
        $this->ajukan($emp, 'Tahunan', '2026-12-30', '2027-01-04')
            ->assertSessionHasErrors('selesai');

        $this->assertSame(0, Leave::count());
    }

    public function test_terpakai_dihitung_per_tahun_untuk_cuti_yang_melewati_batas_tahun(): void
    {
        $emp = $this->karyawan();

        Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Tahunan',
            'mulai' => '2026-12-30',
            'selesai' => '2027-01-05',
            'alasan' => 'Libur akhir tahun',
            'status' => 'Disetujui',
            'level' => 3,
        ]);

        // 2026: Kamis 31 Des saja (30 Des Rabu, 31 Des Kamis → 2 hari kerja).
        $this->assertSame(2, CutiKuota::terpakai($emp->id, 2026));
        // 2027: 1, 4, 5 Jan (Jumat, Senin, Selasa) → 3 hari kerja; 2–3 Jan akhir pekan.
        $this->assertSame(3, CutiKuota::terpakai($emp->id, 2027));
    }

    // ── 5. Pengaturan ─────────────────────────────────────────────────────

    public function test_super_admin_dapat_mengubah_kuota_cuti_tahunan(): void
    {
        $this->setKuota(21);

        $this->assertSame(21, (int) AttendanceSetting::firstOrFail()->cuti_tahunan_max);
        $this->assertSame(21, CutiKuota::max());
    }

    public function test_admin_wilayah_tidak_dapat_mengubah_kuota_cuti_tahunan(): void
    {
        $admin = User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();

        $this->actingAs($admin, 'web')
            ->put('/admin/settings', [
                'jamMasuk' => '07:30',
                'jamPulang' => '16:00',
                'toleransi' => 15,
                'loveMax' => 4,
                'cutiTahunanMax' => 40,
            ])
            ->assertForbidden();

        $this->assertSame(12, (int) AttendanceSetting::firstOrFail()->cuti_tahunan_max);
    }

    public function test_kuota_di_luar_rentang_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->put('/super-admin/settings', [
                'jamMasuk' => '07:30',
                'jamPulang' => '16:00',
                'toleransi' => 15,
                'loveMax' => 4,
                'cutiTahunanMax' => 0,
            ])
            ->assertSessionHasErrors('cutiTahunanMax');

        $this->assertSame(12, (int) AttendanceSetting::firstOrFail()->cuti_tahunan_max);
    }

    public function test_pemanggil_lama_tanpa_cuti_tahunan_max_tetap_valid(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->put('/super-admin/settings', [
                'jamMasuk' => '07:30',
                'jamPulang' => '16:00',
                'toleransi' => 15,
                'loveMax' => 4,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(12, (int) AttendanceSetting::firstOrFail()->cuti_tahunan_max, 'Nilai lama tidak boleh tertimpa');
    }
}
