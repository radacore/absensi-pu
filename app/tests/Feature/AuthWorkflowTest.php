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

    public function test_employee_can_login_with_nik_and_access_pages(): void
    {
        $this->post('/karyawan/login', [
            'login' => '7371001234567890',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs(Employee::where('nik', '7371001234567890')->first(), 'employee');

        $this->get('/karyawan')->assertOk();
        $this->get('/karyawan/absensi')->assertOk();
    }

    public function test_employee_login_rejected_on_wrong_password(): void
    {
        $this->post('/karyawan/login', [
            'login' => '7371001234567890',
            'password' => 'salah',
        ])->assertSessionHasErrors('login');
    }

    public function test_admin_wilayah_login_and_blocked_from_super_admin(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs(User::where('email', 'admin.gowa@bbws-pj.go.id')->first(), 'web');

        $this->get('/super-admin')->assertRedirect('/admin');
        $this->get('/admin/regions')->assertOk();
    }

    public function test_admin_wilayah_cannot_login_to_super_admin_area(): void
    {
        $this->post('/super-admin/login', [
            'email' => 'admin.gowa@bbws-pj.go.id',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
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
