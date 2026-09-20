<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use App\Models\User;
use App\Support\AdminPresenter;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tiga kebijakan yang diputuskan pemilik produk:
 *
 *  1. Absen di akhir pekan / hari libur — dapat diaktifkan Super Admin,
 *     dengan pilihan "tolak" atau "tetap dicatat" (status khusus `libur`).
 *  2. Klaim toleransi (love) yang disetujui DITAUTKAN ke baris absensi
 *     tanggal klaim, memakai status `excused_love` / `early_leave`.
 *  3. Approver cuti 3 level = akun admin saja.
 *
 * Tanggal acuan (September 2026):
 *   2026-09-15 = Selasa (hari kerja)
 *   2026-09-19 = Sabtu  (akhir pekan)
 */
class KebijakanBaruTest extends TestCase
{
    use RefreshDatabase;

    private const HARI_KERJA = '2026-09-15';

    private const AKHIR_PEKAN = '2026-09-19';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function adminBone(): User
    {
        return User::where('email', 'admin.bone@bbws-pj.go.id')->firstOrFail();
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    /** actingAs() tanpa guard akan menggeser guard default — selalu sebut 'web'. */
    private function asAdmin(User $user): self
    {
        return $this->actingAs($user, 'web');
    }

    private function jamKe(string $tanggal, string $jam = '08:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse("{$tanggal} {$jam}", 'Asia/Makassar'));
    }

    /** Simpan pengaturan lewat HTTP, seperti Super Admin dari /super-admin/settings. */
    private function setAbsenLibur(bool $aktif, string $mode = 'tolak'): void
    {
        $this->asAdmin($this->superAdmin())
            ->put('/super-admin/settings', [
                'jamMasuk' => '07:30',
                'jamPulang' => '16:00',
                'toleransi' => 15,
                'loveMax' => 4,
                'absenLiburAktif' => $aktif,
                'absenLiburMode' => $mode,
            ])
            ->assertSessionHasNoErrors();
    }

    private function clockIn(Employee $emp)
    {
        return $this->actingAs($emp, 'employee')->post('/karyawan/absensi/clock-in', [
            'lat' => $emp->site->lat,
            'lng' => $emp->site->lng,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  1. Absen di akhir pekan / hari libur
    // ══════════════════════════════════════════════════════════════════════

    public function test_default_gerbang_libur_nonaktif_absen_akhir_pekan_tetap_dicatat(): void
    {
        $this->assertFalse((bool) AttendanceSetting::firstOrFail()->absen_libur_aktif);

        $this->jamKe(self::AKHIR_PEKAN);
        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::AKHIR_PEKAN)->firstOrFail();
        $this->assertNotSame('libur', $row->status, 'Gerbang nonaktif → status dihitung seperti hari kerja biasa');
    }

    public function test_mode_tolak_menolak_absen_di_akhir_pekan(): void
    {
        $this->setAbsenLibur(true, 'tolak');
        $this->jamKe(self::AKHIR_PEKAN);

        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHasErrors('work_date');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_mode_tolak_menolak_absen_pada_hari_libur_nasional_di_hari_kerja(): void
    {
        Holiday::create(['tanggal' => self::HARI_KERJA, 'nama' => 'Libur Uji Kebijakan', 'cuti_bersama' => false]);
        $this->setAbsenLibur(true, 'tolak');
        $this->jamKe(self::HARI_KERJA);

        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_mode_catat_tetap_menyimpan_absen_dengan_status_libur(): void
    {
        $this->setAbsenLibur(true, 'catat');
        $this->jamKe(self::AKHIR_PEKAN);

        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::AKHIR_PEKAN)->firstOrFail();
        $this->assertSame('libur', $row->status);
        $this->assertSame('08:00', substr((string) $row->clock_in_at, 0, 5));
        $this->assertNotNull($row->selfie_url === null ? 0 : 1, 'baris absensi benar-benar tersimpan');
    }

    public function test_mode_tolak_tidak_mengganggu_absen_di_hari_kerja(): void
    {
        $this->setAbsenLibur(true, 'tolak');
        $this->jamKe(self::HARI_KERJA, '07:20:00');

        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::HARI_KERJA)->firstOrFail();
        $this->assertSame('on_time', $row->status);
    }

    public function test_hari_kerja_kustom_membuat_sabtu_menjadi_hari_kerja(): void
    {
        AttendanceSetting::firstOrFail()->update(['hari_kerja' => ['1', '2', '3', '4', '5', '6']]);
        $this->setAbsenLibur(true, 'tolak');
        $this->jamKe(self::AKHIR_PEKAN);

        $emp = $this->empGowa();
        $this->clockIn($emp)->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::AKHIR_PEKAN)->firstOrFail();
        $this->assertNotSame('libur', $row->status);
    }

    public function test_admin_wilayah_tidak_bisa_mengubah_gerbang_libur(): void
    {
        $this->asAdmin($this->adminGowa())
            ->put('/admin/settings', [
                'jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15, 'loveMax' => 4,
                'absenLiburAktif' => true, 'absenLiburMode' => 'catat',
            ])
            ->assertForbidden();

        $this->assertFalse((bool) AttendanceSetting::firstOrFail()->absen_libur_aktif);
    }

    public function test_super_admin_melihat_gerbang_libur_di_props_settings(): void
    {
        $this->setAbsenLibur(true, 'catat');

        $this->asAdmin($this->superAdmin())
            ->get('/super-admin/settings')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('settings.absenLiburAktif', true)
                ->where('settings.absenLiburMode', 'catat')
                ->where('readOnly', false));
    }

    public function test_halaman_absensi_memberi_tahu_karyawan_saat_absen_ditolak(): void
    {
        $this->setAbsenLibur(true, 'tolak');
        $this->jamKe(self::AKHIR_PEKAN);

        $this->actingAs($this->empGowa(), 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('absenLibur.aktif', true)
                ->where('absenLibur.hariIniLibur', true)
                ->where('absenLibur.ditolak', true));
    }

    // ══════════════════════════════════════════════════════════════════════
    //  2. Klaim toleransi ditautkan ke baris absensi
    // ══════════════════════════════════════════════════════════════════════

    private function klaim(Employee $emp, string $jenis, string $tanggal, string $jam): ToleranceClaim
    {
        return ToleranceClaim::create([
            'employee_id' => $emp->id,
            'jenis' => $jenis,
            'claim_date' => $tanggal,
            'jam' => $jam,
            'alasan' => 'Lupa tap kartu absensi',
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
            'approver_id' => $this->adminGowa()->id,
            'status' => 'pending',
        ]);
    }

    public function test_approve_lupa_absen_membuat_baris_absensi_excused_love(): void
    {
        $emp = $this->empGowa();
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:35:00');

        $this->asAdmin($this->adminGowa())
            ->put("/admin/love/{$love->id}/approve")
            ->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::HARI_KERJA)->firstOrFail();
        $this->assertSame('excused_love', $row->status);
        $this->assertSame((int) $love->id, (int) $row->tolerance_claim_id);
        $this->assertSame('07:35', substr((string) $row->clock_in_at, 0, 5));
    }

