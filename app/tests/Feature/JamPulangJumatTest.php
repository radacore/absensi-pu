<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ToleranceClaim;
use App\Models\User;
use App\Support\RekapPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Jam pulang khusus Jumat untuk karyawan non-shift.
 *
 * Klien meminta Jumat pulang 30 menit lebih awal (mis. 15.30, bukan 16.00)
 * karena sholat Jumat. Jam masuk tidak berubah dan pekerja shift tetap
 * memakai jadwal shift-nya.
 *
 * Satu sumber kebenaran: AttendanceSetting::jamPulangPada(). Uji di sini
 * menjaga semua pemakainya tetap sepakat — rekap PSW, status `early_leave`
 * pada klaim toleransi, tampilan jadwal, dan halaman absensi.
 *
 * Tanggal acuan (Oktober 2026):
 *   2026-10-15 = Kamis (hari kerja)
 *   2026-10-16 = Jumat (hari kerja)
 */
class JamPulangJumatTest extends TestCase
{
    use RefreshDatabase;

    private const KAMIS = '2026-10-15';

    private const JUMAT = '2026-10-16';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));

        Holiday::query()->delete();

        // Setelan acuan: masuk 08.30, pulang 16.00, Jumat 15.30.
        $this->setelan([
            'jam_masuk' => '08:30:00',
            'jam_pulang' => '16:00:00',
            'jam_pulang_jumat' => '15:30:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Pembantu ────────────────────────────────────────────────────────────

    private function setelan(array $attrs): AttendanceSetting
    {
        $s = AttendanceSetting::first() ?? new AttendanceSetting;
        $s->fill($attrs)->save();

        return $s->refresh();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    /** Karyawan non-shift wilayah Gowa yang punya titik absensi. */
    private function karyawan(): Employee
    {
        $e = Employee::where('region_id', 2)->whereNotNull('site_id')->firstOrFail();
        $e->forceFill(['is_shift' => false])->save();

        return $e->refresh();
    }

    private function absen(Employee $e, string $tanggal, string $masuk, string $pulang): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'work_date' => $tanggal,
            'clock_in_at' => $masuk,
            'clock_out_at' => $pulang,
            'status' => 'on_time',
            'lat_in' => 0, 'lng_in' => 0, 'distance_in_m' => 10,
            'site_id' => $e->site_id,
            'region_id' => $e->region_id,
        ]);
    }

    // ── Sumber kebenaran ────────────────────────────────────────────────────

    public function test_jam_pulang_jumat_hanya_berlaku_pada_hari_jumat(): void
    {
        $s = AttendanceSetting::firstOrFail();

        $this->assertSame('15:30', $s->jamPulangPada(self::JUMAT), 'Jumat memakai jam pulang khusus');
        $this->assertSame('16:00', $s->jamPulangPada(self::KAMIS), 'hari lain tetap memakai jam pulang global');
        $this->assertSame(930, $s->jamPulangMenitPada(self::JUMAT), '15.30 = 930 menit');
        $this->assertSame(960, $s->jamPulangMenitPada(self::KAMIS), '16.00 = 960 menit');
    }

    public function test_setelan_jumat_kosong_berarti_ikut_jam_pulang_global(): void
    {
        $s = $this->setelan(['jam_pulang_jumat' => null]);

        $this->assertSame('16:00', $s->jamPulangPada(self::JUMAT), 'tanpa setelan, Jumat tidak berubah');
        $this->assertSame(960, $s->jamPulangMenitPada(self::JUMAT));
    }

    // ── Rekap: PSW ──────────────────────────────────────────────────────────

    public function test_rekap_tidak_menghitung_psw_saat_pulang_1530_pada_jumat(): void
    {
        $e = $this->karyawan();
        $this->absen($e, self::KAMIS, '08:20:00', '15:30:00');
        $this->absen($e, self::JUMAT, '08:20:00', '15:30:00');

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        // Hanya Kamis yang kurang 30 menit; Jumat 15.30 sudah sesuai acuan.
        $this->assertSame(30, $rekap['total_psw'], 'PSW Jumat tidak boleh dihitung dari jam pulang global');
    }

    public function test_rekap_menghitung_psw_jumat_bila_setelan_jumat_kosong(): void
    {
        $this->setelan(['jam_pulang_jumat' => null]);

        $e = $this->karyawan();
        $this->absen($e, self::KAMIS, '08:20:00', '15:30:00');
        $this->absen($e, self::JUMAT, '08:20:00', '15:30:00');

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        // Perilaku lama: 15.30 kurang 30 menit di kedua hari.
        $this->assertSame(60, $rekap['total_psw']);
    }

    public function test_rekap_menghitung_psw_jumat_bila_pulang_sebelum_setelan_jumat(): void
    {
        $e = $this->karyawan();
        $this->absen($e, self::JUMAT, '08:20:00', '15:20:00');

        $rekap = RekapPresenter::buildRekap($e, 2026, 10);

        $this->assertSame(10, $rekap['total_psw'], 'kurang 10 menit dari acuan 15.30');
    }

    // ── Klaim toleransi (lupa pulang) ───────────────────────────────────────

    private function klaimLupaPulang(Employee $e, string $tanggal, string $jam): ToleranceClaim
    {
        return ToleranceClaim::create([
            'employee_id' => $e->id,
            'jenis' => 'lupa_pulang',
            'claim_date' => $tanggal,
            'jam' => $jam,
            'alasan' => 'Lupa tap kartu absensi',
            'site_id' => $e->site_id,
            'region_id' => $e->region_id,
            'approver_id' => $this->adminGowa()->id,
            'status' => 'pending',
        ]);
    }

    public function test_klaim_pulang_1530_pada_jumat_bukan_pulang_lebih_awal(): void
    {
        Carbon::setTestNow(Carbon::parse(self::JUMAT.' 16:00:00', 'Asia/Makassar'));

        $e = $this->karyawan();
        $this->absen($e, self::JUMAT, '08:20:00', '');
        $love = $this->klaimLupaPulang($e, self::JUMAT, '15:30:00');

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/admin/love/{$love->id}/approve")
            ->assertSessionHas('success');

        $row = Attendance::where('employee_id', $e->id)->whereDate('work_date', self::JUMAT)->firstOrFail();
        $this->assertNotSame('early_leave', $row->status, '15.30 hari Jumat = jam pulang normal');
    }

    public function test_klaim_pulang_1530_pada_kamis_tetap_pulang_lebih_awal(): void
    {
        Carbon::setTestNow(Carbon::parse(self::KAMIS.' 16:00:00', 'Asia/Makassar'));

        $e = $this->karyawan();
        $this->absen($e, self::KAMIS, '08:20:00', '');
        $love = $this->klaimLupaPulang($e, self::KAMIS, '15:30:00');

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/admin/love/{$love->id}/approve")
            ->assertSessionHas('success');

        $row = Attendance::where('employee_id', $e->id)->whereDate('work_date', self::KAMIS)->firstOrFail();
        $this->assertSame('early_leave', $row->status, 'hari lain 15.30 masih kurang 30 menit');
    }

    public function test_pekerja_shift_tetap_memakai_jam_pulang_global_pada_jumat(): void
    {
        Carbon::setTestNow(Carbon::parse(self::JUMAT.' 16:00:00', 'Asia/Makassar'));

        $e = $this->karyawan();
        $e->forceFill(['is_shift' => true])->save();
        $this->absen($e, self::JUMAT, '08:20:00', '');
        $love = $this->klaimLupaPulang($e, self::JUMAT, '15:30:00');

        $this->actingAs($this->adminGowa(), 'web')
            ->put("/admin/love/{$love->id}/approve")
            ->assertSessionHas('success');

        $row = Attendance::where('employee_id', $e->id)->whereDate('work_date', self::JUMAT)->firstOrFail();
        $this->assertSame('early_leave', $row->status, 'aturan Jumat hanya untuk non-shift');
    }

    // ── Halaman karyawan ────────────────────────────────────────────────────
    public function test_jadwal_non_shift_memakai_jam_pulang_jumat_per_hari(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Makassar')); // Senin
        $e = $this->karyawan();

        $this->actingAs($e, 'employee')
            ->get('/karyawan/jadwal')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $hari = collect($page->toArray()['props']['minggu'])
                    ->flatMap(fn ($m) => $m['hari'])
                    ->keyBy('tanggal');

                $this->assertSame('15:30', $hari[self::JUMAT]['jamSelesai'], 'baris Jumat pulang 15.30');
                $this->assertSame('16:00', $hari[self::KAMIS]['jamSelesai'], 'baris Kamis tetap 16.00');
                $this->assertSame('08:30', $hari[self::JUMAT]['jamMulai'], 'jam masuk Jumat tidak berubah');

                $page->where('jamKerja.pulang', '16:00')
                    ->where('jamKerja.pulangJumat', '15:30');
            });
    }

    public function test_absensi_menampilkan_jam_pulang_hari_ini_sesuai_harinya(): void
    {
        $e = $this->karyawan();

        Carbon::setTestNow(Carbon::parse(self::JUMAT.' 08:00:00', 'Asia/Makassar'));
        $this->actingAs($e, 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.jamPulang', '16:00')
                ->where('settings.jamPulangHariIni', '15:30'));

        Carbon::setTestNow(Carbon::parse(self::KAMIS.' 08:00:00', 'Asia/Makassar'));
        $this->actingAs($e, 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('settings.jamPulangHariIni', '16:00'));
    }

    // ── Pengaturan Super Admin ──────────────────────────────────────────────

    private function payload(array $override = []): array
    {
        return array_merge([
            'jamMasuk' => '08:30',
            'jamPulang' => '16:00',
            'jamPulangJumat' => '15:30',
            'toleransi' => 15,
            'loveMax' => 4,
            'hariKerja' => ['1', '2', '3', '4', '5'],
        ], $override);
    }

    public function test_super_admin_menyimpan_dan_mengosongkan_jam_pulang_jumat(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')
            ->put('/super-admin/settings', $this->payload(['jamPulangJumat' => '15:00']))
            ->assertSessionHas('success');
        $this->assertSame('15:00:00', AttendanceSetting::firstOrFail()->jam_pulang_jumat);

        // Dikosongkan → kembali ikut jam pulang global.
        $this->actingAs($admin, 'web')
            ->put('/super-admin/settings', $this->payload(['jamPulangJumat' => '']))
            ->assertSessionHas('success');
        $this->assertNull(AttendanceSetting::firstOrFail()->jam_pulang_jumat);
        $this->assertSame('16:00', AttendanceSetting::firstOrFail()->jamPulangPada(self::JUMAT));
    }

    public function test_jam_pulang_jumat_harus_setelah_jam_masuk(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->from('/super-admin/settings')
            ->put('/super-admin/settings', $this->payload(['jamPulangJumat' => '08:00']))
            ->assertSessionHasErrors('jamPulangJumat');

        $this->assertSame('15:30:00', AttendanceSetting::firstOrFail()->jam_pulang_jumat, 'nilai lama tidak tertimpa');
    }

    public function test_admin_wilayah_tidak_bisa_mengubah_jam_pulang_jumat(): void
    {
        $this->actingAs($this->adminGowa(), 'web')
            ->put('/admin/settings', $this->payload(['jamPulangJumat' => '14:00']))
            ->assertForbidden();

        $this->assertSame('15:30:00', AttendanceSetting::firstOrFail()->jam_pulang_jumat);
    }
}
