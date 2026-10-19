<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Buku catatan pekerjaan saat absen pulang (kolom `catatan_pulang`).
 *
 * Kebijakan: wajib diisi, minimal 1 karakter, maksimal 500 karakter, dan
 * tidak bisa diedit lagi setelah tersimpan.
 */
class AttendanceCatatanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
    }

    /** Karyawan wilayah Gowa — punya titik penugasan. */
    private function karyawan(): Employee
    {
        return Employee::where('region_id', 2)->first();
    }

    /** Buka absen masuk hari ini supaya jalur absen pulang bisa diuji. */
    private function absenMasukHariIni(Employee $me): Attendance
    {
        return Attendance::create([
            'employee_id' => $me->id,
            'work_date' => now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:00:00',
            'status' => 'on_time',
            'lat_in' => $me->site->lat,
            'lng_in' => $me->site->lng,
            'distance_in_m' => 0,
            'site_id' => $me->site_id,
            'region_id' => $me->region_id,
        ]);
    }

    /** POST absen pulang dengan muatan dasar + tambahan dari pemanggil. */
    private function absenPulang(Employee $me, array $extra = [])
    {
        return $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-out', array_merge([
                'lat' => $me->site->lat,
                'lng' => $me->site->lng,
                'selfie_pulang' => UploadedFile::fake()->image('pulang.jpg', 200, 200),
            ], $extra));
    }

    private function rowHariIni(Employee $me): Attendance
    {
        return Attendance::where('employee_id', $me->id)
            ->whereDate('work_date', now('Asia/Makassar')->toDateString())
            ->firstOrFail();
    }

    public function test_catatan_pulang_tersimpan_dan_terbaca_admin(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);
        $catatan = 'Menyelesaikan laporan harian dan pengecekan jaringan';

        $this->absenPulang($me, ['catatan_pulang' => $catatan])
            ->assertRedirect('/karyawan/absensi')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $me->id,
            'catatan_pulang' => $catatan,
        ]);

        $this->actingAs($this->superAdmin(), 'web')
            ->get('/super-admin/attendances')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Attendances')
                ->where('attendances.0.catatan_pulang', $catatan));
    }

    public function test_catatan_pulang_wajib_diisi(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);

        $this->absenPulang($me)->assertSessionHasErrors('catatan_pulang');

        // Absen pulang tidak boleh tercatat kalau catatan kosong.
        $this->assertNull($this->rowHariIni($me)->clock_out_at);
    }

    public function test_catatan_pulang_spasi_saja_ditolak(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);

        $this->absenPulang($me, ['catatan_pulang' => '     '])
            ->assertSessionHasErrors('catatan_pulang');

        $this->assertNull($this->rowHariIni($me)->clock_out_at);
    }

    public function test_catatan_pulang_satu_karakter_diterima(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);

        $this->absenPulang($me, ['catatan_pulang' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $me->id,
            'catatan_pulang' => 'x',
        ]);
    }

    public function test_catatan_pulang_lebih_dari_500_karakter_ditolak(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);

        $this->absenPulang($me, ['catatan_pulang' => str_repeat('a', 501)])
            ->assertSessionHasErrors('catatan_pulang');

        $this->assertNull($this->rowHariIni($me)->clock_out_at);
    }

    public function test_catatan_pulang_muncul_di_rekap_perorangan(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);
        $catatan = 'Rapat koordinasi lapangan dan pengecekan pompa';

        $this->absenPulang($me, ['catatan_pulang' => $catatan])
            ->assertSessionHasNoErrors();

        $bulan = now('Asia/Makassar')->format('Y-m');

        $this->actingAs($this->superAdmin(), 'web')
            ->get("/super-admin/employees/{$me->id}/rekap?bulan={$bulan}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/EmployeeRekap')
                ->where('rekap.detailHari', fn ($hari) => collect($hari)->contains(
                    fn ($d) => ($d['pulang']['catatan'] ?? null) === $catatan
                )));
    }

    // ── D5: admin boleh mengoreksi catatan pekerjaan ──

    public function test_admin_bisa_mengoreksi_catatan_pekerjaan(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);
        $this->absenPulang($me, ['catatan_pulang' => 'Salah tulis'])->assertSessionHasNoErrors();

        $row = $this->rowHariIni($me);

        $this->actingAs($this->superAdmin(), 'web')
            ->put("/super-admin/attendances/{$row->id}/catatan", ['catatan_pulang' => 'Pengecekan pompa selesai'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Pengecekan pompa selesai', $row->fresh()->catatan_pulang);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.catatan_update', 'subject_id' => $row->id]);
    }

    public function test_karyawan_tidak_bisa_mengoreksi_catatan_lewat_rute_admin(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);
        $this->absenPulang($me, ['catatan_pulang' => 'Catatan asli'])->assertSessionHasNoErrors();

        $row = $this->rowHariIni($me);

        $this->actingAs($me, 'employee')
            ->put("/super-admin/attendances/{$row->id}/catatan", ['catatan_pulang' => 'Diubah karyawan'])
            ->assertRedirect('/login');

        $this->assertSame('Catatan asli', $row->fresh()->catatan_pulang);
    }

    public function test_admin_wilayah_tidak_bisa_mengoreksi_catatan_luar_wilayahnya(): void
    {
        $lain = Employee::where('region_id', 1)->firstOrFail();

        $row = Attendance::create([
            'employee_id' => $lain->id,
            'work_date' => now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:00:00',
            'clock_out_at' => '16:00:00',
            'status' => 'on_time',
            'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 0,
            'site_id' => $lain->site_id,
            'region_id' => $lain->region_id,
            'catatan_pulang' => 'Catatan asli',
        ]);

        $admin = User::where('role', 'admin_wilayah')->where('region_id', 2)->firstOrFail();

        $this->actingAs($admin, 'web')
            ->put("/admin/attendances/{$row->id}/catatan", ['catatan_pulang' => 'Diubah admin wilayah lain'])
            ->assertForbidden();

        $this->assertSame('Catatan asli', $row->fresh()->catatan_pulang);
    }

    public function test_koreksi_catatan_kosong_ditolak(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);

        $row = $this->rowHariIni($me);

        $this->actingAs($this->superAdmin(), 'web')
            ->put("/super-admin/attendances/{$row->id}/catatan", ['catatan_pulang' => '   '])
            ->assertSessionHasErrors('catatan_pulang');
    }

    // ── D8: catatan pekerjaan ikut ke rekap bulanan ──

    public function test_catatan_pekerjaan_ikut_ke_rekap_harian(): void
    {
        $me = $this->karyawan();
        $this->absenMasukHariIni($me);
        $catatan = 'Pengecekan pompa dan pembersihan saluran';
        $this->absenPulang($me, ['catatan_pulang' => $catatan])->assertSessionHasNoErrors();

        $hariIni = now('Asia/Makassar')->toDateString();
        [$tahun, $bulan] = array_map('intval', explode('-', now('Asia/Makassar')->format('Y-m')));

        $hari = collect(RekapPresenter::buildRekap($me->fresh(), $tahun, $bulan)['harian'])
            ->firstWhere('iso', $hariIni);

        $this->assertSame($catatan, $hari['catatan_pulang']);
    }
}
