<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Halaman Profil panel admin.
 *
 * Super Admin dan Admin Wilayah harus bisa mengganti kata sandinya sendiri.
 * Untuk akun yang tertaut ke karyawan, sandi yang berlaku tetap satu: baris
 * karyawan. Kolom users.password tidak boleh ikut berubah.
 */
class AdminProfilPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User
    {
        return User::where('email', 'pusat@bbws-pj.go.id')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function karyawanGowa(): Employee
    {
        return Employee::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function adminMandiri(): User
    {
        return User::create([
            'name' => 'Admin Wilayah Uji',
            'email' => 'admin.uji@bbws-pj.go.id',
            'password' => 'password123',
            'role' => 'admin_wilayah',
            'region_id' => 2,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_bisa_membuka_halaman_profil(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->get('/super-admin/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profil')
                ->where('me.peran', 'Super Admin')
                ->where('me.tertaut', false)
                ->where('sandiBawaan', Employee::SANDI_BAWAAN));
    }

    public function test_admin_wilayah_bisa_membuka_halaman_profil(): void
    {
        $admin = $this->adminMandiri();

        $this->actingAs($admin, 'web')
            ->get('/admin/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profil')
                ->where('me.peran', 'Admin Wilayah')
                ->where('me.wilayah', 'Kab. Gowa'));
    }

    public function test_admin_wilayah_tidak_bisa_membuka_profil_super_admin(): void
    {
        $this->actingAs($this->adminMandiri(), 'web')
            ->get('/super-admin/profil')
            ->assertRedirect('/admin');
    }

    public function test_super_admin_bisa_mengganti_sandi_sendiri(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')->put('/super-admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('SandiBaru123', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.change_password',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_wilayah_mandiri_bisa_mengganti_sandi(): void
    {
        $admin = $this->adminMandiri();

        $this->actingAs($admin, 'web')->put('/admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiWilayah123',
            'password_confirmation' => 'SandiWilayah123',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('SandiWilayah123', $admin->fresh()->password));
    }

    public function test_sandi_lama_salah_ditolak(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')->put('/super-admin/profil/password', [
            'current_password' => 'Salah123x',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password123', $admin->fresh()->password));
    }

    public function test_konfirmasi_tidak_cocok_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'web')->put('/super-admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'BedaSekali123',
        ])->assertSessionHasErrors('password');
    }

    public function test_sandi_bawaan_tidak_boleh_dipakai(): void
    {
        $this->actingAs($this->superAdmin(), 'web')->put('/super-admin/profil/password', [
            'current_password' => 'password123',
            'password' => Employee::SANDI_BAWAAN,
            'password_confirmation' => Employee::SANDI_BAWAAN,
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_tertaut_mengubah_sandi_karyawan_dan_tidak_menyentuh_users_password(): void
    {
        $admin = $this->adminGowa();
        $karyawan = $this->karyawanGowa();
        $sandiAcak = $admin->password;

        $this->actingAs($admin, 'web')->put('/admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiGabungan123',
            'password_confirmation' => 'SandiGabungan123',
        ])->assertSessionHas('success');

        // Sandi karyawan berubah — inilah yang dipakai login.
        $this->assertTrue(Hash::check('SandiGabungan123', $karyawan->fresh()->password));

        // users.password tetap sandi acak, tidak ikut berubah.
        $this->assertSame($sandiAcak, $admin->fresh()->password);
        $this->assertFalse(Hash::check('SandiGabungan123', $admin->fresh()->password));
    }

    public function test_sandi_baru_dari_panel_admin_bisa_dipakai_masuk_kedua_peran(): void
    {
        $admin = $this->adminGowa();

        $this->actingAs($admin, 'web')->put('/admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiGabungan123',
            'password_confirmation' => 'SandiGabungan123',
        ])->assertSessionHas('success');

        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'SandiGabungan123',
        ])->assertRedirect('/pilih-peran');

        $this->assertAuthenticatedAs($admin->fresh(), 'web');
        $this->assertAuthenticatedAs($this->karyawanGowa(), 'employee');
    }

    public function test_sandi_lama_tidak_berlaku_lagi_setelah_diganti(): void
    {
        $admin = $this->adminGowa();

        $this->actingAs($admin, 'web')->put('/admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiGabungan123',
            'password_confirmation' => 'SandiGabungan123',
        ])->assertSessionHas('success');

        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_ganti_sandi_dari_panel_admin_mematikan_penanda_wajib_ganti(): void
    {
        $admin = $this->adminGowa();
        $karyawan = $this->karyawanGowa();
        $karyawan->forceFill(['must_change_password' => true])->save();

        $this->actingAs($admin, 'web')->put('/admin/profil/password', [
            'current_password' => 'password123',
            'password' => 'SandiGabungan123',
            'password_confirmation' => 'SandiGabungan123',
        ])->assertSessionHas('success');

        $this->assertFalse($karyawan->fresh()->must_change_password);
    }

    public function test_admin_tertaut_tetap_bisa_mengganti_sandi_dari_aplikasi_karyawan(): void
    {
        $admin = $this->adminGowa();
        $karyawan = $this->karyawanGowa();

        // Sesi dua peran: panel admin dan aplikasi karyawan hidup bersamaan.
        $this->actingAs($admin, 'web');
        $this->actingAs($karyawan, 'employee')
            ->put('/karyawan/profil/password', [
                'current_password' => 'password123',
                'password' => 'SandiKaryawan123',
                'password_confirmation' => 'SandiKaryawan123',
            ])->assertSessionHas('success');

        // Satu sumber kebenaran: sandi karyawan, yang juga dipakai panel admin.
        $this->assertTrue(Hash::check('SandiKaryawan123', $karyawan->fresh()->password));
        $this->assertTrue(Hash::check('SandiKaryawan123', $admin->employee->fresh()->password));
    }

    public function test_halaman_profil_menampilkan_info_karyawan_untuk_akun_tertaut(): void
    {
        $this->actingAs($this->adminGowa(), 'web')
            ->get('/admin/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profil')
                ->where('me.tertaut', true)
                ->where('me.karyawan.nama', 'Admin Wilayah Gowa')
                ->where('me.karyawan.wilayah', 'Kab. Gowa'));
    }
}
