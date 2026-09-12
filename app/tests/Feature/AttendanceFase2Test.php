<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceFase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User { return User::where('role', 'super_admin')->first(); }
    private function adminGowa(): User { return User::where('email', 'admin.gowa@bbws-pj.go.id')->first(); }

    private function loginEmployee(Employee $e): Employee
    {
        // Employee password is bcrypt('password123') from seeder
        return $e;
    }

    public function test_admin_attendances_scoped(): void
    {
        // seed one attendance in Gowa (region 2) and one in Maros (region 3)
        $gowaEmp = Employee::where('region_id', 2)->first();
        $marosEmp = Employee::where('region_id', 3)->first();
        $today = Carbon::now('Asia/Makassar')->toDateString();
        Attendance::create(['employee_id'=>$gowaEmp->id,'work_date'=>$today,'clock_in_at'=>'07:38:00','status'=>'on_time','lat_in'=>$gowaEmp->site->lat,'lng_in'=>$gowaEmp->site->lng,'distance_in_m'=>20,'site_id'=>$gowaEmp->site_id,'region_id'=>$gowaEmp->region_id]);
        Attendance::create(['employee_id'=>$marosEmp->id,'work_date'=>$today,'clock_in_at'=>'07:40:00','status'=>'on_time','lat_in'=>99,'lng_in'=>99,'distance_in_m'=>10,'site_id'=>$marosEmp->site_id,'region_id'=>$marosEmp->region_id]);

        $this->actingAs($this->superAdmin())->get('/super-admin/attendances')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Attendances')->has('attendances', 2));
        $this->actingAs($this->adminGowa())->get('/admin/attendances')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Attendances')->has('attendances', 1)->where('attendances.0.wilayah', 'Kab. Gowa'));
    }

    public function test_wilayah_cannot_delete_attendance_out_of_scope(): void
    {
        $marosEmp = Employee::where('region_id', 3)->first();
        $today = Carbon::now('Asia/Makassar')->toDateString();
        $row = Attendance::create(['employee_id'=>$marosEmp->id,'work_date'=>$today,'clock_in_at'=>'07:40:00','status'=>'on_time','lat_in'=>0,'lng_in'=>0,'distance_in_m'=>10,'site_id'=>$marosEmp->site_id,'region_id'=>$marosEmp->region_id]);
        $this->actingAs($this->adminGowa())->delete("/admin/attendances/{$row->id}")->assertForbidden();
        $this->actingAs($this->superAdmin())->delete("/super-admin/attendances/{$row->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('attendances', ['id'=>$row->id]);
    }

    public function test_settings_readonly_wilayah_and_update_forbidden(): void
    {
        $this->actingAs($this->adminGowa())->get('/admin/settings')->assertOk()->assertInertia(fn (Assert $p) => $p->where('readOnly', true));
        $this->actingAs($this->adminGowa())->put('/admin/settings', ['jamMasuk'=>'08:00','jamPulang'=>'17:00','toleransi'=>10,'loveMax'=>4])->assertForbidden();
        $this->actingAs($this->superAdmin())->put('/super-admin/settings', ['jamMasuk'=>'08:00','jamPulang'=>'17:00','toleransi'=>10,'loveMax'=>4])->assertSessionHas('success');
        $this->assertDatabaseHas('attendance_settings', ['jam_masuk'=>'08:00:00','toleransi_late_menit'=>10]);
    }

    public function test_karyawan_clock_in_radius_and_unique_and_late(): void
    {
        $emp = Employee::where('region_id', 2)->first(); // has site
        $site = $emp->site;

        // outside radius → 422 validation error (distance key)
        $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-in', ['lat'=> $site->lat + 0.02, 'lng'=> $site->lng])->assertSessionHasErrors('distance');

        // inside radius → success
        $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-in', ['lat'=> $site->lat, 'lng'=> $site->lng])->assertSessionHas('success');
        $today = Carbon::now('Asia/Makassar')->toDateString();
        $todayDt = Carbon::parse($today)->startOfDay();
        $this->assertDatabaseHas('attendances', ['employee_id'=>$emp->id,'work_date'=>$todayDt]);

        // duplicate same day → error work_date
        $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-in', ['lat'=> $site->lat, 'lng'=> $site->lng])->assertSessionHasErrors('work_date');

        // clock-out inside radius → success
        $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-out', ['lat'=> $site->lat, 'lng'=> $site->lng])->assertSessionHas('success');
        $this->assertNotNull(Attendance::where('employee_id',$emp->id)->whereDate('work_date',$today)->first()->clock_out_at);

        // second clock-out → error
        $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-out', ['lat'=> $site->lat, 'lng'=> $site->lng])->assertSessionHasErrors('work_date');
    }

    public function test_karyawan_rekap_and_absensi_index_have_assigned(): void
    {
        $emp = Employee::where('region_id', 2)->first();
        $this->actingAs($emp, 'employee')->get('/karyawan/absensi')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Karyawan/Absensi')->has('assigned')->has('settings')->has('history'));
        $this->actingAs($emp, 'employee')->get('/karyawan/rekap')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Karyawan/Rekap')->has('assigned')->has('monthRows'));
    }

    public function test_dashboard_scoped(): void
    {
        $gowaEmp = Employee::where('region_id', 2)->first();
        $today = Carbon::now('Asia/Makassar')->toDateString();
        Attendance::create(['employee_id'=>$gowaEmp->id,'work_date'=>$today,'clock_in_at'=>'07:38:00','status'=>'on_time','lat_in'=>0,'lng_in'=>0,'distance_in_m'=>10,'site_id'=>$gowaEmp->site_id,'region_id'=>$gowaEmp->region_id]);
        $this->actingAs($this->adminGowa())->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')->has('attendances')->has('regions', 1));
        $this->actingAs($this->superAdmin())->get('/super-admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')->has('regions', 24));
    }
}
