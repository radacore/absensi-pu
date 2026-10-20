<?php

namespace Tests\Feature;

use App\Models\EarlyLeaveRequest;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\OvertimeClaim;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengajuan yang ditujukan ke approver tertentu hanya boleh terlihat dan
 * diproses oleh approver itu. Admin wilayah lain di wilayah yang sama tidak
 * ikut melihat antreannya. Pengajuan tanpa approver (akun approver sudah
 * dihapus) tetap terbuka untuk admin wilayah mana pun di wilayah tersebut.
 */
class ApproverScopeTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-10-05'; // Senin

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminA = User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
        $this->adminB = User::create([
            'name' => 'Admin Wilayah Gowa Kedua',
            'email' => 'admin.gowa2@uji.local',
            'password' => 'RahasiaUji123',
            'role' => 'admin_wilayah',
            'region_id' => $this->adminA->region_id,
            'site_id' => null,
            'is_active' => true,
        ]);
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', $this->adminA->region_id)->firstOrFail();
    }

    private function klaimToleransi(User $approver): ToleranceClaim
    {
        $emp = $this->empGowa();

        return ToleranceClaim::create([
            'employee_id' => $emp->id,
            'jenis' => 'lupa_absen',
            'claim_date' => self::TANGGAL,
            'jam' => '07:35:00',
            'alasan' => 'Lupa tap kartu',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
            'approver_id' => $approver->id,
            'status' => 'pending',
        ]);
    }

    private function pulangCepat(?User $approver): EarlyLeaveRequest
    {
        $emp = $this->empGowa();

        return EarlyLeaveRequest::create([
            'employee_id' => $emp->id,
            'tanggal' => self::TANGGAL,
            'jam_rencana' => '12:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'approver_id' => $approver?->id,
            'status' => 'Menunggu',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);
    }

    private function lembur(?User $approver): OvertimeClaim
    {
        $emp = $this->empGowa();

        return OvertimeClaim::create([
            'employee_id' => $emp->id,
            'region_id' => $emp->region_id,
            'site_id' => $emp->site_id,
            'tanggal' => self::TANGGAL,
            'jam_mulai' => '18:00:00',
            'jam_selesai' => '20:00:00',
            'menit' => 120,
            'keterangan' => 'Menutup laporan bulanan',
            'approver_id' => $approver?->id,
            'status' => 'Menunggu',
        ]);
    }

    private function cuti(?User $approver, int $level = 0): Leave
    {
        return Leave::create([
            'employee_id' => $this->empGowa()->id,
            'jenis' => 'Tahunan',
            'mulai' => '2026-11-02',
            'selesai' => '2026-11-03',
            'alasan' => 'Keperluan keluarga',
            'status' => 'Menunggu',
            'level' => $level,
            'approver_id' => $approver?->id,
        ]);
    }

    // ── Klaim toleransi ────────────────────────────────────────────────────

    public function test_klaim_toleransi_hanya_terlihat_dan_bisa_diproses_approver_yang_dituju(): void
    {
        $claim = $this->klaimToleransi($this->adminA);

        $this->actingAs($this->adminA)->get('/admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Love')
                ->has('claims', 1)
                ->where('claims.0.id', $claim->id));

        $this->actingAs($this->adminB)->get('/admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Love')->has('claims', 0));

        // Admin wilayah lain tidak boleh menyetujui, menolak, atau menghapus.
        $this->actingAs($this->adminB)->put("/admin/love/{$claim->id}/approve")->assertForbidden();
        $this->actingAs($this->adminB)->put("/admin/love/{$claim->id}/reject", ['note' => 'Bukan tugas saya'])->assertForbidden();
        $this->actingAs($this->adminB)->delete("/admin/love/{$claim->id}")->assertForbidden();

        $this->assertSame('pending', $claim->refresh()->status);

        // Approver yang ditunjuk tetap bisa memproses.
        $this->actingAs($this->adminA)->put("/admin/love/{$claim->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $claim->refresh()->status);
    }

    public function test_klaim_toleransi_tanpa_approver_tetap_terbuka_untuk_admin_wilayah(): void
    {
        $emp = $this->empGowa();
        $claim = ToleranceClaim::create([
            'employee_id' => $emp->id,
            'jenis' => 'lupa_absen',
            'claim_date' => self::TANGGAL,
            'jam' => '07:35:00',
            'alasan' => 'Approver lama sudah dihapus',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
            'approver_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($this->adminB)->get('/admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('claims', 1)->where('claims.0.id', $claim->id));

        $this->actingAs($this->adminB)->put("/admin/love/{$claim->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $claim->refresh()->status);
    }

    // ── Pulang cepat ───────────────────────────────────────────────────────

    public function test_pulang_cepat_hanya_terlihat_dan_bisa_diproses_approver_yang_dituju(): void
    {
        $req = $this->pulangCepat($this->adminA);

        $this->actingAs($this->adminA)->get('/admin/pulang-cepat')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/PulangAwal')
                ->has('requests', 1)
                ->where('requests.0.id', $req->id));

        $this->actingAs($this->adminB)->get('/admin/pulang-cepat')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/PulangAwal')->has('requests', 0));

        $this->actingAs($this->adminB)->put("/admin/pulang-cepat/{$req->id}/approve")->assertForbidden();
        $this->actingAs($this->adminB)->put("/admin/pulang-cepat/{$req->id}/reject", ['note' => 'Bukan tugas saya'])->assertForbidden();

        $this->actingAs($this->adminA)->put("/admin/pulang-cepat/{$req->id}/approve")->assertSessionHas('success');
        $this->assertSame('Disetujui', $req->refresh()->status);
    }

    public function test_pulang_cepat_tanpa_approver_tetap_terbuka_untuk_admin_wilayah(): void
    {
        $req = $this->pulangCepat(null);

        $this->actingAs($this->adminB)->get('/admin/pulang-cepat')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('requests', 1));

        $this->actingAs($this->adminB)->put("/admin/pulang-cepat/{$req->id}/approve")->assertSessionHas('success');
        $this->assertSame('Disetujui', $req->refresh()->status);
    }

    // ── Lembur ─────────────────────────────────────────────────────────────

    public function test_lembur_hanya_terlihat_dan_bisa_diproses_approver_yang_dituju(): void
    {
        $claim = $this->lembur($this->adminA);

        $this->actingAs($this->adminA)->get('/admin/lembur')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Lembur')
                ->has('requests', 1)
                ->where('requests.0.id', $claim->id));

        $this->actingAs($this->adminB)->get('/admin/lembur')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Lembur')->has('requests', 0));

        $this->actingAs($this->adminB)->put("/admin/lembur/{$claim->id}/approve")->assertForbidden();
        $this->actingAs($this->adminB)->put("/admin/lembur/{$claim->id}/reject", ['note' => 'Bukan tugas saya'])->assertForbidden();

        $this->actingAs($this->adminA)->put("/admin/lembur/{$claim->id}/approve")->assertSessionHas('success');
        $this->assertSame('Disetujui', $claim->refresh()->status);
    }

    public function test_lembur_tanpa_approver_tetap_terbuka_untuk_admin_wilayah(): void
    {
        $claim = $this->lembur(null);

        $this->actingAs($this->adminB)->get('/admin/lembur')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('requests', 1));

        $this->actingAs($this->adminB)->put("/admin/lembur/{$claim->id}/approve")->assertSessionHas('success');
        $this->assertSame('Disetujui', $claim->refresh()->status);
    }

    // ── Cuti ───────────────────────────────────────────────────────────────

    public function test_cuti_level_0_hanya_untuk_approver_lalu_level_berikutnya_terbuka(): void
    {
        $cuti = $this->cuti($this->adminA);

        $this->actingAs($this->adminA)->get('/admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Cuti')
                ->has('list', 1)
                ->where('list.0.id', $cuti->id));

        $this->actingAs($this->adminB)->get('/admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Cuti')->has('list', 0));

        // Level 0 milik approver lain: detail, approve, reject, hapus ditolak.
        $this->actingAs($this->adminB)->get("/admin/cuti/{$cuti->id}")->assertForbidden();
        $this->actingAs($this->adminB)->put("/admin/cuti/{$cuti->id}/approve")->assertForbidden();
        $this->actingAs($this->adminB)->put("/admin/cuti/{$cuti->id}/reject", ['note' => 'Bukan tugas saya'])->assertForbidden();
        $this->actingAs($this->adminB)->delete("/admin/cuti/{$cuti->id}")->assertForbidden();

        // Approver yang ditunjuk memproses level 0 → 1.
        $this->actingAs($this->adminA)->put("/admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');
        $this->assertSame(1, (int) $cuti->refresh()->level);

        // Level 1 sudah terbuka untuk admin wilayah lain di wilayah yang sama.
        $this->actingAs($this->adminB)->get('/admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 1));

        $this->actingAs($this->adminB)->put("/admin/cuti/{$cuti->id}/approve")->assertSessionHas('success');
        $this->assertSame(2, (int) $cuti->refresh()->level);
    }

    public function test_cuti_level_0_yang_ditujukan_ke_super_admin_tidak_muncul_di_daftar_wilayah(): void
    {
        $cuti = $this->cuti($this->superAdmin());

        $this->actingAs($this->adminA)->get('/admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 0));

        $this->actingAs($this->superAdmin())->get('/super-admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 1)->where('list.0.id', $cuti->id));
    }

    // ── Dashboard & super admin ────────────────────────────────────────────

    public function test_dashboard_admin_wilayah_menghitung_pengajuan_yang_ditujukan_kepadanya(): void
    {
        $this->klaimToleransi($this->adminA);
        $this->klaimToleransi($this->adminB);
        $this->cuti($this->adminA);
        $this->cuti($this->adminB);

        $this->actingAs($this->adminA)->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')
                ->has('love', 1)
                ->has('cuti', 1));

        $this->actingAs($this->adminB)->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')
                ->has('love', 1)
                ->has('cuti', 1));
    }

    public function test_super_admin_tetap_melihat_dan_memproses_semua_pengajuan(): void
    {
        $milikA = $this->klaimToleransi($this->adminA);
        $this->klaimToleransi($this->adminB);
        $this->cuti($this->adminA);
        $this->cuti($this->adminB);

        $this->actingAs($this->superAdmin())->get('/super-admin/love')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('claims', 2));

        $this->actingAs($this->superAdmin())->get('/super-admin/cuti')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('list', 2));

        $this->actingAs($this->superAdmin())->put("/super-admin/love/{$milikA->id}/approve")
            ->assertSessionHas('success');
        $this->assertSame('approved', $milikA->refresh()->status);
    }
}
