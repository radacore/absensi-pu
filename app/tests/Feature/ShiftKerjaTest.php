<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\EarlyLeaveRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Support\ShiftResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Absensi pekerja shift (K1–K12, P2, P4, P5).
 *
 * Aturan inti yang dijaga di sini:
 *  - panjang shift selalu 24 jam ÷ jumlah shift (2 → 12 jam, 3 → 8 jam);
 *  - jadwal disusun manual, satu baris per orang per tanggal;
 *  - shift malam tetap SATU baris absensi bertanggal hari mulai;
 *  - absen pulang sebelum jam shift berakhir butuh persetujuan Admin Wilayah;
 *  - pekerja shift tidak terkena gerbang hari libur nasional.
 */
class ShiftKerjaTest extends TestCase
{
    use RefreshDatabase;

    /** Senin — dipakai sebagai "hari ini" di hampir semua uji. */
    private const SENIN = '2026-10-12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));

        // Hari libur bawaan seeder dimatikan supaya tiap uji mengatur sendiri.
        Holiday::query()->delete();

        Carbon::setTestNow(Carbon::parse(self::SENIN.' 07:05:00', 'Asia/Makassar'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Pembantu ────────────────────────────────────────────

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminWilayah(int $regionId = 2): User
    {
        return User::where('role', 'admin_wilayah')->where('region_id', $regionId)->firstOrFail();
    }

    private function karyawan(int $regionId = 2): Employee
    {
        return Employee::where('region_id', $regionId)->whereNotNull('site_id')->firstOrFail();
    }

    private function pola(array $attrs = []): ShiftPattern
    {
        return ShiftPattern::create(array_merge([
            'nama' => 'Pola Uji',
            'jumlah_shift' => 2,
            'jam_mulai_shift_1' => '07:00:00',
            'is_active' => true,
        ], $attrs));
    }

    /** Tandai karyawan sebagai pekerja shift + beri jadwal pada satu tanggal. */
    private function jadikanShift(Employee $e, int $shiftKe = 1, ?ShiftPattern $p = null, ?string $tanggal = null): ShiftSchedule
    {
        $p ??= $this->pola();

        $e->forceFill(['is_shift' => true])->save();

        return ShiftSchedule::create([
            'employee_id' => $e->id,
            'tanggal' => $tanggal ?? self::SENIN,
            'shift_ke' => $shiftKe,
            'shift_pattern_id' => $p->id,
        ]);
    }

    private function jam(int $h, int $m = 0, string $tanggal = self::SENIN): void
    {
        Carbon::setTestNow(Carbon::parse(sprintf('%s %02d:%02d:00', $tanggal, $h, $m), 'Asia/Makassar'));
    }

    /** Baris jadwal seorang karyawan pada satu tanggal, atau null bila tidak ada. */
    private function jadwal(Employee $e, string $tanggal): ?ShiftSchedule
    {
        return ShiftSchedule::where('employee_id', $e->id)->whereDate('tanggal', $tanggal)->first();
    }

    private function absenMasuk(Employee $me)
    {
        return $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $me->site->lat,
                'lng' => $me->site->lng,
                'selfie' => UploadedFile::fake()->image('masuk.jpg', 200, 200),
            ]);
    }

