<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
