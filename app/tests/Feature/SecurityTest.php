<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
    }

    public function test_karyawan_baru_dari_admin_password_default_sama_dengan_nik_dan_flag_must_change_true(): void
    {
        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees', [
                'nik' => '7371009999999998',
                'nip' => '',
                'nama' => 'Karyawan Baru Test',
                'email' => 'baru.karyawan@bbws-pj.go.id',
                'gol' => '-',
                'jabatan' => 'Staff',
                'unit' => 'Bidang Test',
                'status' => 'Kontrak',
                'region' => 'Kab. Gowa',
                'office_location_id' => 201,
            ])
            ->assertRedirect('/super-admin/employees');

        $emp = Employee::where('nik', '7371009999999998')->firstOrFail();
        $this->assertTrue($emp->must_change_password);
        $this->assertTrue(Hash::check($emp->nik, $emp->password), 'Password default harus sama dengan NIK');
    }

    public function test_endpoint_login_admin_dan_karyawan_dilindungi_throttle_middleware(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $loginRoutes = [
            'super-admin/login',
            'admin/login',
            'wilayah/login',
            'karyawan/login',
        ];

        foreach ($loginRoutes as $path) {
            $route = collect($routes)->first(fn ($r) => $r->uri() === $path && in_array('POST', $r->methods(), true));
            $this->assertNotNull($route, "Route POST {$path} tidak ditemukan");
            $middleware = $route->gatherMiddleware();
            $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains($m, 'throttle'));
            $this->assertTrue($hasThrottle, "POST {$path} harus punya middleware throttle");
        }
    }

    public function test_audit_log_dibuat_saat_reset_password_karyawan(): void
    {
        $emp = Employee::first();
        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post("/super-admin/employees/{$emp->id}/reset-password");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.reset_password',
            'subject_type' => 'Employee',
            'subject_id' => $emp->id,
        ]);

        $log = AuditLog::where('action', 'employee.reset_password')->latest()->first();
        $this->assertSame($this->superAdmin()->name, $log->actor_name);
        $this->assertSame('user', $log->actor_type);
        $this->assertNotNull($log->ip);
    }

    public function test_audit_log_dibuat_saat_approve_cuti_dan_delete_karyawan(): void
    {
        $emp = Employee::first();
        $cuti = \App\Models\Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(2)->toDateString(),
            'selesai' => now()->addDays(3)->toDateString(),
            'alasan' => 'Uji audit log',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $this->actingAs($this->superAdmin());
        $this->put("/super-admin/cuti/{$cuti->id}/approve");
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cuti.approve_level',
            'subject_type' => 'Leave',
            'subject_id' => $cuti->id,
        ]);

        $empId = $emp->id;
        $this->delete("/super-admin/employees/{$empId}");
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.delete',
        ]);
    }

    public function test_audit_log_viewer_hanya_super_admin(): void
    {
        AuditLog::create(['action' => 'test.dummy', 'description' => 'Uji']);

        $adminWilayah = User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
        $this->actingAs($adminWilayah)->get('/admin/audit-log')->assertForbidden();

        $this->actingAs($this->superAdmin())->get('/super-admin/audit-log')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/AuditLog')->has('logs'));
    }
}