    private function absenPulang(Employee $me, array $extra = [])
    {
        return $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-out', array_merge([
                'lat' => $me->site->lat,
                'lng' => $me->site->lng,
                'selfie_pulang' => UploadedFile::fake()->image('pulang.jpg', 200, 200),
                'catatan_pulang' => 'Pekerjaan hari ini selesai',
            ], $extra));
    }

    // ── Pola shift: panjang diturunkan, bukan diisi ─────────

    public function test_panjang_shift_diturunkan_dari_jumlah_shift(): void
    {
        $dua = $this->pola(['jumlah_shift' => 2]);
        $this->assertSame(720, $dua->durasiMenit());
        $this->assertSame(['Pagi', 'Malam'], [$dua->labelShift(1), $dua->labelShift(2)]);
        $this->assertSame(['07:00', '19:00'], [$dua->jamMulaiShift(1), $dua->jamMulaiShift(2)]);

        $tiga = $this->pola(['nama' => 'Pola 3', 'jumlah_shift' => 3]);
        $this->assertSame(480, $tiga->durasiMenit());
        $this->assertSame(['Pagi', 'Siang', 'Malam'], [$tiga->labelShift(1), $tiga->labelShift(2), $tiga->labelShift(3)]);
        $this->assertSame(['07:00', '15:00', '23:00'], [
            $tiga->jamMulaiShift(1), $tiga->jamMulaiShift(2), $tiga->jamMulaiShift(3),
        ]);

        $empat = $this->pola(['nama' => 'Pola 4', 'jumlah_shift' => 4]);
        $this->assertSame(360, $empat->durasiMenit());
        $this->assertSame('01:00', $empat->jamMulaiShift(4));
    }

    public function test_rentang_shift_malam_melewati_tengah_malam(): void
    {
        $pola = $this->pola(['jumlah_shift' => 2]);
        $w = $pola->windowFor(Carbon::parse(self::SENIN, 'Asia/Makassar'), 2);

        $this->assertSame(self::SENIN.' 19:00', $w['mulai']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 07:00', $w['selesai']->format('Y-m-d H:i'));
    }

    public function test_admin_menyimpan_pola_shift_lewat_halaman(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->post('/super-admin/pola-shift', [
                'nama' => 'Regu Pabrik',
                'jumlahShift' => 3,
                'jamMulaiShift1' => '07:00',
                'cakupan' => 'global',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_patterns', [
            'nama' => 'Regu Pabrik',
            'jumlah_shift' => 3,
            'region_id' => null,
            'site_id' => null,
        ]);
    }

    public function test_admin_wilayah_tidak_bisa_membuat_pola_global(): void
    {
        $this->actingAs($this->adminWilayah(), 'web')
            ->post('/admin/pola-shift', [
                'nama' => 'Pola Global',
                'jumlahShift' => 2,
                'jamMulaiShift1' => '07:00',
                'cakupan' => 'global',
            ])
            ->assertSessionHasErrors('cakupan');

        $this->assertDatabaseCount('shift_patterns', 0);
    }

    public function test_admin_wilayah_bisa_membuat_pola_untuk_wilayahnya(): void
    {
        $this->actingAs($this->adminWilayah(), 'web')
            ->post('/admin/pola-shift', [
                'nama' => 'Pola Gowa',
                'jumlahShift' => 3,
                'jamMulaiShift1' => '06:00',
                'cakupan' => 'wilayah',
                'regionId' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_patterns', ['nama' => 'Pola Gowa', 'region_id' => 2, 'site_id' => null]);
    }

    public function test_tidak_boleh_ada_dua_pola_untuk_cakupan_yang_sama(): void
    {
        $this->pola();

        $this->actingAs($this->superAdmin(), 'web')
            ->post('/super-admin/pola-shift', [
                'nama' => 'Pola Kedua',
                'jumlahShift' => 3,
                'jamMulaiShift1' => '07:00',
                'cakupan' => 'global',
            ])
            ->assertSessionHasErrors('cakupan');

        $this->assertDatabaseCount('shift_patterns', 1);
    }

    // ── Resolusi pola: yang paling spesifik menang ──────────

    public function test_pola_paling_spesifik_yang_dipakai(): void
    {
        $e = $this->karyawan();
        $e->forceFill(['is_shift' => true])->save();

        $global = $this->pola(['nama' => 'Global', 'jumlah_shift' => 2]);
        $wilayah = $this->pola(['nama' => 'Wilayah', 'jumlah_shift' => 3, 'region_id' => $e->region_id]);
        $lokasi = $this->pola(['nama' => 'Lokasi', 'jumlah_shift' => 4, 'region_id' => $e->region_id, 'site_id' => $e->site_id]);

        $this->assertSame($lokasi->id, ShiftResolver::patternFor($e->fresh())->id);

        $lokasi->delete();
        $this->assertSame($wilayah->id, ShiftResolver::patternFor($e->fresh())->id);

        $wilayah->delete();
        $this->assertSame($global->id, ShiftResolver::patternFor($e->fresh())->id);
    }

    public function test_pola_nonaktif_tidak_dipakai(): void
    {
        $e = $this->karyawan();
        $e->forceFill(['is_shift' => true])->save();

        $global = $this->pola(['nama' => 'Global']);
        $this->pola(['nama' => 'Lokasi Mati', 'region_id' => $e->region_id, 'site_id' => $e->site_id, 'is_active' => false]);

        $this->assertSame($global->id, ShiftResolver::patternFor($e->fresh())->id);
    }

    public function test_karyawan_bukan_pekerja_shift_tidak_punya_pola(): void
    {
        $e = $this->karyawan();
        $this->pola();

        $this->assertFalse((bool) $e->is_shift);
        $this->assertNull(ShiftResolver::patternFor($e));
    }

    // ── Jadwal mingguan manual ──────────────────────────────

    public function test_jadwal_shift_disimpan_dan_diganti_utuh_per_rentang(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e);
        $admin = $this->superAdmin();

        // Halaman selalu mengirim sepekan penuh (7 × jumlah orang), jadi
        // rentang yang diganti selalu Senin–Minggu.
        $minggu = ['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18'];

        $sepekan = fn (array $perTanggal) => collect($minggu)
            ->map(fn (string $t) => [
                'employeeId' => $e->id,
                'tanggal' => $t,
                'shiftKe' => array_key_exists($t, $perTanggal) ? $perTanggal[$t] : 1,
            ])
            ->values()
            ->all();

        $kirim = function (array $baris) use ($admin) {
            return $this->actingAs($admin, 'web')
                ->post('/super-admin/jadwal-shift', ['baris' => $baris])
                ->assertSessionHasNoErrors();
        };

        // Sepekan pertama: Senin–Jumat shift 1, Sabtu–Minggu ditandai libur.
        $kirim($sepekan(['2026-10-17' => null, '2026-10-18' => null]));

        $this->assertSame(1, (int) $this->jadwal($e, self::SENIN)->shift_ke);

        $libur = $this->jadwal($e, '2026-10-17');
        $this->assertNotNull($libur, 'Sel libur harus tersimpan sebagai baris.');
        $this->assertNull($libur->shift_ke);
        $this->assertSame(7, ShiftSchedule::where('employee_id', $e->id)->count());

        // Simpan ulang: Rabu jadi shift 2, Kamis dikosongkan admin.
        $kedua = $sepekan(['2026-10-14' => 2, '2026-10-17' => null, '2026-10-18' => null]);
        $kedua = array_values(array_filter($kedua, fn (array $r) => $r['tanggal'] !== '2026-10-15'));
        $kirim($kedua);

        $this->assertNull($this->jadwal($e, '2026-10-15'), 'Sel yang dikosongkan admin ikut terhapus.');
        $this->assertSame(2, (int) $this->jadwal($e, '2026-10-14')->shift_ke);
        $this->assertSame(1, (int) $this->jadwal($e, self::SENIN)->shift_ke);
        $this->assertSame(6, ShiftSchedule::where('employee_id', $e->id)->count());
    }

    public function test_jadwal_shift_menolak_karyawan_yang_bukan_pekerja_shift(): void
    {
        $e = $this->karyawan();

        $this->actingAs($this->superAdmin(), 'web')
            ->post('/super-admin/jadwal-shift', ['baris' => [
                ['employeeId' => $e->id, 'tanggal' => self::SENIN, 'shiftKe' => 1],
            ]])
            ->assertSessionHasErrors('baris');

        $this->assertDatabaseCount('shift_schedules', 0);
    }

    public function test_admin_wilayah_tidak_bisa_menjadwalkan_karyawan_wilayah_lain(): void
    {
        $e = $this->karyawan(1);
        $this->jadikanShift($e);

        $this->actingAs($this->adminWilayah(2), 'web')
            ->post('/admin/jadwal-shift', ['baris' => [
                ['employeeId' => $e->id, 'tanggal' => self::SENIN, 'shiftKe' => 1],
            ]])
            ->assertForbidden();
    }

    // ── Absen masuk pekerja shift ───────────────────────────

    public function test_absen_masuk_pekerja_shift_mengikuti_jam_shift(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $row = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame(self::SENIN, $row->work_date->toDateString());
        $this->assertSame(1, (int) $row->shift_ke);
        $this->assertSame('on_time', $row->status);
    }

    public function test_keterlambatan_dihitung_dari_jam_mulai_shift_plus_toleransi(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        // Toleransi global 15 menit → 07:16 sudah terlambat.
        $this->jam(7, 16);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $this->assertSame('late', Attendance::where('employee_id', $e->id)->firstOrFail()->status);
    }

    public function test_shift_malam_absen_masuk_bertanggal_hari_mulai(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 2);

        // 02:00 dini hari — masih di dalam shift malam yang mulai 19:00 kemarin.
        $this->jam(2, 0, '2026-10-13');
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $row = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame(self::SENIN, $row->work_date->toDateString());
        $this->assertSame(2, (int) $row->shift_ke);
    }

    public function test_shift_malam_absen_pulang_mengisi_baris_hari_mulai(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 2);

        $this->jam(2, 0, '2026-10-13');
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $this->jam(7, 30, '2026-10-13');
        $this->absenPulang($e)->assertSessionHasNoErrors();

        $rows = Attendance::where('employee_id', $e->id)->get();
        $this->assertCount(1, $rows, 'Shift malam harus tetap satu baris absensi.');
        $this->assertSame(self::SENIN, $rows->first()->work_date->toDateString());
        $this->assertNotNull($rows->first()->clock_out_at);
    }

    public function test_pekerja_shift_tetap_bisa_absen_pada_hari_libur_nasional(): void
    {
        AttendanceSetting::firstOrFail()->update(['absen_libur_aktif' => true, 'absen_libur_mode' => 'tolak']);
        Holiday::create(['tanggal' => self::SENIN, 'nama' => 'Hari Libur Uji']);

        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $row = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertSame('on_time', $row->status, 'Absen shift pada hari libur dicatat sebagai absen biasa (P4).');
    }

    public function test_karyawan_biasa_masih_ditolak_pada_hari_libur_nasional(): void
    {
        AttendanceSetting::firstOrFail()->update(['absen_libur_aktif' => true, 'absen_libur_mode' => 'tolak']);
        Holiday::create(['tanggal' => self::SENIN, 'nama' => 'Hari Libur Uji']);

        $e = $this->karyawan();

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasErrors('work_date');

        $this->assertDatabaseCount('attendances', 0);
    }

    // ── Gerbang pulang cepat (P2/P5) ────────────────────────

    public function test_pekerja_shift_tidak_bisa_pulang_sebelum_jam_shift_berakhir(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $this->jam(12, 0);
        $this->absenPulang($e)->assertSessionHasErrors('clock_out_at');

        $this->assertNull(Attendance::where('employee_id', $e->id)->firstOrFail()->clock_out_at);
    }

    public function test_pulang_cepat_yang_disetujui_membuka_absen_pulang(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        EarlyLeaveRequest::create([
            'employee_id' => $e->id,
            'tanggal' => self::SENIN,
            'jam_rencana' => '12:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'status' => 'Disetujui',
            'region_id' => $e->region_id,
            'site_id' => $e->site_id,
        ]);

        $this->jam(12, 0);
        $this->absenPulang($e)->assertSessionHasNoErrors();

        $this->assertNotNull(Attendance::where('employee_id', $e->id)->firstOrFail()->clock_out_at);
    }

    public function test_pulang_cepat_yang_masih_menunggu_belum_membuka_absen_pulang(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        EarlyLeaveRequest::create([
            'employee_id' => $e->id,
            'tanggal' => self::SENIN,
            'jam_rencana' => '12:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'status' => 'Menunggu',
            'region_id' => $e->region_id,
            'site_id' => $e->site_id,
        ]);

        $this->jam(12, 0);
        $this->absenPulang($e)->assertSessionHasErrors('clock_out_at');
    }

    public function test_karyawan_biasa_tidak_terkena_gerbang_pulang_cepat(): void
    {
        $e = $this->karyawan();

        $this->jam(7, 40);
        $this->absenMasuk($e)->assertSessionHasNoErrors();

        $this->jam(9, 0);
        $this->absenPulang($e)->assertSessionHasNoErrors();

        $this->assertNotNull(Attendance::where('employee_id', $e->id)->firstOrFail()->clock_out_at);
    }

    // ── Alur pengajuan pulang cepat ─────────────────────────

    public function test_karyawan_mengajukan_pulang_cepat_lalu_disetujui_admin(): void
    {
        $e = $this->karyawan();
        $admin = $this->adminWilayah($e->region_id);

        $this->actingAs($e, 'employee')
            ->post('/karyawan/pulang-cepat', [
                'tanggal' => self::SENIN,
                'jamRencana' => '12:00',
                'alasan' => 'Anak sakit, harus ke rumah sakit',
                'approver_id' => $admin->id,
            ])
            ->assertSessionHasNoErrors();

        $req = EarlyLeaveRequest::firstOrFail();
        $this->assertSame('Menunggu', $req->status);

        $this->actingAs($admin, 'web')
            ->put("/admin/pulang-cepat/{$req->id}/approve")
            ->assertSessionHasNoErrors();

        $this->assertSame('Disetujui', $req->fresh()->status);
        $this->assertSame($admin->id, (int) $req->fresh()->approved_by);
    }

    public function test_pengajuan_pulang_cepat_ditolak_bila_atasan_di_luar_daftar(): void
    {
        $e = $this->karyawan();

        $this->actingAs($e, 'employee')
            ->post('/karyawan/pulang-cepat', [
                'tanggal' => self::SENIN,
                'jamRencana' => '12:00',
                'alasan' => 'Keperluan keluarga mendesak',
                'approver_id' => $this->superAdmin()->id,
            ])
            ->assertSessionHasErrors('approver_id');

        $this->assertDatabaseCount('early_leave_requests', 0);
    }

    public function test_hanya_satu_pengajuan_menunggu_per_tanggal(): void
    {
        $e = $this->karyawan();
        $admin = $this->adminWilayah($e->region_id);

        $payload = [
            'tanggal' => self::SENIN,
            'jamRencana' => '12:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'approver_id' => $admin->id,
        ];

        $this->actingAs($e, 'employee')->post('/karyawan/pulang-cepat', $payload)->assertSessionHasNoErrors();
        $this->actingAs($e, 'employee')->post('/karyawan/pulang-cepat', $payload)->assertSessionHasErrors('tanggal');

        $this->assertDatabaseCount('early_leave_requests', 1);
    }

    public function test_admin_menolak_pengajuan_pulang_cepat_dengan_catatan(): void
    {
        $e = $this->karyawan();
        $admin = $this->adminWilayah($e->region_id);

        $req = EarlyLeaveRequest::create([
            'employee_id' => $e->id,
            'tanggal' => self::SENIN,
            'jam_rencana' => '12:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'status' => 'Menunggu',
            'region_id' => $e->region_id,
            'site_id' => $e->site_id,
        ]);

        $this->actingAs($admin, 'web')
            ->put("/admin/pulang-cepat/{$req->id}/reject", ['note' => 'Beban kerja sedang tinggi'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ditolak', $req->fresh()->status);
        $this->assertSame('Beban kerja sedang tinggi', $req->fresh()->note);
    }

    public function test_admin_wilayah_tidak_bisa_memproses_pengajuan_wilayah_lain(): void
    {
        $e = $this->karyawan(1);

        $req = EarlyLeaveRequest::create([
            'employee_id' => $e->id,
            'tanggal' => self::SENIN,
            'jam_rencana' => '12:00:00',
            'alasan' => 'Keperluan keluarga mendesak',
            'status' => 'Menunggu',
            'region_id' => 1,
            'site_id' => $e->site_id,
        ]);

        $this->actingAs($this->adminWilayah(2), 'web')
            ->put("/admin/pulang-cepat/{$req->id}/approve")
            ->assertForbidden();
    }

    // ── Penanda pekerja shift & tampilan ────────────────────

    public function test_admin_dapat_menandai_karyawan_sebagai_pekerja_shift(): void
    {
        $e = $this->karyawan();

        $this->actingAs($this->superAdmin(), 'web')
            ->put("/super-admin/employees/{$e->id}", [
                'nik' => $e->nik,
                'nip' => $e->nip,
                'nama' => $e->name,
                'email' => $e->email,
                'gol' => $e->golongan ?: '-',
                'jabatan' => $e->jabatan,
                'unit' => $e->unit_kerja,
                'status' => $e->status_kepegawaian,
                'region' => $e->region->name,
                'office_location_id' => $e->site_id,
                'isShift' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $e->fresh()->is_shift);
    }

    public function test_dashboard_karyawan_menyesuaikan_pekerja_shift(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->actingAs($e, 'employee')
            ->get('/karyawan')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Karyawan/Dashboard')
                ->where('isShift', true)
                ->where('shiftHariIni', 'Pagi'));
    }

    public function test_dashboard_karyawan_biasa_tetap_pakai_jam_kantor(): void
    {
        $e = $this->karyawan();

        $this->actingAs($e, 'employee')
            ->get('/karyawan')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Karyawan/Dashboard')
                ->where('isShift', false)
                ->where('shiftHariIni', null));
    }

    public function test_halaman_absen_menyertakan_info_shift(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->jam(7, 5);
        $this->actingAs($e, 'employee')
            ->get('/karyawan/absensi')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Karyawan/Absensi')
                ->where('isShift', true)
                ->where('shift.label', 'Pagi')
                ->where('shift.tanggal', self::SENIN)
                ->where('bolehPulang', false)
                ->where('workDateAktif', self::SENIN));
    }

    public function test_rekap_karyawan_menampilkan_jadwal_shift_sebulan(): void
    {
        $e = $this->karyawan();
        $this->jadikanShift($e, 1);

        $this->actingAs($e, 'employee')
            ->get('/karyawan/rekap')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Karyawan/Rekap')
                ->where('isShift', true)
                ->where('jadwalBulan.0.tgl', self::SENIN)
                ->where('jadwalBulan.0.shift', 'Pagi'));
    }

    public function test_halaman_pola_dan_jadwal_shift_dapat_dibuka(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')
            ->get('/super-admin/pola-shift')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/PolaShift'));

        $this->actingAs($admin, 'web')
            ->get('/super-admin/jadwal-shift')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/JadwalShift'));

        $this->actingAs($admin, 'web')
            ->get('/super-admin/pulang-cepat')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/PulangAwal'));
    }

    public function test_halaman_jadwal_shift_hanya_memuat_pekerja_shift(): void
    {
        $shift = $this->karyawan();
        $this->jadikanShift($shift);

        $this->actingAs($this->superAdmin(), 'web')
            ->get('/super-admin/jadwal-shift')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/JadwalShift')
                ->has('employees', 1)
                ->where('employees.0.id', $shift->id)
                ->where('employees.0.jumlahShift', 2)
                ->where('employees.0.cells.'.self::SENIN, 1));
    }

    public function test_halaman_jadwal_shift_mengurutkan_minggu_dari_senin(): void
    {
        $this->jam(10, 0, '2026-10-15'); // Kamis

        $this->actingAs($this->superAdmin(), 'web')
            ->get('/super-admin/jadwal-shift')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/JadwalShift')
                ->where('senin', self::SENIN)
                ->has('hari', 7)
                ->where('hari.6', '2026-10-18'));
    }
}
