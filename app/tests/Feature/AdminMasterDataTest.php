<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->first();
    }

    public function test_super_admin_region_index_returns_all_scoped_region_rows(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/super-admin/regions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Regions')
                ->has('regions', 24)
                ->where('regions.0.name', 'Kota Makassar')
                ->has('employees', 5));
    }

    public function test_wilayah_admin_region_index_only_sees_own_region(): void
    {
        $this->actingAs($this->adminGowa())
            ->get('/admin/regions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Regions')
                ->where('regions.0.name', 'Kab. Gowa')
                ->has('regions', 1)
                ->has('employees', 3));
    }

    public function test_super_admin_can_create_update_and_delete_region(): void
    {
        $this->actingAs($this->superAdmin());

        $this->from('/super-admin/regions')->post('/super-admin/regions', [
            'name' => 'Kab. Test Baru',
            'tipe' => 'cabang',
            'kantor' => 'Kantor Wilayah Test',
            'address' => 'Jl. Test No.1',
        ])->assertRedirect('/super-admin/regions');
        $this->assertDatabaseHas('regions', ['name' => 'Kab. Test Baru']);

        $region = Region::where('name', 'Kab. Test Baru')->first();
        $this->from('/super-admin/regions')->put("/super-admin/regions/{$region->id}", [
            'name' => 'Kab. Test Baru 2',
            'tipe' => 'cabang',
            'kantor' => 'Kantor Baru',
            'address' => 'Jl. Baru',
        ])->assertRedirect('/super-admin/regions');
        $this->assertDatabaseHas('regions', ['name' => 'Kab. Test Baru 2']);

        $this->from('/super-admin/regions')->delete("/super-admin/regions/{$region->id}")->assertRedirect('/super-admin/regions');
        $this->assertDatabaseMissing('regions', ['name' => 'Kab. Test Baru 2']);
    }

    public function test_wilayah_admin_cannot_manage_other_region(): void
    {
        $this->actingAs($this->adminGowa());

        // wilayah tidak bisa menambah wilayah baru
        $this->from('/admin/regions')->post('/admin/regions', [
            'name' => 'Kab. Test',
            'tipe' => 'cabang',
            'kantor' => 'Kantor',
            'address' => 'Jl. Test',
        ])->assertForbidden();
        $this->assertDatabaseMissing('regions', ['name' => 'Kab. Test']);

        // wilayah tidak bisa menghapus wilayah (termasuk milik sendiri)
        $this->from('/admin/regions')->delete('/admin/regions/2')->assertForbidden();
        $this->assertDatabaseHas('regions', ['id' => 2]);

        $maros = Region::where('slug', 'kab-maros')->first();
        $this->from('/admin/regions')->put("/admin/regions/{$maros->id}", [
            'name' => 'Kab. Maros',
            'tipe' => 'cabang',
            'kantor' => 'X',
            'address' => 'X',
        ])->assertForbidden();
    }

    public function test_employee_create_update_move_and_delete(): void
    {
        $this->actingAs($this->superAdmin());

        $this->from('/super-admin/employees')->post('/super-admin/employees', [
            'nik' => '7371009999999999',
            'nip' => '',
            'nama' => 'Karyawan Test',
            'email' => 'test.karyawan@bbws-pj.go.id',
            'gol' => '-',
            'jabatan' => 'Staff',
            'unit' => 'Bidang Jalan',
            'status' => 'Kontrak',
            'region' => 'Kab. Gowa',
            'office_location_id' => 201,
        ])->assertRedirect('/super-admin/employees');

        $emp = Employee::where('email', 'test.karyawan@bbws-pj.go.id')->first();
        $this->assertNotNull($emp);
        $this->assertSame(2, $emp->region_id);

        $this->from('/super-admin/employees')->put("/super-admin/employees/{$emp->id}", [
            'nik' => '7371009999999999',
            'nip' => '',
            'nama' => 'Karyawan Test Update',
            'email' => 'test.karyawan@bbws-pj.go.id',
            'gol' => 'III/c',
            'jabatan' => 'Analis',
            'unit' => 'Bidang Air',
            'status' => 'PPPK',
            'region' => 'Kab. Gowa',
            'office_location_id' => 202,
        ])->assertRedirect('/super-admin/employees');
        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'jabatan' => 'Analis', 'site_id' => 202]);

        // pindah kantor
        $this->from('/super-admin/regions/2/sites/201')
            ->put('/super-admin/sites/201/move', ['employee_id' => $emp->id])
            ->assertRedirect('/super-admin/regions/2/sites/201');
        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'site_id' => 201]);

        $this->from('/super-admin/employees')->delete("/super-admin/employees/{$emp->id}")->assertRedirect('/super-admin/employees');
        $this->assertDatabaseMissing('employees', ['id' => $emp->id]);
    }

    public function test_site_create_validation_and_delete_guards(): void
    {
        $this->actingAs($this->superAdmin());

        // titik baru di wilayah Gowa
        $response = $this->from('/super-admin/regions/2')->post('/super-admin/regions/2/sites', [
            'nama_lokasi' => 'Bendungan Cipelangi Test',
            'lat' => -5.2,
            'lng' => 119.5,
            'radius' => 250,
            'address' => 'Jl. Poros',
        ]);
        $response->assertRedirect();
        $this->assertStringContainsString('/super-admin/regions/2/sites/', $response->headers->get('Location'));

        $newSite = Site::where('nama_lokasi', 'Bendungan Cipelangi Test')->first();
        $this->assertNotNull($newSite);
        $this->assertSame(2, $newSite->region_id);

        // duplicate lokasi ditolak
        $this->from('/super-admin/regions/2')->post('/super-admin/regions/2/sites', [
            'nama_lokasi' => 'Bendungan Cipelangi Test',
            'lat' => -5.2,
            'lng' => 119.5,
            'radius' => 250,
            'address' => 'Jl. Poros',
        ])->assertSessionHasErrors('nama_lokasi');

        // titik ber-pegawai otomatis memindah anggotanya lalu dihapus
        $this->from('/super-admin/regions/2/sites/201')->delete('/super-admin/sites/201')->assertSessionHas('success');
        $this->assertDatabaseMissing('sites', ['id' => 201]);
        $this->assertDatabaseHas('employees', ['email' => 'andi@bbws-pj.go.id', 'site_id' => 202]);
        $this->assertDatabaseHas('employees', ['email' => 'rina@bbws-pj.go.id', 'site_id' => 202]);

        // titik kosong bisa dihapus
        $this->from('/super-admin/regions/2/sites/'.$newSite->id)
            ->delete("/super-admin/sites/{$newSite->id}")
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('sites', ['id' => $newSite->id]);
    }

    public function test_site_scope_wilayah_can_only_manage_own_site(): void
    {
        $this->actingAs($this->adminGowa());

        $this->put('/admin/sites/201', [
            'nama_lokasi' => 'Bendungan Bili-Bili',
            'lat' => -5.3114,
            'lng' => 119.42,
            'radius' => 200,
            'address' => 'Jl. Poros Malino',
        ])->assertSessionHas('success');

        $this->put('/admin/sites/301', [
            'nama_lokasi' => 'Kantor Maros',
            'lat' => -5.005,
            'lng' => 119.58,
            'radius' => 200,
            'address' => 'Jl. Poros Maros',
        ])->assertForbidden();
    }

    public function test_admin_wilayah_crud_only_for_super_admin(): void
    {
        $this->actingAs($this->adminGowa());
        $this->get('/admin/admin-wilayah')->assertForbidden();
        $this->post('/admin/admin-wilayah', [
            'nama' => 'X', 'email' => 'x@y.com', 'region' => 'Kab. Gowa', 'password' => 'secret123',
        ])->assertForbidden();

        $this->actingAs($this->superAdmin());

        $this->from('/super-admin/admin-wilayah')->post('/super-admin/admin-wilayah', [
            'nama' => 'Admin Wilayah Maros Baru',
            'email' => 'admin.marosbaru@bbws-pj.go.id',
            'region' => 'Kab. Maros',
            'password' => 'secret123',
        ])->assertRedirect('/super-admin/admin-wilayah');

        $created = User::where('email', 'admin.marosbaru@bbws-pj.go.id')->first();
        $this->assertNotNull($created);
        $this->assertSame(3, $created->region_id);
        $this->assertTrue($created->is_active);

        $this->from('/super-admin/admin-wilayah')->put("/super-admin/admin-wilayah/{$created->id}/toggle", ['is_active' => false])
            ->assertRedirect('/super-admin/admin-wilayah');
        $this->assertFalse($created->fresh()->is_active);

        $this->from('/super-admin/admin-wilayah')->delete("/super-admin/admin-wilayah/{$created->id}")->assertRedirect('/super-admin/admin-wilayah');
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }
}