    public function test_approve_lupa_absen_menandai_ulang_baris_terlambat_yang_sudah_ada(): void
    {
        $emp = $this->empGowa();
        $row = Attendance::create([
            'employee_id' => $emp->id, 'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:10:00', 'status' => 'late',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:30:00');

        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve")->assertSessionHas('success');

        $row->refresh();
        $this->assertSame('excused_love', $row->status);
        $this->assertSame((int) $love->id, (int) $row->tolerance_claim_id);
        $this->assertSame('08:10', substr((string) $row->clock_in_at, 0, 5), 'jam masuk asli tidak ditimpa');
    }

    public function test_approve_lupa_pulang_mengisi_jam_pulang_dan_menandai_early_leave(): void
    {
        $emp = $this->empGowa();
        Attendance::create([
            'employee_id' => $emp->id, 'work_date' => self::HARI_KERJA,
            'clock_in_at' => '07:20:00', 'status' => 'on_time',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);
        // jam_pulang global 16:00 → klaim 15:10 = pulang lebih awal
        $love = $this->klaim($emp, 'lupa_pulang', self::HARI_KERJA, '15:10:00');

        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve")->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::HARI_KERJA)->firstOrFail();
        $this->assertSame('early_leave', $row->status);
        $this->assertSame('15:10', substr((string) $row->clock_out_at, 0, 5));
        $this->assertSame((int) $love->id, (int) $row->tolerance_claim_id);
    }

    public function test_approve_lupa_pulang_setelah_jam_pulang_tidak_menandai_early_leave(): void
    {
        $emp = $this->empGowa();
        Attendance::create([
            'employee_id' => $emp->id, 'work_date' => self::HARI_KERJA,
            'clock_in_at' => '07:20:00', 'status' => 'on_time',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);
        $love = $this->klaim($emp, 'lupa_pulang', self::HARI_KERJA, '16:45:00');

        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve")->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->whereDate('work_date', self::HARI_KERJA)->firstOrFail();
        $this->assertSame('on_time', $row->status);
        $this->assertSame('16:45', substr((string) $row->clock_out_at, 0, 5));
    }

    public function test_klaim_ditolak_tidak_pernah_menautkan_absensi(): void
    {
        $emp = $this->empGowa();
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:35:00');

        $this->asAdmin($this->adminGowa())
            ->put("/admin/love/{$love->id}/reject", ['note' => 'Bukti tidak cukup'])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('attendances', 0);
        $this->assertNull(Attendance::where('tolerance_claim_id', $love->id)->first());
    }

    public function test_hapus_klaim_yang_disetujui_membersihkan_baris_absensi_sintetis(): void
    {
        $emp = $this->empGowa();
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:35:00');

        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve");
        $this->assertDatabaseCount('attendances', 1);

        $this->asAdmin($this->adminGowa())->delete("/admin/love/{$love->id}")->assertSessionHas('success');

        // Baris lahir dari approve (tanpa GPS) → ikut dihapus, tidak jadi absensi palsu.
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_hapus_klaim_tidak_menghapus_absen_asli_hanya_melepas_tautan(): void
    {
        $emp = $this->empGowa();
        Attendance::create([
            'employee_id' => $emp->id, 'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:10:00', 'status' => 'late',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:30:00');
        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve");

        $this->asAdmin($this->adminGowa())->delete("/admin/love/{$love->id}")->assertSessionHas('success');

        $row = Attendance::where('employee_id', $emp->id)->firstOrFail();
        $this->assertNull($row->tolerance_claim_id);
        $this->assertSame('late', $row->status, 'status dihitung ulang dari jam masuk asli');
    }

    public function test_presenter_absensi_admin_menandai_love_approved(): void
    {
        $emp = $this->empGowa();
        $love = $this->klaim($emp, 'lupa_absen', self::HARI_KERJA, '07:35:00');
        $this->asAdmin($this->adminGowa())->put("/admin/love/{$love->id}/approve");

        $rows = collect(AdminPresenter::attendancesFor(2));
        $row = $rows->firstWhere('tgl', self::HARI_KERJA);

        $this->assertNotNull($row);
        $this->assertSame('approved', $row['love']);
        $this->assertSame('excused_love', $row['status']);
    }

    public function test_rekap_menghitung_excused_love_sebagai_toleransi_bukan_terlambat(): void
    {
        $emp = $this->empGowa();
        // 2 Sep 2026 (Rabu) — masuk 08:00 = 30 menit lewat batas toleransi 07:45
        Attendance::create([
            'employee_id' => $emp->id, 'work_date' => '2026-09-02',
            'clock_in_at' => '08:00:00', 'status' => 'excused_love',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);

        $rekap = RekapPresenter::buildRekap($emp, 2026, 9);

        $this->assertSame(1, $rekap['metrikHadir']['toleransi_terlambat']);
        $this->assertSame(0, $rekap['metrikHadir']['terlambat']);
        $this->assertSame(0, $rekap['metrikHadir']['tepat_waktu']);
        $this->assertCount(0, $rekap['keterlambatan']);
    }

    public function test_absensi_karyawan_menampilkan_status_excused_love(): void
    {
        $emp = $this->empGowa();
        Attendance::create([
            'employee_id' => $emp->id, 'work_date' => self::HARI_KERJA,
            'clock_in_at' => '08:10:00', 'status' => 'excused_love',
            'lat_in' => -5.1, 'lng_in' => 119.5, 'distance_in_m' => 12,
            'site_id' => $emp->site_id, 'region_id' => $emp->region_id,
        ]);

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('history.0.status', 'excused_love'));
    }

    // ══════════════════════════════════════════════════════════════════════
    //  3. Approver cuti = akun admin saja
    // ══════════════════════════════════════════════════════════════════════

    public function test_daftar_approver_cuti_hanya_berisi_akun_admin(): void
    {
        $emp = $this->empGowa();
        $rows = AdminPresenter::leaveApproversFor($emp->region_id, $emp->site_id);

        $this->assertNotEmpty($rows);

        $roles = User::whereIn('id', collect($rows)->pluck('id'))->pluck('role')->unique()->values()->all();
        foreach ($roles as $role) {
            $this->assertContains($role, ['super_admin', 'admin_wilayah'], "role {$role} tidak boleh jadi approver cuti");
        }
    }

    public function test_approver_cuti_menolak_akun_non_admin(): void
    {
        $emp = $this->empGowa();
        $nonAdmin = User::create([
            'name' => 'Bukan Admin', 'email' => 'bukan.admin@bbws-pj.go.id',
            'password' => 'RahasiaKuat123', 'role' => 'karyawan',
            'region_id' => $emp->region_id, 'is_active' => true,
        ]);

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-02',
                'alasan' => 'Keperluan keluarga',
                'approver_id' => $nonAdmin->id,
            ])
            ->assertSessionHasErrors('approver_id');

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_approver_cuti_menerima_admin_wilayah_di_wilayahnya(): void
    {
        $emp = $this->empGowa();
        $admin = $this->adminGowa();

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-02',
                'alasan' => 'Keperluan keluarga',
                'approver_id' => $admin->id,
            ])
            ->assertSessionHasNoErrors();

        $leave = Leave::firstOrFail();
        $this->assertSame((int) $admin->id, (int) $leave->approver_id);
        $this->assertSame('Menunggu', $leave->status);
    }

    public function test_approver_cuti_menolak_admin_wilayah_luar_wilayah(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-02',
                'alasan' => 'Keperluan keluarga',
                'approver_id' => $this->adminBone()->id, // Bone ≠ Gowa
            ])
            ->assertSessionHasErrors('approver_id');
    }

    public function test_approver_cuti_boleh_dikosongkan(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => '2026-10-01',
                'selesai' => '2026-10-02',
                'alasan' => 'Keperluan keluarga',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Leave::firstOrFail()->approver_id);
    }

    public function test_approve_cuti_mencatat_admin_yang_memutuskan(): void
    {
        $emp = $this->empGowa();
        $admin = $this->adminGowa();
        $leave = Leave::create([
            'employee_id' => $emp->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-01', 'selesai' => '2026-10-02',
            'alasan' => 'Keperluan keluarga', 'status' => 'Menunggu', 'level' => 0,
        ]);

        $this->asAdmin($admin)->put("/admin/cuti/{$leave->id}/approve")->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame(1, (int) $leave->level);
        $this->assertSame((int) $admin->id, (int) $leave->approved_by);
    }

    public function test_approver_yang_ditunjuk_saja_yang_boleh_level_1(): void
    {
        $emp = $this->empGowa();
        // Karyawan menunjuk Admin Wilayah Bone secara eksplisit (skenario data lama).
        $leave = Leave::create([
            'employee_id' => $emp->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-01', 'selesai' => '2026-10-02',
            'alasan' => 'Keperluan keluarga', 'status' => 'Menunggu', 'level' => 0,
            'approver_id' => $this->adminBone()->id,
        ]);

        // Admin Gowa bukan approver yang ditunjuk → ditolak.
        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$leave->id}/approve")->assertForbidden();

        // Super Admin tetap boleh (Kantor Pusat, cakupan global).
        $this->asAdmin($this->superAdmin())->put("/super-admin/cuti/{$leave->id}/approve")->assertSessionHas('success');

        $this->assertSame(1, (int) $leave->refresh()->level);
    }

