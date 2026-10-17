<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Satu orang, dua peran.
 *
 * Akun admin wilayah boleh tertaut ke satu baris karyawan. Saat tertaut,
 * kata sandi yang berlaku adalah kata sandi karyawan, dan satu kali login
 * membuka dua sesi: panel admin (guard web) dan aplikasi karyawan (guard
 * employee).
 */
class AkunGandaTest extends TestCase
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

    public function test_pintu_baru_mengarahkan_super_admin_ke_panel_pusat(): void
    {
        $this->post('/login', [
            'email' => 'pusat@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/super-admin');

        $this->assertAuthenticatedAs($this->superAdmin(), 'web');
    }

    public function test_pintu_baru_mengarahkan_karyawan_biasa_ke_aplikasi_karyawan(): void
    {
        $this->post('/login', [
            'email' => 'andi@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs(Employee::where('email', 'andi@bbws-pj.go.id')->first(), 'employee');
        $this->assertGuest('web');
    }

    public function test_admin_tertaut_memakai_sandi_karyawan_bukan_sandi_akun_admin(): void
    {
        $admin = $this->adminGowa();
        $this->assertNotNull($admin->employee_id);

        // Sandi lama di kolom users.password tidak boleh lagi berlaku.
        $admin->forceFill(['password' => 'sandiLama123'])->save();

        $this->from('/login')->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'sandiLama123',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/pilih-peran');
    }

    public function test_admin_tertaut_langsung_mendapat_dua_sesi(): void
    {
        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/pilih-peran');

        $this->assertAuthenticatedAs($this->adminGowa(), 'web');
        $this->assertAuthenticatedAs($this->karyawanGowa(), 'employee');

        $this->get('/admin/regions')->assertOk();
        $this->get('/karyawan')->assertOk();
    }

    public function test_sandi_bawaan_belum_boleh_membuka_panel_admin(): void
    {
        $karyawan = $this->karyawanGowa();
        $karyawan->forceFill([
            'password' => Employee::SANDI_BAWAAN,
            'must_change_password' => true,
        ])->save();

        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => Employee::SANDI_BAWAAN,
        ])->assertRedirect('/karyawan/profil');

        // Sesi admin TIDAK dibuka: sandi bawaan tidak boleh jadi kunci panel.
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($karyawan, 'employee');
    }

    public function test_pintu_lama_tetap_melayani_aplikasi_karyawan_yang_sudah_terpasang(): void
    {
        // PWA lama mengirim `login`, bukan `email`.
        $this->post('/karyawan/login', [
            'login' => 'andi@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs(Employee::where('email', 'andi@bbws-pj.go.id')->first(), 'employee');
    }

    public function test_halaman_login_lama_menampilkan_pintu_yang_sama(): void
    {
        foreach (['/login', '/karyawan/login', '/admin/login', '/super-admin/login', '/wilayah/login'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Auth/Login'));
        }
    }

    public function test_admin_wilayah_bisa_ditautkan_ke_karyawan(): void
    {
        $karyawan = Employee::where('email', 'rina@bbws-pj.go.id')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/admin-wilayah')
            ->post('/super-admin/admin-wilayah', [
                'employee_id' => $karyawan->id,
                'region' => 'Kab. Gowa',
            ])
            ->assertRedirect('/super-admin/admin-wilayah')
            ->assertSessionHasNoErrors();

        $admin = User::where('email', 'rina@bbws-pj.go.id')->firstOrFail();
        $this->assertSame($karyawan->id, $admin->employee_id);
        $this->assertSame($karyawan->name, $admin->name);
        $this->assertSame(2, $admin->region_id);

        // Kolom users.password tidak boleh bisa dipakai masuk.
        $this->assertFalse(Hash::check('password123', $admin->password));
    }

    public function test_satu_karyawan_hanya_boleh_satu_akun_admin(): void
    {
        $karyawan = $this->karyawanGowa();

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/admin-wilayah')
            ->post('/super-admin/admin-wilayah', [
                'employee_id' => $karyawan->id,
                'region' => 'Kab. Gowa',
            ])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_melepas_tautan_wajib_menyertakan_sandi_baru(): void
    {
        $admin = $this->adminGowa();

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/admin-wilayah')
            ->put("/super-admin/admin-wilayah/{$admin->id}", [
                'employee_id' => null,
                'nama' => 'Admin Wilayah Gowa',
                'email' => 'admin.gowa@bbws-pj.go.id',
                'region' => 'Kab. Gowa',
            ])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($admin->fresh()->employee_id);
    }

    public function test_setelah_tautan_dilepas_akun_memakai_sandi_sendiri(): void
    {
        $admin = $this->adminGowa();

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/admin-wilayah')
            ->put("/super-admin/admin-wilayah/{$admin->id}", [
                'employee_id' => null,
                'nama' => 'Admin Wilayah Gowa',
                'email' => 'admin.gowa@bbws-pj.go.id',
                'region' => 'Kab. Gowa',
                'password' => 'sandiBaru123',
            ])
            ->assertRedirect('/super-admin/admin-wilayah')
            ->assertSessionHasNoErrors();

        $this->assertNull($admin->fresh()->employee_id);

        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'sandiBaru123',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_karyawan_yang_tertaut_tidak_bisa_dihapus(): void
    {
        $karyawan = $this->karyawanGowa();

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->delete("/super-admin/employees/{$karyawan->id}")
            ->assertRedirect('/super-admin/employees')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('employees', ['id' => $karyawan->id]);
    }

    public function test_email_akun_admin_tidak_boleh_dipakai_karyawan_baru(): void
    {
        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees', [
                'nik' => '7371009999999999',
                'nip' => '',
                'nama' => 'Karyawan Bentrok',
                'email' => 'pusat@bbws-pj.go.id',
                'gol' => '-',
                'jabatan' => 'Staff',
                'unit' => 'Bidang Test',
                'status' => 'Kontrak',
                'region' => 'Kab. Gowa',
                'office_location_id' => 201,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('employees', ['nik' => '7371009999999999']);
    }

    // ==================================================================
    // Halaman pemilihan peran
    // ==================================================================

    public function test_akun_dua_peran_melihat_dua_kartu_pilihan(): void
    {
        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/pilih-peran');

        $this->get('/pilih-peran')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/PilihPeran')
                ->where('admin.peran', 'Admin Wilayah')
                ->where('admin.wilayah', 'Kab. Gowa')
                ->where('admin.tujuan', '/admin')
                ->where('karyawan.tujuan', '/karyawan'));
    }

    public function test_pilih_peran_menolak_tamu(): void
    {
        $this->get('/pilih-peran')->assertRedirect('/login');
    }

    public function test_pilih_peran_mengalihkan_admin_tanpa_tautan_karyawan(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->get('/pilih-peran')
            ->assertRedirect('/super-admin');
    }

    public function test_pilih_peran_mengalihkan_karyawan_biasa(): void
    {
        $karyawan = Employee::where('email', 'andi@bbws-pj.go.id')->firstOrFail();

        $this->actingAs($karyawan, 'employee')
            ->get('/pilih-peran')
            ->assertRedirect('/karyawan');
    }

    public function test_pilih_peran_menunggu_sandi_bawaan_diganti(): void
    {
        $admin = $this->adminGowa();
        $karyawan = $this->karyawanGowa();
        $karyawan->forceFill(['must_change_password' => true])->save();

        $this->actingAs($admin, 'web')
            ->actingAs($karyawan, 'employee')
            ->get('/pilih-peran')
            ->assertRedirect('/karyawan/profil');
    }

    public function test_membuka_login_lagi_mengembalikan_akun_dua_peran_ke_pilihan(): void
    {
        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ]);

        $this->get('/login')->assertRedirect('/pilih-peran');
    }

    public function test_memilih_panel_admin_tidak_menutup_sesi_karyawan(): void
    {
        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ]);

        $this->get('/admin/regions')->assertOk();
        $this->get('/karyawan')->assertOk();
        $this->assertAuthenticatedAs($this->karyawanGowa(), 'employee');
    }

    public function test_keluar_dari_halaman_pilihan_menutup_dua_sesi(): void
    {
        $this->post('/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ]);

        $this->post('/pilih-peran/keluar')->assertRedirect('/login');

        $this->assertGuest('web');
        $this->assertGuest('employee');
    }
}
