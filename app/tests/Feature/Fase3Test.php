<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Fase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superAdmin(): User { return User::where('role', 'super_admin')->first(); }
    private function adminGowa(): User { return User::where('email', 'admin.gowa@bbws-pj.go.id')->first(); }
    private function adminBone(): User { return User::where('email', 'admin.bone@bbws-pj.go.id')->first(); }
    private function empGowa(): Employee { return Employee::where('region_id', 2)->first(); }
    private function empMaros(): Employee { return Employee::where('region_id', 3)->first(); }

    /** Find a weekday date string Y-m-d in current Asia/Makassar month */
    private function weekdayInThisMonth(): string
    {
        $now = Carbon::now('Asia/Makassar');
        for ($d = 1; $d <= 28; $d++) {
            $c = Carbon::create($now->year, $now->month, $d, 0, 0, 0, 'Asia/Makassar');
            if (! $c->isWeekend()) return $c->toDateString();
        }
        // fallback: today if weekday, else next monday
        $today = Carbon::now('Asia/Makassar');
        return $today->isWeekend() ? $today->next(Carbon::MONDAY)->toDateString() : $today->toDateString();
    }

    private function saturdayInThisMonth(): string
    {
        $now = Carbon::now('Asia/Makassar');
        for ($d = 1; $d <= 28; $d++) {
            $c = Carbon::create($now->year, $now->month, $d, 0, 0, 0, 'Asia/Makassar');
            if ($c->isSaturday()) return $c->toDateString();
        }
        return Carbon::now('Asia/Makassar')->next(Carbon::SATURDAY)->toDateString();
    }

    // ── Cuti berjenjang 3 level ────────────────────────────────
    public function test_cuti_berjenjang_3_level_and_reject_terminal(): void
    {
        $emp = $this->empGowa();
        $cuti = Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Tahunan',
            'mulai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'selesai' => Carbon::now('Asia/Makassar')->addDays(3)->toDateString(),
            'alasan' => 'Keperluan keluarga',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        // level 0 → 1 (masih Menunggu)
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');
        $cuti->refresh();
        $this->assertSame(1, (int) $cuti->level);
        $this->assertSame('Menunggu', $cuti->status);

        // level 1 → 2
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');
        $cuti->refresh();
        $this->assertSame(2, (int) $cuti->level);
        $this->assertSame('Menunggu', $cuti->status);

        // level 2 → 3 final Disetujui
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');
        $cuti->refresh();
        $this->assertSame(3, (int) $cuti->level);
        $this->assertSame('Disetujui', $cuti->status);

        // sudah Disetujui → approve lagi tidak mengubah, hanya back with error
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti->id}/approve")->assertSessionHas('error');

        // reject flow: buat cuti baru lalu tolak
        $cuti2 = Leave::create([
            'employee_id' => $emp->id,
            'jenis' => 'Sakit',
            'mulai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'selesai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'alasan' => 'Sakit demam',
            'status' => 'Menunggu',
            'level' => 1,
        ]);
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti2->id}/reject", ['note' => 'Bukti kurang'])->assertSessionHas('success');
        $cuti2->refresh();
        $this->assertSame('Ditolak', $cuti2->status);
        // Ditolak terminal → approve ditolak
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti2->id}/approve")->assertSessionHas('error');
    }

    public function test_cuti_scoped_wilayah_403(): void
    {
        $marosEmp = $this->empMaros();
        $cuti = Leave::create([
            'employee_id' => $marosEmp->id,
            'jenis' => 'Tahunan',
            'mulai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'selesai' => Carbon::now('Asia/Makassar')->addDays(3)->toDateString(),
            'alasan' => 'Cuti Maros',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        // admin Gowa tidak boleh approve cuti Maros
        $this->actingAs($this->adminGowa())->put("/admin/cuti/{$cuti->id}/approve")->assertForbidden();
        $this->actingAs($this->adminGowa())->put("/admin/cuti/{$cuti->id}/reject", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->adminGowa())->delete("/admin/cuti/{$cuti->id}")->assertForbidden();
        $this->actingAs($this->adminGowa())->get("/admin/cuti/{$cuti->id}")->assertForbidden();

        // super admin boleh
        $this->actingAs($this->superAdmin())->get("/super-admin/cuti/{$cuti->id}")->assertOk();
        $this->actingAs($this->superAdmin())->put("/super-admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');

        // index scoping: Gowa hanya lihat cuti Gowa
        $gowaEmp = $this->empGowa();
        Leave::create(['employee_id'=>$gowaEmp->id,'jenis'=>'Tahunan','mulai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'selesai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'alasan'=>'Gowa cuti','status'=>'Menunggu','level'=>0]);
        $this->actingAs($this->adminGowa())->get('/admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Cuti')->where('list.0.wilayah', 'Kab. Gowa'));
        $this->actingAs($this->superAdmin())->get('/super-admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 2));
    }

    public function test_karyawan_cuti_store_and_destroy_only_own_level0(): void
    {
        $gowa = $this->empGowa();
        $maros = $this->empMaros();

        // karyawan bisa ajukan cuti valid
        $this->actingAs($gowa, 'employee')->post('/karyawan/cuti', [
            'jenis' => 'Tahunan',
            'mulai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'selesai' => Carbon::now('Asia/Makassar')->addDays(3)->toDateString(),
            'alasan' => 'Keperluan keluarga penting',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('leaves', ['employee_id'=>$gowa->id,'jenis'=>'Tahunan']);

        // selesai < mulai → validation error
        $this->actingAs($gowa, 'employee')->post('/karyawan/cuti', [
            'jenis' => 'Tahunan',
            'mulai' => Carbon::now('Asia/Makassar')->addDays(5)->toDateString(),
            'selesai' => Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),
            'alasan' => 'Salah tanggal',
        ])->assertSessionHasErrors('selesai');

        // hanya Menunggu level 0 bisa dibatalkan oleh pemilik
        $cuti = Leave::where('employee_id', $gowa->id)->first();
        $this->actingAs($gowa, 'employee')->delete("/karyawan/cuti/{$cuti->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('leaves', ['id'=>$cuti->id]);

        // buat cuti level 1 → tidak boleh dibatalkan karyawan
        $cuti2 = Leave::create(['employee_id'=>$gowa->id,'jenis'=>'Sakit','mulai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'selesai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'alasan'=>'Sakit','status'=>'Menunggu','level'=>1]);
        $this->actingAs($gowa, 'employee')->delete("/karyawan/cuti/{$cuti2->id}")->assertForbidden();

        // karyawan lain tidak boleh hapus cuti orang lain
        $cuti3 = Leave::create(['employee_id'=>$maros->id,'jenis'=>'Tahunan','mulai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'selesai'=>Carbon::now('Asia/Makassar')->addDays(2)->toDateString(),'alasan'=>'Maros','status'=>'Menunggu','level'=>0]);
        $this->actingAs($gowa, 'employee')->delete("/karyawan/cuti/{$cuti3->id}")->assertForbidden();
    }

    // ── LOVE: quota, weekend, same-month, approver ─────────────
    public function test_love_quota_weekend_same_month_approver_guards(): void
    {
        $emp = $this->empGowa();
        $approverId = $this->adminGowa()->id;
        $weekday = $this->weekdayInThisMonth();
        $saturday = $this->saturdayInThisMonth();
        $lastMonth = Carbon::now('Asia/Makassar')->subMonth()->format('Y-m-d');
        // pastikan lastMonth bukan weekend agar error same-month bukan weekend
        $lmCarbon = Carbon::parse($lastMonth);
        if ($lmCarbon->isWeekend()) {
            $lastMonth = $lmCarbon->next(Carbon::MONDAY)->subMonth()->format('Y-m-d');
            // fallback: simply use first weekday of last month
            $lastMonth = Carbon::now('Asia/Makassar')->subMonth()->startOfMonth()->next(Carbon::MONDAY)->toDateString();
        }

        // valid klaim weekday bulan ini → success
        $this->actingAs($emp, 'employee')->post('/karyawan/love', [
            'jenis' => 'lupa_absen',
            'tgl' => $weekday,
            'jam' => '07:35',
            'alasan' => 'Lupa absen pagi karena macet',
            'approver_id' => $approverId,
        ])->assertSessionHas('success');

        // weekend → 422 tgl
        $this->actingAs($emp, 'employee')->post('/karyawan/love', [
            'jenis' => 'lupa_absen',
            'tgl' => $saturday,
            'jam' => '07:35',
            'alasan' => 'Weekend test',
            'approver_id' => $approverId,
        ])->assertSessionHasErrors('tgl');

        // bulan berbeda → error tgl
        $this->actingAs($emp, 'employee')->post('/karyawan/love', [
            'jenis' => 'lupa_absen',
            'tgl' => $lastMonth,
            'jam' => '07:35',
            'alasan' => 'Bulan lalu test',
            'approver_id' => $approverId,
        ])->assertSessionHasErrors('tgl');

        // approver tidak valid (adminBone region 4 bukan Gowa)
        $this->actingAs($emp, 'employee')->post('/karyawan/love', [
            'jenis' => 'lupa_absen',
            'tgl' => $weekday,
            'jam' => '07:35',
            'alasan' => 'Approver salah',
            'approver_id' => $this->adminBone()->id,
        ])->assertSessionHasErrors('approver_id');

        // quota: isi sampai love_max 4 (sudah 1 pending) → tambah 3 lagi = 4
        for ($i = 0; $i < 3; $i++) {
            ToleranceClaim::create([
                'employee_id' => $emp->id,
                'jenis' => 'lupa_absen',
                'claim_date' => $weekday,
                'jam' => '07:35:00',
                'alasan' => "Dummy {$i}",
                'site_id' => $emp->site_id,
                'region_id' => $emp->region_id,
                'approver_id' => $approverId,
                'status' => 'pending',
            ]);
        }
        // sekarang quota sisa 0 → klaim baru ditolak dengan error love
        $this->actingAs($emp, 'employee')->post('/karyawan/love', [
            'jenis' => 'lupa_absen',
            'tgl' => $weekday,
            'jam' => '07:35',
            'alasan' => 'Quota habis test',
            'approver_id' => $approverId,
        ])->assertSessionHasErrors('love');

        // index menampilkan quota sisa 0
        $this->actingAs($emp, 'employee')->get('/karyawan/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Karyawan/Love')->where('settings.loveQuota.sisa', 0));
    }

    public function test_love_admin_scoped_and_reject_needs_note(): void
    {
        $gowaEmp = $this->empGowa();
        $marosEmp = $this->empMaros();
        $approverId = $this->adminGowa()->id;
        $weekday = $this->weekdayInThisMonth();

        $claimGowa = ToleranceClaim::create([
            'employee_id'=>$gowaEmp->id,'jenis'=>'lupa_absen','claim_date'=>$weekday,'jam'=>'07:35:00','alasan'=>'Gowa','site_id'=>$gowaEmp->site_id,'region_id'=>$gowaEmp->region_id,'approver_id'=>$approverId,'status'=>'pending',
        ]);
        $claimMaros = ToleranceClaim::create([
            'employee_id'=>$marosEmp->id,'jenis'=>'lupa_absen','claim_date'=>$weekday,'jam'=>'07:35:00','alasan'=>'Maros','site_id'=>$marosEmp->site_id,'region_id'=>$marosEmp->region_id,'approver_id'=>$approverId,'status'=>'pending',
        ]);

        // wilayah Gowa hanya lihat claim Gowa
        $this->actingAs($this->adminGowa())->get('/admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Love')->has('claims', 1)->where('claims.0.wilayah', 'Kab. Gowa'));
        $this->actingAs($this->superAdmin())->get('/super-admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('claims', 2));

        // wilayah tidak boleh approve claim luar wilayah
        $this->actingAs($this->adminGowa())->put("/admin/love/{$claimMaros->id}/approve")->assertForbidden();
        $this->actingAs($this->adminGowa())->put("/admin/love/{$claimMaros->id}/reject", ['note'=>'ab'])->assertForbidden();

        // super boleh approve
        $this->actingAs($this->superAdmin())->put("/super-admin/love/{$claimMaros->id}/approve")->assertSessionHas('success');
        $this->assertDatabaseHas('tolerance_claims', ['id'=>$claimMaros->id,'status'=>'approved']);

        // reject butuh note min 3
        $this->actingAs($this->superAdmin())->put("/super-admin/love/{$claimGowa->id}/reject", ['note'=>'ab'])->assertSessionHasErrors('note');
        $this->actingAs($this->superAdmin())->put("/super-admin/love/{$claimGowa->id}/reject", ['note'=>'Bukti tidak lengkap'])->assertSessionHas('success');
        $this->assertDatabaseHas('tolerance_claims', ['id'=>$claimGowa->id,'status'=>'rejected']);
    }

    // ── Pengumuman: Global vs Wilayah, pin, scoping ────────────
    public function test_pengumuman_global_vs_wilayah_scoping_and_pin(): void
    {
        $super = $this->superAdmin();
        $gowa = $this->adminGowa();

        $global = Announcement::create(['judul'=>'Global Info','konten'=>'Untuk semua','scope'=>'Global','region_id'=>null,'pin'=>true,'created_by'=>$super->id]);
        $gowaAnn = Announcement::create(['judul'=>'Gowa Info','konten'=>'Khusus Gowa','scope'=>'Wilayah','region_id'=>2,'pin'=>false,'created_by'=>$super->id]);
        $marosAnn = Announcement::create(['judul'=>'Maros Info','konten'=>'Khusus Maros','scope'=>'Wilayah','region_id'=>3,'pin'=>false,'created_by'=>$super->id]);

        // super lihat semua 3
        $this->actingAs($super)->get('/super-admin/pengumuman')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Pengumuman')->has('list', 3));

        // wilayah Gowa hanya Global + Gowa (pin Global di atas)
        $this->actingAs($gowa)->get('/admin/pengumuman')->assertOk()
            ->assertInertia(function (Assert $p) {
                $p->component('Admin/Pengumuman')->has('list', 2);
                // pin true should be first
                $p->where('list.0.pin', true)->where('list.0.judul', 'Global Info');
            });

        // karyawan Gowa hanya Global + Gowa
        $empGowa = $this->empGowa();
        $this->actingAs($empGowa, 'employee')->get('/karyawan/pengumuman')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Karyawan/Pengumuman')->has('list', 2)->where('unreadCount', 2));

        // karyawan Maros hanya Global + Maros
        $empMaros = $this->empMaros();
        $this->actingAs($empMaros, 'employee')->get('/karyawan/pengumuman')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 2));
    }

    public function test_pengumuman_wilayah_cannot_create_or_edit_global(): void
    {
        $gowa = $this->adminGowa();
        $super = $this->superAdmin();

        // wilayah tidak boleh buat Global
        $this->actingAs($gowa)->post('/admin/pengumuman', [
            'judul'=>'Coba Global','konten'=>'x','scope'=>'Global','pin'=>false,
        ])->assertSessionHasErrors('scope');

        // wilayah buat Wilayah → region_id dipaksa own region meski kirim lain
        $this->actingAs($gowa)->post('/admin/pengumuman', [
            'judul'=>'Gowa Baru','konten'=>'isi','scope'=>'Wilayah','region_id'=>3,'pin'=>false,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('announcements', ['judul'=>'Gowa Baru','region_id'=>2,'scope'=>'Wilayah']);

        // wilayah tidak boleh edit Global
        $global = Announcement::create(['judul'=>'Global','konten'=>'g','scope'=>'Global','region_id'=>null,'pin'=>false,'created_by'=>$super->id]);
        $this->actingAs($gowa)->put("/admin/pengumuman/{$global->id}", [
            'judul'=>'Hacked','konten'=>'x','scope'=>'Global','pin'=>false,
        ])->assertForbidden();
        $this->actingAs($gowa)->delete("/admin/pengumuman/{$global->id}")->assertForbidden();

        // wilayah tidak boleh edit pengumuman wilayah lain
        $marosAnn = Announcement::create(['judul'=>'Maros','konten'=>'m','scope'=>'Wilayah','region_id'=>3,'pin'=>false,'created_by'=>$super->id]);
        $this->actingAs($gowa)->put("/admin/pengumuman/{$marosAnn->id}", [
            'judul'=>'Hacked','konten'=>'x','scope'=>'Wilayah','region_id'=>3,'pin'=>false,
        ])->assertForbidden();

        // super boleh edit Global
        $this->actingAs($super)->put("/super-admin/pengumuman/{$global->id}", [
            'judul'=>'Global Updated','konten'=>'g updated','scope'=>'Global','pin'=>true,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('announcements', ['id'=>$global->id,'judul'=>'Global Updated','pin'=>true]);
    }

    public function test_pengumuman_karyawan_mark_read(): void
    {
        $super = $this->superAdmin();
        $emp = $this->empGowa();
        $ann = Announcement::create(['judul'=>'Info','konten'=>'isi','scope'=>'Global','region_id'=>null,'pin'=>false,'created_by'=>$super->id]);

        $this->actingAs($emp, 'employee')->post("/karyawan/pengumuman/{$ann->id}/read")->assertSessionHas('success');
        $this->assertDatabaseHas('announcement_reads', ['announcement_id'=>$ann->id,'employee_id'=>$emp->id]);

        // idempotent
        $this->actingAs($emp, 'employee')->post("/karyawan/pengumuman/{$ann->id}/read")->assertSessionHas('success');
        $this->assertSame(1, AnnouncementRead::where('announcement_id',$ann->id)->where('employee_id',$emp->id)->count());

        // markAllRead
        $ann2 = Announcement::create(['judul'=>'Gowa','konten'=>'gowa','scope'=>'Wilayah','region_id'=>2,'pin'=>false,'created_by'=>$super->id]);
        $this->actingAs($emp, 'employee')->post('/karyawan/pengumuman/read-all')->assertSessionHas('success');
        $this->assertDatabaseHas('announcement_reads', ['announcement_id'=>$ann2->id,'employee_id'=>$emp->id]);

        $this->actingAs($emp, 'employee')->get('/karyawan/pengumuman')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unreadCount', 0)->where('readIds', [$ann->id, $ann2->id]));
    }

    public function test_karyawan_profil_update_and_password(): void
    {
        $emp = $this->empGowa();
        // update phone+email
        $this->actingAs($emp, 'employee')->put('/karyawan/profil', [
            'phone' => '08123456789',
            'email' => 'andi.new@bbws-pj.go.id',
        ])->assertSessionHas('success');
        $emp->refresh();
        $this->assertSame('08123456789', $emp->phone);
        $this->assertSame('andi.new@bbws-pj.go.id', $emp->email);

        // hapus foto
        $emp->update(['foto_url' => 'https://example.com/foto.jpg']);
        $this->actingAs($emp, 'employee')->delete('/karyawan/profil/foto')->assertSessionHas('success');
        $this->assertNull($emp->fresh()->foto_url);

        // ganti password: salah current → 422
        $this->actingAs($emp, 'employee')->put('/karyawan/profil/password', [
            'current_password' => 'salah123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('current_password');

        // konfirmasi tidak cocok
        $this->actingAs($emp, 'employee')->put('/karyawan/profil/password', [
            'current_password' => 'password123',
            'password' => 'newpass123',
            'password_confirmation' => 'beda123',
        ])->assertSessionHasErrors('password');

        // sukses
        $this->actingAs($emp, 'employee')->put('/karyawan/profil/password', [
            'current_password' => 'password123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHas('success');

        // login dengan password baru via guard employee (hash check)
        $emp->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass123', $emp->password));
    }
}