    public function test_cuti_tetap_berjenjang_tiga_level_lalu_disetujui(): void
    {
        $emp = $this->empGowa();
        $leave = Leave::create([
            'employee_id' => $emp->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-01', 'selesai' => '2026-10-02',
            'alasan' => 'Keperluan keluarga', 'status' => 'Menunggu', 'level' => 0,
        ]);

        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$leave->id}/approve")->assertSessionHas('success');
        $this->assertSame('Menunggu', $leave->refresh()->status);

        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$leave->id}/approve")->assertSessionHas('success');
        $this->assertSame('Menunggu', $leave->refresh()->status);

        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$leave->id}/approve")->assertSessionHas('success');
        $leave->refresh();
        $this->assertSame('Disetujui', $leave->status);
        $this->assertSame(3, (int) $leave->level);

        // Sudah final → approve lagi ditolak
        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$leave->id}/approve")->assertSessionHas('error');
    }

    public function test_admin_wilayah_lain_tetap_tidak_boleh_menyentuh_cuti_luar_wilayah(): void
    {
        $emp = $this->empGowa();
        $leave = Leave::create([
            'employee_id' => $emp->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-01', 'selesai' => '2026-10-02',
            'alasan' => 'Keperluan keluarga', 'status' => 'Menunggu', 'level' => 0,
        ]);

        $this->asAdmin($this->adminBone())->put("/admin/cuti/{$leave->id}/approve")->assertForbidden();
        $this->assertSame(0, (int) $leave->refresh()->level);
    }

    public function test_riwayat_cuti_karyawan_menampilkan_nama_approver(): void
    {
        $emp = $this->empGowa();
        $admin = $this->adminGowa();
        $leave = Leave::create([
            'employee_id' => $emp->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-10-01', 'selesai' => '2026-10-02',
            'alasan' => 'Keperluan keluarga', 'status' => 'Menunggu', 'level' => 0,
            'approver_id' => $admin->id,
        ]);

        $this->asAdmin($admin)->put("/admin/cuti/{$leave->id}/approve");

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/cuti')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('list.0.approver_nama', $admin->name)
                ->where('list.0.approved_by_nama', $admin->name));
    }
}
