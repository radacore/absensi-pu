<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use App\Models\User;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RekapPresensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User { return User::where('role', 'super_admin')->first(); }
    private function adminGowa(): User { return User::where('email', 'admin.gowa@bbws-pj.go.id')->first(); }
    private function empGowa(): Employee { return Employee::where('region_id', 2)->first(); }
    private function empMaros(): Employee { return Employee::where('region_id', 3)->first(); }

    public function test_rekap_presenter_hitung_distribusi_hadir_cuti_dinas_tanpa_keterangan(): void
    {
        $emp = $this->empGowa();
        $year = 2026;
        $month = 9; // September 2026: 1 Sep = Selasa

        // 2 attendances hadir (weekday)
        Attendance::create(['employee_id' => $emp->id, 'work_date' => "$year-09-01", 'clock_in_at' => '07:25:00', 'clock_out_at' => '16:10:00', 'status' => 'on_time', 'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 10, 'site_id' => $emp->site_id, 'region_id' => $emp->region_id]);
        Attendance::create(['employee_id' => $emp->id, 'work_date' => "$year-09-02", 'clock_in_at' => '07:50:00', 'clock_out_at' => '16:10:00', 'status' => 'late', 'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 10, 'site_id' => $emp->site_id, 'region_id' => $emp->region_id]);

        // 1 cuti disetujui (3-4 Sep)
        Leave::create(['employee_id' => $emp->id, 'jenis' => 'Tahunan', 'mulai' => "$year-09-03", 'selesai' => "$year-09-04", 'alasan' => 'Uji rekap', 'status' => 'Disetujui', 'level' => 3]);

        // 1 dinas disetujui (7-8 Sep) — Sen-Sel
        DinasClaim::create(['employee_id' => $emp->id, 'nomor_surat' => '123/TEST/2026', 'tanggal_mulai' => "$year-09-07", 'tanggal_selesai' => "$year-09-08", 'tanggal_pengajuan' => "$year-09-01", 'keterangan' => 'Uji dinas', 'tujuan' => 'Bone', 'status' => 'Disetujui']);

        // Libur nasional 17 Agu 2026 (di luar bulan) tidak dihitung
        Holiday::create(['tanggal' => "$year-09-15", 'nama' => 'Uji Libur', 'cuti_bersama' => false]);

        $rekap = RekapPresenter::buildRekap($emp, $year, $month);

        $this->assertSame(2 + 2, $rekap['distribusi']['hadir'], 'hadir = 2 attendance + 2 dinas');
        $this->assertSame(2, $rekap['distribusi']['cuti']);
        $this->assertSame(2, $rekap['distribusi']['dinas']);
        $this->assertSame('September', $rekap['periode']['namaBulan']);
        $this->assertSame(2026, $rekap['periode']['year']);
        $this->assertSame(30, $rekap['periode']['lastDay']);

        // Metrik zona jam masuk (jam_masuk=07:30, toleransi=15 → batas 07:45)
        $this->assertSame(1, $rekap['metrikHadir']['tepat_waktu']); // 07:25 <= 07:30
        $this->assertSame(0, $rekap['metrikHadir']['toleransi_terlambat']); // tidak ada di 07:31-07:45
        $this->assertSame(1, $rekap['metrikHadir']['terlambat']); // 07:50 > 07:45
        // total di metrikHadir sama dengan hadir di distribusi (attendance + dinas)
        $this->assertSame(4, $rekap['metrikHadir']['total']);
    }

    public function test_rekap_presenter_hari_kerja_tanpa_absen_dan_cuti_dihitung_tanpa_keterangan(): void
    {
        $emp = $this->empGowa();
        $year = 2026;
        $month = 9;

        // Tidak ada attendance, cuti, dinas → semua hari kerja jadi tanpa keterangan
        $rekap = RekapPresenter::buildRekap($emp, $year, $month);

        // September 2026: 30 hari, weekend = 8 hari (Sabtu Minggu), sisanya 22 hari kerja
        $weekdayCount = 0;
        for ($d = 1; $d <= 30; $d++) {
            $dow = Carbon::create($year, $month, $d)->dayOfWeek;
            if ($dow !== 0 && $dow !== 6) $weekdayCount++;
        }
        $this->assertSame($weekdayCount, $rekap['distribusi']['tanpa_keterangan']);
    }

    public function test_rekap_presenter_hitung_menit_terlambat_dan_psw(): void
    {
        $emp = $this->empGowa();
        $year = 2026;
        $month = 9;

        // 1 Sep 2026 Selasa: jam masuk 08:00 (30 menit dari jam_masuk 07:30), pulang 15:30 (30 menit sebelum jam_pulang 16:00)
        Attendance::create(['employee_id' => $emp->id, 'work_date' => "$year-09-01", 'clock_in_at' => '08:00:00', 'clock_out_at' => '15:30:00', 'status' => 'late', 'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 10, 'site_id' => $emp->site_id, 'region_id' => $emp->region_id]);

        $rekap = RekapPresenter::buildRekap($emp, $year, $month);

        $row = collect($rekap['keterlambatan'])->first();
        $this->assertNotNull($row);
        $this->assertSame(30, $row['menit_terlambat']);
        $this->assertSame(30, $row['menit_psw']);
    }

    public function test_super_admin_bisa_lihat_rekap_karyawan_wilayah_manapun(): void
    {
        $emp = $this->empMaros();
        $bulan = now('Asia/Makassar')->format('Y-m');

        $this->actingAs($this->superAdmin())
            ->get("/super-admin/employees/{$emp->id}/rekap?bulan={$bulan}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/EmployeeRekap')->has('rekap.karyawan')->where('rekap.karyawan.id', $emp->id));
    }

    public function test_admin_wilayah_hanya_bisa_lihat_rekap_karyawan_wilayahnya(): void
    {
        $bulan = now('Asia/Makassar')->format('Y-m');

        // own region → ok
        $ownEmp = $this->empGowa();
        $this->actingAs($this->adminGowa())
            ->get("/admin/employees/{$ownEmp->id}/rekap?bulan={$bulan}")
            ->assertOk();

        // luar region → 403
        $otherEmp = $this->empMaros();
        $this->actingAs($this->adminGowa())
            ->get("/admin/employees/{$otherEmp->id}/rekap?bulan={$bulan}")
            ->assertForbidden();
    }

    public function test_karyawan_bisa_lihat_rekap_detail_sendiri(): void
    {
        $emp = $this->empGowa();
        $bulan = now('Asia/Makassar')->format('Y-m');

        $this->actingAs($emp, 'employee')
            ->get("/karyawan/rekap/detail?bulan={$bulan}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Karyawan/RekapDetail')->has('rekap.karyawan')->where('rekap.karyawan.id', $emp->id));
    }

    public function test_dinas_karyawan_crud_dan_admin_approve(): void
    {
        $emp = $this->empGowa();

        // Karyawan submit
        $this->actingAs($emp, 'employee')->from('/karyawan/dinas')->post('/karyawan/dinas', [
            'nomor_surat' => '2485/SPT/0627/2026',
            'tanggal_mulai' => now('Asia/Makassar')->addDays(2)->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->addDays(4)->toDateString(),
            'keterangan' => 'Monitoring pelaksanaan kegiatan',
            'tujuan' => 'Bone',
            'transportasi' => 'mobil',
            'pembebanan_anggaran' => 'Kendaraan Sewa',
        ]);

        $this->assertDatabaseHas('dinas_claims', ['employee_id' => $emp->id, 'nomor_surat' => '2485/SPT/0627/2026', 'status' => 'Menunggu']);

        // Admin approve — switch guard ke web
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        $dinas = DinasClaim::where('employee_id', $emp->id)->firstOrFail();
        $this->actingAs($this->adminGowa(), 'web')
            ->put("/admin/dinas/{$dinas->id}/approve")
            ->assertSessionHas('success');
        $this->assertSame('Disetujui', $dinas->fresh()->status);

        // Buat data dinas untuk karyawan luar wilayah secara langsung
        $otherEmp = $this->empMaros();
        $otherDinas = DinasClaim::create([
            'employee_id' => $otherEmp->id,
            'nomor_surat' => '999/TEST/2026',
            'tanggal_mulai' => now('Asia/Makassar')->addDays(2)->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->addDays(3)->toDateString(),
            'tanggal_pengajuan' => now('Asia/Makassar')->toDateString(),
            'keterangan' => 'Uji dinas luar wilayah',
            'tujuan' => 'Palopo',
            'status' => 'Menunggu',
        ]);

        // Admin Wilayah Gowa tidak boleh approve dinas Maros
        $this->actingAs($this->adminGowa(), 'web')
            ->put("/admin/dinas/{$otherDinas->id}/approve")
            ->assertForbidden();
    }

    public function test_holiday_crud_hanya_super_admin(): void
    {
        // Super admin bisa
        $this->actingAs($this->superAdmin())
            ->post('/super-admin/holidays', ['tanggal' => '2026-12-31', 'nama' => 'Uji Libur', 'cuti_bersama' => false])
            ->assertSessionHas('success');
        $h = Holiday::where('nama', 'Uji Libur')->first();
        $this->assertNotNull($h);
        $this->assertSame('2026-12-31', $h->tanggal->format('Y-m-d'));

        // Admin wilayah 403
        $this->actingAs($this->adminGowa())
            ->post('/admin/holidays', ['tanggal' => '2026-11-11', 'nama' => 'Uji Ditolak'])
            ->assertForbidden();
    }
}
