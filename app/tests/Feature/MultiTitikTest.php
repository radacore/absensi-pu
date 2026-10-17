<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use App\Support\AdminPresenter;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Absensi multi titik: satu karyawan boleh terdaftar di beberapa titik dalam
 * satu wilayah. Titik utama tetap di `employees.site_id`; sisanya di pivot.
 */
class MultiTitikTest extends TestCase
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
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function karyawanGowa(): Employee
    {
        return Employee::where('email', 'andi@bbws-pj.go.id')->firstOrFail();
    }

    private function payloadKaryawan(array $override = []): array
    {
        return array_merge([
            'nik' => '7371009999999999',
            'nip' => '',
            'nama' => 'Karyawan Multi Titik',
            'email' => 'multititik@bbws-pj.go.id',
            'gol' => 'III/a',
            'jabatan' => 'Staff',
            'unit' => 'Bidang Jalan',
            'status' => 'PNS',
            'region' => 'Kab. Gowa',
            'office_location_id' => 201,
            'office_location_ids' => [202],
        ], $override);
    }

    public function test_simpan_karyawan_dengan_titik_tambahan(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/super-admin/employees', $this->payloadKaryawan([
                // Titik utama tidak boleh ikut tersimpan sebagai titik tambahan.
                'office_location_ids' => [202, 201],
            ]))
            ->assertSessionHasNoErrors();

        $employee = Employee::where('email', 'multititik@bbws-pj.go.id')->firstOrFail();

        $this->assertSame(201, (int) $employee->site_id);
        $this->assertSame([201, 202], $employee->siteIds());
        $this->assertDatabaseHas('employee_sites', ['employee_id' => $employee->id, 'site_id' => 202]);
        $this->assertDatabaseMissing('employee_sites', ['employee_id' => $employee->id, 'site_id' => 201]);
    }

    public function test_titik_tambahan_lintas_wilayah_ditolak(): void
    {
        $sebelum = Employee::count();

        $this->actingAs($this->superAdmin())
            ->post('/super-admin/employees', $this->payloadKaryawan([
                'office_location_ids' => [301], // Maros, beda wilayah
            ]))
            ->assertSessionHasErrors('office_location_ids');

        // Karyawan barunya batal dibuat, bukan tertinggal setengah jadi.
        $this->assertSame($sebelum, Employee::count());
    }

    public function test_absen_masuk_di_titik_tambahan_lalu_pulang_di_titik_utama(): void
    {
        $emp = $this->karyawanGowa();
        $emp->sites()->attach(202);
        $siteTambahan = Site::findOrFail(202);
        $siteUtama = Site::findOrFail(201);

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $siteTambahan->lat,
                'lng' => $siteTambahan->lng,
                'selfie' => UploadedFile::fake()->image('masuk.jpg', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $hariIni = Carbon::now('Asia/Makassar')->toDateString();
        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', $hariIni)->firstOrFail();
        $this->assertSame(202, (int) $row->site_id);

        // Pulang di titik utama tetap boleh — titik pulang tidak dikunci.
        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-out', [
                'lat' => $siteUtama->lat,
                'lng' => $siteUtama->lng,
                'selfie_pulang' => UploadedFile::fake()->image('pulang.jpg', 200, 200),
                'catatan_pulang' => 'Pindah lokasi pemeriksaan bendungan',
            ])
            ->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertNotNull($row->clock_out_at);
        $this->assertSame(201, (int) $row->site_out_id);
    }

    public function test_absen_di_luar_semua_titik_ditolak(): void
    {
        $emp = $this->karyawanGowa();
        $emp->sites()->attach(202);
        $site = Site::findOrFail(201);

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $site->lat + 0.2,
                'lng' => $site->lng + 0.2,
                'selfie' => UploadedFile::fake()->image('luar.jpg', 200, 200),
            ])
            ->assertSessionHasErrors('distance');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_approver_mencakup_admin_di_titik_tambahan(): void
    {
        $emp = $this->karyawanGowa();
        $emp->sites()->attach(202);

        $adminTambahan = User::create([
            'name' => 'Admin Titik Pampang',
            'email' => 'admin.pampang@bbws-pj.go.id',
            'password' => 'password123',
            'role' => 'admin_wilayah',
            'region_id' => 2,
            'site_id' => 202,
            'is_active' => true,
        ]);

        $semua = collect(AdminPresenter::leaveApproversFor(2, $emp->siteIds()))->pluck('id')->all();
        $this->assertContains($adminTambahan->id, $semua);

        // Tanpa titik tambahan, admin titik itu tidak berwenang.
        $utamaSaja = collect(AdminPresenter::leaveApproversFor(2, [$emp->site_id]))->pluck('id')->all();
        $this->assertNotContains($adminTambahan->id, $utamaSaja);
    }

    public function test_hapus_titik_mempromosikan_titik_tambahan_karyawan(): void
    {
        $budi = Employee::where('email', 'budi@bbws-pj.go.id')->firstOrFail(); // utama 202
        $budi->sites()->attach(201);

        $this->actingAs($this->superAdmin())
            ->delete('/super-admin/sites/202')
            ->assertSessionHas('success');

        $budi->refresh();
        $this->assertSame(201, (int) $budi->site_id);
        $this->assertDatabaseMissing('employee_sites', ['employee_id' => $budi->id, 'site_id' => 201]);
        $this->assertDatabaseMissing('sites', ['id' => 202]);
    }

    public function test_keluarkan_karyawan_dari_titik_tambahan(): void
    {
        $emp = $this->karyawanGowa();
        $emp->sites()->attach(202);

        $this->actingAs($this->superAdmin())
            ->delete("/super-admin/sites/202/employees/{$emp->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('employee_sites', ['employee_id' => $emp->id, 'site_id' => 202]);
        $this->assertSame(201, (int) $emp->fresh()->site_id);
    }

    public function test_titik_utama_tidak_bisa_dikeluarkan_tanpa_pengganti(): void
    {
        $emp = $this->karyawanGowa(); // utama 201, tanpa titik tambahan

        $this->actingAs($this->superAdmin())
            ->delete("/super-admin/sites/201/employees/{$emp->id}")
            ->assertSessionHas('error');

        $this->assertSame(201, (int) $emp->fresh()->site_id);
    }

    /**
     * Absensi sehari dengan titik masuk dan titik pulang berbeda.
     *
     * @return array{0: Employee, 1: Attendance, 2: Site, 3: Site, 4: string}
     */
    private function absenDuaTitik(): array
    {
        $emp = $this->karyawanGowa();
        $siteMasuk = Site::findOrFail(202);
        $sitePulang = Site::findOrFail(201);
        $tanggal = Carbon::now('Asia/Makassar')->toDateString();

        $row = Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => $tanggal,
            'clock_in_at' => '07:15:00',
            'clock_out_at' => '16:05:00',
            'status' => 'on_time',
            'lat_in' => $siteMasuk->lat,
            'lng_in' => $siteMasuk->lng,
            'distance_in_m' => 5,
            'lat_out' => $sitePulang->lat,
            'lng_out' => $sitePulang->lng,
            'site_id' => $siteMasuk->id,
            'site_out_id' => $sitePulang->id,
            'region_id' => $emp->region_id,
        ]);

        return [$emp, $row, $siteMasuk, $sitePulang, $tanggal];
    }

    public function test_rekap_menampilkan_titik_masuk_dan_titik_pulang_terpisah(): void
    {
        [$emp, , $siteMasuk, $sitePulang, $tanggal] = $this->absenDuaTitik();

        $now = Carbon::now('Asia/Makassar');
        $rekap = RekapPresenter::buildRekap($emp, (int) $now->year, (int) $now->month);

        $detail = collect($rekap['detailHari'])->firstWhere('iso', $tanggal);
        $this->assertNotNull($detail);
        $this->assertSame($siteMasuk->nama_lokasi, $detail['masuk']['lokasi']);
        $this->assertSame($sitePulang->nama_lokasi, $detail['pulang']['lokasi']);
        $this->assertTrue($detail['berpindah_lokasi']);

        // Jarak pulang diukur ke titik pulang, bukan ke titik masuk — kalau
        // salah, angkanya akan tampak seperti absen di luar radius.
        $this->assertNotNull($detail['pulang']['radius']);
        $this->assertLessThanOrEqual(10, $detail['pulang']['radius']);

        $baris = collect($rekap['harian'])->firstWhere('iso', $tanggal);
        $this->assertTrue($baris['berpindah_lokasi']);
        $this->assertSame($siteMasuk->nama_lokasi, $baris['lokasi_masuk']);
        $this->assertSame($sitePulang->nama_lokasi, $baris['lokasi_pulang']);
        $this->assertStringContainsString('Berpindah lokasi', $baris['catatan']);
    }

    public function test_daftar_absensi_admin_membawa_titik_pulang(): void
    {
        [, $row, $siteMasuk, $sitePulang] = $this->absenDuaTitik();

        $baris = collect(AdminPresenter::attendancesFor(null))->firstWhere('id', $row->id);

        $this->assertNotNull($baris);
        $this->assertSame($siteMasuk->nama_lokasi, $baris['nama_titik']);
        $this->assertSame($sitePulang->nama_lokasi, $baris['nama_titik_pulang']);
        $this->assertSame((int) $sitePulang->id, $baris['office_location_id_out']);
        $this->assertTrue($baris['berpindah_lokasi']);
        $this->assertLessThanOrEqual(10, $baris['jarak_pulang']);
    }
}
