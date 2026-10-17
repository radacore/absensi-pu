<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_redirected_to_login_pages(): void
    {
        $this->get('/super-admin')->assertRedirect('/super-admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/karyawan')->assertRedirect('/karyawan/login');
    }

    public function test_employee_can_login_with_email_and_access_pages(): void
    {
        $this->post('/karyawan/login', [
            'login' => 'andi@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs(Employee::where('email', 'andi@bbws-pj.go.id')->first(), 'employee');

        $this->get('/karyawan')->assertOk();
        $this->get('/karyawan/absensi')->assertOk();
    }

    public function test_employee_login_rejected_on_wrong_password(): void
    {
        $this->post('/karyawan/login', [
            'login' => 'andi@bbws-pj.go.id',
            'password' => 'salah',
        ])->assertSessionHasErrors('login');
    }

    public function test_admin_wilayah_login_and_blocked_from_super_admin(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/pilih-peran');

        $this->assertAuthenticatedAs(User::where('email', 'admin.gowa@bbws-pj.go.id')->first(), 'web');

        $this->get('/super-admin')->assertRedirect('/admin');
        $this->get('/admin/regions')->assertOk();
    }

    public function test_pintu_super_admin_tetap_mengarahkan_admin_wilayah_ke_panel_wilayah(): void
    {
        // Satu pintu masuk melayani semua peran. Admin wilayah yang mengetik
        // alamat /super-admin/login tidak diberi akses pusat; ia diarahkan ke
        // panel wilayahnya sendiri.
        $this->post('/super-admin/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/pilih-peran');

        $this->get('/super-admin')->assertRedirect('/admin');
    }

    public function test_super_admin_login_and_reaches_pusat_area(): void
    {
        $this->post('/super-admin/login', [
            'email' => 'pusat@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/super-admin');

        $this->get('/super-admin')->assertOk();
        $this->get('/admin')->assertRedirect('/super-admin');
    }

    public function test_admin_guard_cannot_access_karyawan_pages(): void
    {
        $admin = User::where('email', 'pusat@bbws-pj.go.id')->first();

        $this->actingAs($admin)->get('/karyawan')->assertRedirect('/karyawan/login');
    }

    public function test_employee_guard_cannot_access_admin_pages(): void
    {
        $employee = Employee::where('nik', '7371001234567890')->first();

        $this->actingAs($employee, 'employee')->get('/admin')->assertRedirect('/admin/login');
    }
}
