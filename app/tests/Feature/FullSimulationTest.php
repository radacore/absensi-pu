<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Region;
use App\Models\Site;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SIMULASI END-TO-END SELURUH FITUR.
 *
 * Berbeda dari tes lain yang fokus per-fitur, berkas ini "memakai aplikasi"
 * seperti pengguna sungguhan: login, absen, klaim toleransi, cuti, dinas
 * (dengan unggah dokumen), pengumuman, profil, sampai seluruh CRUD admin.
 *
 * Setiap tes menuliskan perilaku yang SEHARUSNYA. Bila tes gagal, itu berarti
 * aplikasi berperilaku lain dari yang seharusnya — yaitu BUG, bukan tesnya salah.
 *
 * ── 6 CELAH KEAMANAN YANG DITEMUKAN SIMULASI (DIPERBAIKI 20 Sep 2026) ──
 * Tes di bawah ini adalah penjaga regresi. Semuanya LULUS sejak perbaikannya.
 * Bila salah satunya gagal lagi, berarti perbaikannya mundur.
 *
 *   1. test_keamanan_halaman_detail_titik_tidak_membocorkan_data_lintas_wilayah
 *      Admin\SiteController::show dulu mengirim SELURUH wilayah + SELURUH
 *      karyawan ke admin wilayah mana pun. Kini cakupan diperiksa (403) dan
 *      query disaring.
 *
 *   2. test_keamanan_ubah_karyawan_tidak_boleh_menugaskan_ke_titik_wilayah_lain
 *      Admin\EmployeeController::update dulu tidak memeriksa apakah
 *      `office_location_id` benar-benar milik `region` (hanya `store` yang
 *      memeriksa). Kini keduanya memakai `assertSiteInRegion()`.
 *
 *   3. test_keamanan_karyawan_tidak_bisa_menandai_pengumuman_wilayah_lain
 *      Karyawan\PengumumanController::markRead dulu tanpa cek visibilitas.
 *
 *   4. test_keamanan_admin_dengan_region_null_tidak_boleh_melihat_semua_data
 *      `users.region_id` nullable; bila null, seluruh filter wilayah mati
 *      (`$scope` menjadi null) dan diperlakukan seperti super admin. Kini
 *      EnsureAdminRole menolak admin wilayah tanpa cakupan.
 *
 *   5. test_keamanan_menghapus_wilayah_berisi_karyawan_tidak_boleh_menghapus_karyawan
 *      `regions` -> `employees` memakai `cascadeOnDelete` dan
 *      RegionController::destroy tanpa pengaman, sehingga satu permintaan
 *      menghapus seluruh karyawan wilayah. Kini ditolak selama masih ada
 *      karyawan.
 *
 *   6. test_keamanan_admin_nonaktif_kehilangan_akses_sesi
 *      EnsureAdminRole dulu tidak memeriksa `is_active`, sehingga sesi admin
 *      yang dinonaktifkan tetap berlaku. Kini sesinya dicabut.
 * ──────────────────────────────────────────────────────────────────────
 *
 * Jalankan di MySQL:
 *   DB_CONNECTION=mysql DB_DATABASE=absensi_sim DB_USERNAME=absensi_pu \
 *   DB_PASSWORD=absensi_dev_pass php artisan test --filter=FullSimulationTest
 *
 * Jalankan hanya penjaga regresi keamanan:
 *   php artisan test --filter=FullSimulationTest --group=regresi-keamanan
 */
class FullSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();

        // Unggahan tidak boleh menyentuh bucket S3 sungguhan.
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    /** Admin wilayah Gowa (region_id = 2). */
    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    /** Admin wilayah Bone (region_id = 4) — dipakai sebagai "wilayah lain". */
    private function adminBone(): User
    {
        return User::where('email', 'admin.bone@bbws-pj.go.id')->firstOrFail();
    }

    private function karyawan(string $email = 'andi@bbws-pj.go.id'): Employee
    {
        return Employee::where('email', $email)->firstOrFail();
    }

    /** Karyawan dari wilayah lain (region 3 / Kab. Maros). */
    private function karyawanLain(): Employee
    {
        return $this->karyawan('dewi@bbws-pj.go.id');
    }

    /** Hari kerja (Senin–Jumat) di bulan berjalan yang tidak melewati hari ini. */
    private function hariKerjaBulanIni(): string
    {
        $today = now('Asia/Makassar')->copy();
        $d = $today->copy()->startOfMonth();
        while ($d->lte($today) && $d->isWeekend()) {
            $d->addDay();
        }
        $this->assertTrue($d->lte($today), 'Prasyarat: harus ada hari kerja di bulan ini.');

        return $d->toDateString();
    }

    private function setJamKerja(string $masuk, string $pulang = '16:00', int $toleransi = 15, int $loveMax = 4): void
    {
        AttendanceSetting::query()->update([
            'jam_masuk' => $masuk,
            'jam_pulang' => $pulang,
            'toleransi_late_menit' => $toleransi,
            'love_max' => $loveMax,
        ]);
    }

    private function pdf(string $name = 'surat-tugas.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    /**
     * Login sebagai admin (guard `web`).
     *
     * Guard HARUS disebut eksplisit. `actingAs($user)` tanpa guard memakai guard
     * default, dan guard default ikut berubah bila sebelumnya ada
     * `actingAs($employee, 'employee')` — akibatnya admin akan terpasang di guard
     * `employee` dan seluruh aksi admin gagal karena dianggap belum login.
     */
    private function asAdmin(User $user): self
    {
        return $this->actingAs($user, 'web');
    }

    // ==================================================================
    // 0. PRASYARAT — pastikan simulasi benar-benar berjalan di MySQL
    // ==================================================================

    public function test_prasyarat_simulasi_berjalan_di_mysql(): void
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $db = $conn->getDatabaseName();
        $version = $conn->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        fwrite(STDERR, "\n[SIM] driver={$driver} database={$db} server={$version}\n");

        if ($driver !== 'mysql') {
            // phpunit.xml memaksa SQLite :memory: secara default. Simulasi lengkap
            // (information_schema, AUTO_INCREMENT, MODIFY ENUM) hanya valid di MySQL;
            // jalankan dengan DB_CONNECTION=mysql agar uji ini benar-benar dieksekusi.
            $this->markTestSkipped("Simulasi lengkap butuh MySQL; driver saat ini: {$driver}.");
        }

        $this->assertSame(21, DB::table('information_schema.tables')
            ->where('table_schema', $db)->where('table_type', 'BASE TABLE')->count(),
            'Jumlah tabel tidak sesuai harapan.');
    }

    // ==================================================================
    // 1. AUTENTIKASI & BATAS PERAN
    // ==================================================================

    public function test_login_karyawan_pakai_nip(): void
    {
        $this->post('/karyawan/login', [
            'login' => '198501012010011001',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs($this->karyawan(), 'employee');
    }

    public function test_login_karyawan_pakai_nik(): void
    {
        $this->post('/karyawan/login', [
            'login' => '7371001234567890',
            'password' => 'password123',
        ])->assertRedirect('/karyawan');

        $this->assertAuthenticatedAs($this->karyawan(), 'employee');
    }

    public function test_login_karyawan_password_salah_ditolak(): void
    {
        $this->from('/karyawan/login')->post('/karyawan/login', [
            'login' => '7371001234567890',
            'password' => 'salah-sekali',
        ])->assertRedirect('/karyawan/login')->assertSessionHasErrors('login');

        $this->assertGuest('employee');
    }

    public function test_login_ketiga_panel_admin(): void
    {
        $this->post('/super-admin/login', ['email' => 'pusat@bbws-pj.go.id', 'password' => 'password123'])
            ->assertRedirect('/super-admin');
        $this->assertAuthenticatedAs($this->superAdmin());

        Auth::guard('web')->logout();

        $this->post('/admin/login', ['email' => 'admin.gowa@bbws-pj.go.id', 'password' => 'password123'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->adminGowa());
    }

    public function test_admin_tidak_bisa_login_ke_panel_super_admin(): void
    {
        $this->from('/super-admin/login')
            ->post('/super-admin/login', ['email' => 'admin.gowa@bbws-pj.go.id', 'password' => 'password123'])
            ->assertRedirect('/super-admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_tidak_bisa_masuk_panel_admin_wilayah(): void
    {
        $this->asAdmin($this->superAdmin())
            ->get('/admin')
            ->assertRedirect('/super-admin');
    }

    public function test_admin_wilayah_tidak_bisa_masuk_panel_super_admin(): void
    {
        $this->asAdmin($this->adminGowa())
            ->get('/super-admin')
            ->assertRedirect('/admin');
    }

    public function test_tamu_tidak_bisa_masuk_panel_mana_pun(): void
    {
        $this->get('/karyawan')->assertRedirect('/karyawan/login');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/super-admin')->assertRedirect('/super-admin/login');
    }

    public function test_karyawan_tidak_bisa_masuk_panel_admin(): void
    {
        // Guard berbeda: sesi karyawan tidak boleh dianggap sesi admin.
        $this->actingAs($this->karyawan(), 'employee')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_nonaktif_tidak_bisa_login(): void
    {
        $this->adminGowa()->update(['is_active' => false]);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin.gowa@bbws-pj.go.id', 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ==================================================================
    // 2. ABSENSI (GPS + SELFIE)
    // ==================================================================

    public function test_absensi_happy_path_masuk_dan_pulang(): void
    {
        $me = $this->karyawan();
        $site = $me->site;
        $this->setJamKerja('23:59', '23:59', 60); // cutoff jauh di depan → on_time

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $site->lat,
                'lng' => $site->lng,
                'selfie_url' => 'data:image/jpeg;base64,/9j/AAAA',
            ])
            ->assertRedirect('/karyawan/absensi')
            ->assertSessionHasNoErrors();

        $row = Attendance::where('employee_id', $me->id)->firstOrFail();
        $this->assertSame('on_time', $row->status);
        $this->assertSame(0, (int) $row->distance_in_m);
        $this->assertSame($site->id, $row->site_id);
        $this->assertNotNull($row->clock_in_at);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-out', ['lat' => $site->lat, 'lng' => $site->lng])
            ->assertRedirect('/karyawan/absensi')
            ->assertSessionHasNoErrors();

        $this->assertNotNull($row->fresh()->clock_out_at);
    }

    public function test_absensi_ditolak_di_luar_radius(): void
    {
        $me = $this->karyawan();
        $site = $me->site;

        // Geser ~1 derajat lintang (≈111 km) — jauh di luar radius.
        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => (float) $site->lat + 1,
                'lng' => $site->lng,
            ])
            ->assertSessionHasErrors('distance');

        $this->assertSame(0, Attendance::where('employee_id', $me->id)->count());
    }

    public function test_absensi_ganda_di_hari_yang_sama_ditolak(): void
    {
        $me = $this->karyawan();
        $site = $me->site;

        Attendance::create([
            'employee_id' => $me->id,
            'work_date' => now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:00:00',
            'status' => 'on_time',
            'lat_in' => $site->lat,
            'lng_in' => $site->lng,
            'distance_in_m' => 0,
            'site_id' => $site->id,
            'region_id' => $me->region_id,
        ]);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', ['lat' => $site->lat, 'lng' => $site->lng])
            ->assertSessionHasErrors('work_date');

        $this->assertSame(1, Attendance::where('employee_id', $me->id)->count());
    }

    public function test_absensi_pulang_tanpa_absen_masuk_ditolak(): void
    {
        $me = $this->karyawan();
        $site = $me->site;

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-out', ['lat' => $site->lat, 'lng' => $site->lng])
            ->assertSessionHasErrors('work_date');
    }

    public function test_absensi_pulang_dua_kali_ditolak(): void
    {
        $me = $this->karyawan();
        $site = $me->site;

        Attendance::create([
            'employee_id' => $me->id,
            'work_date' => now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:00:00',
            'clock_out_at' => '16:00:00',
            'status' => 'on_time',
            'lat_in' => $site->lat,
            'lng_in' => $site->lng,
            'distance_in_m' => 0,
            'site_id' => $site->id,
            'region_id' => $me->region_id,
        ]);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-out', ['lat' => $site->lat, 'lng' => $site->lng])
            ->assertSessionHasErrors('work_date');
    }

    public function test_absensi_menandai_terlambat_bila_melewati_toleransi(): void
    {
        $me = $this->karyawan();
        $site = $me->site;
        // cutoff = 00:01 + 0 menit → jam berapa pun sekarang pasti "late"
        $this->setJamKerja('00:01', '16:00', 0);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', ['lat' => $site->lat, 'lng' => $site->lng])
            ->assertSessionHasNoErrors();

        $this->assertSame('late', Attendance::where('employee_id', $me->id)->firstOrFail()->status);
    }

    public function test_absensi_menolak_koordinat_tidak_valid(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/absensi')
            ->post('/karyawan/absensi/clock-in', ['lat' => 999, 'lng' => 'bukan-angka'])
            ->assertSessionHasErrors(['lat', 'lng']);
    }

    // ==================================================================
    // 3. LOVE / KLAIM TOLERANSI
    // ==================================================================

    public function test_klaim_toleransi_berhasil_dan_mengurangi_kuota(): void
    {
        $me = $this->karyawan();
        $this->setJamKerja('07:30', '16:00', 15, 4);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/love')
            ->post('/karyawan/love', [
                'jenis' => 'lupa_absen',
                'tgl' => $this->hariKerjaBulanIni(),
                'jam' => '07:35',
                'alasan' => 'Lupa tap kartu karena antre di gerbang',
                'approver_id' => $this->adminGowa()->id,
            ])
            ->assertRedirect('/karyawan/love')
            ->assertSessionHasNoErrors();

        $claim = ToleranceClaim::where('employee_id', $me->id)->firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->assertSame($me->region_id, $claim->region_id);
        $this->assertSame($me->site_id, $claim->site_id);
    }

    public function test_klaim_toleransi_ditolak_bila_approver_luar_wilayah(): void
    {
        $me = $this->karyawan(); // region 2

        $this->actingAs($me, 'employee')
            ->from('/karyawan/love')
            ->post('/karyawan/love', [
                'jenis' => 'lupa_absen',
                'tgl' => $this->hariKerjaBulanIni(),
                'jam' => '07:35',
                'alasan' => 'Mencoba approver wilayah lain',
                'approver_id' => $this->adminBone()->id, // region 4
            ])
            ->assertSessionHasErrors('approver_id');

        $this->assertSame(0, ToleranceClaim::where('employee_id', $me->id)->count());
    }

    public function test_klaim_toleransi_ditolak_pada_akhir_pekan(): void
    {
        $me = $this->karyawan();
        $sabtu = now('Asia/Makassar')->startOfMonth();
        while (! $sabtu->isSaturday()) {
            $sabtu->addDay();
        }
        // Pastikan Sabtu itu belum lewat; kalau sudah, lewati (butuh tgl <= hari ini).
        if ($sabtu->gt(now('Asia/Makassar'))) {
            $sabtu = now('Asia/Makassar')->copy();
            while (! $sabtu->isSaturday()) {
                $sabtu->subDay();
            }
        }

        $this->actingAs($me, 'employee')
            ->from('/karyawan/love')
            ->post('/karyawan/love', [
                'jenis' => 'lupa_pulang',
                'tgl' => $sabtu->toDateString(),
                'jam' => '16:05',
                'alasan' => 'Klaim di akhir pekan seharusnya ditolak',
                'approver_id' => $this->adminGowa()->id,
            ])
            ->assertSessionHasErrors('tgl');
    }

    public function test_klaim_toleransi_ditolak_saat_kuota_habis(): void
    {
        $me = $this->karyawan();
        $this->setJamKerja('07:30', '16:00', 15, 1); // kuota 1

        // Kuota terpakai oleh satu klaim pending.
        ToleranceClaim::create([
            'employee_id' => $me->id,
            'jenis' => 'lupa_absen',
            'claim_date' => $this->hariKerjaBulanIni(),
            'jam' => '07:35:00',
            'alasan' => 'Klaim pertama memakai kuota',
            'site_id' => $me->site_id,
            'region_id' => $me->region_id,
            'approver_id' => $this->adminGowa()->id,
            'status' => 'pending',
        ]);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/love')
            ->post('/karyawan/love', [
                'jenis' => 'lupa_absen',
                'tgl' => $this->hariKerjaBulanIni(),
                'jam' => '07:40',
                'alasan' => 'Klaim kedua harus ditolak karena kuota habis',
                'approver_id' => $this->adminGowa()->id,
            ])
            ->assertSessionHasErrors('love');

        $this->assertSame(1, ToleranceClaim::where('employee_id', $me->id)->count());
    }

    public function test_approve_dan_reject_klaim_toleransi_oleh_admin(): void
    {
        $me = $this->karyawan();
        $claim = ToleranceClaim::create([
            'employee_id' => $me->id,
            'jenis' => 'lupa_absen',
            'claim_date' => $this->hariKerjaBulanIni(),
            'jam' => '07:35:00',
            'alasan' => 'Untuk diuji approve',
            'site_id' => $me->site_id,
            'region_id' => $me->region_id,
            'approver_id' => $this->adminGowa()->id,
            'status' => 'pending',
        ]);

        $this->asAdmin($this->adminGowa())
            ->from('/admin/love')
            ->put("/admin/love/{$claim->id}/approve")
            ->assertRedirect('/admin/love');

        $this->assertSame('approved', $claim->fresh()->status);

        // Admin wilayah lain tidak boleh menyentuh klaim ini.
        $claim2 = ToleranceClaim::create([
            'employee_id' => $me->id,
            'jenis' => 'lupa_pulang',
            'claim_date' => $this->hariKerjaBulanIni(),
            'jam' => '16:05:00',
            'alasan' => 'Untuk diuji reject lintas wilayah',
            'site_id' => $me->site_id,
            'region_id' => $me->region_id,
            'approver_id' => $this->adminGowa()->id,
            'status' => 'pending',
        ]);

        $this->asAdmin($this->adminBone())
            ->put("/admin/love/{$claim2->id}/reject", ['note' => 'Coba tolak lintas wilayah'])
            ->assertForbidden();

        $this->assertSame('pending', $claim2->fresh()->status);
    }

    // ==================================================================
    // 4. CUTI
    // ==================================================================

    public function test_cuti_diajukan_dan_disetujui_setelah_tiga_level(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/cuti')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => now('Asia/Makassar')->addDays(3)->toDateString(),
                'selesai' => now('Asia/Makassar')->addDays(5)->toDateString(),
                'alasan' => 'Cuti tahunan untuk urusan keluarga',
            ])
            ->assertRedirect('/karyawan/cuti')
            ->assertSessionHasNoErrors();

        $cuti = Leave::where('employee_id', $me->id)->firstOrFail();
        $this->assertSame('Menunggu', $cuti->status);
        $this->assertSame(0, $cuti->level);

        $admin = $this->adminGowa();
        foreach ([1, 2, 3] as $level) {
            $this->asAdmin($admin)->put("/admin/cuti/{$cuti->id}/approve")->assertRedirect();
            $this->assertSame($level, $cuti->fresh()->level);
        }

        $this->assertSame('Disetujui', $cuti->fresh()->status);
    }

    public function test_cuti_validasi_tanggal_dan_alasan(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/cuti')
            ->post('/karyawan/cuti', [
                'jenis' => 'Tahunan',
                'mulai' => now('Asia/Makassar')->subDays(2)->toDateString(), // masa lalu
                'selesai' => now('Asia/Makassar')->subDays(1)->toDateString(),
                'alasan' => 'x', // terlalu pendek
            ])
            ->assertSessionHasErrors(['mulai', 'alasan']);

        $this->assertSame(0, Leave::count());
    }

    public function test_cuti_selesai_sebelum_mulai_ditolak(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/cuti')
            ->post('/karyawan/cuti', [
                'jenis' => 'Sakit',
                'mulai' => now('Asia/Makassar')->addDays(5)->toDateString(),
                'selesai' => now('Asia/Makassar')->addDays(2)->toDateString(),
                'alasan' => 'Rentang tanggal terbalik',
            ])
            ->assertSessionHasErrors('selesai');
    }

    public function test_karyawan_bisa_membatalkan_cuti_yang_belum_diproses(): void
    {
        $me = $this->karyawan();
        $cuti = Leave::create([
            'employee_id' => $me->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(3)->toDateString(),
            'selesai' => now()->addDays(4)->toDateString(),
            'alasan' => 'Untuk diuji pembatalan',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/cuti')
            ->delete("/karyawan/cuti/{$cuti->id}")
            ->assertRedirect('/karyawan/cuti');

        $this->assertDatabaseMissing('leaves', ['id' => $cuti->id]);
    }

    public function test_karyawan_tidak_bisa_membatalkan_cuti_yang_sudah_diproses(): void
    {
        $me = $this->karyawan();
        $cuti = Leave::create([
            'employee_id' => $me->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(3)->toDateString(),
            'selesai' => now()->addDays(4)->toDateString(),
            'alasan' => 'Sudah naik level',
            'status' => 'Menunggu',
            'level' => 1,
        ]);

        $this->actingAs($me, 'employee')
            ->delete("/karyawan/cuti/{$cuti->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('leaves', ['id' => $cuti->id]);
    }

    public function test_karyawan_tidak_bisa_membatalkan_cuti_milik_orang_lain(): void
    {
        $orangLain = $this->karyawanLain();
        $cuti = Leave::create([
            'employee_id' => $orangLain->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(3)->toDateString(),
            'selesai' => now()->addDays(4)->toDateString(),
            'alasan' => 'Milik karyawan lain',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $this->actingAs($this->karyawan(), 'employee')
            ->delete("/karyawan/cuti/{$cuti->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('leaves', ['id' => $cuti->id]);
    }

    public function test_admin_wilayah_lain_tidak_bisa_mengakses_cuti_lintas_wilayah(): void
    {
        $me = $this->karyawan(); // region 2
        $cuti = Leave::create([
            'employee_id' => $me->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(3)->toDateString(),
            'selesai' => now()->addDays(4)->toDateString(),
            'alasan' => 'Milik wilayah Gowa',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $bone = $this->adminBone(); // region 4
        $this->asAdmin($bone)->get("/admin/cuti/{$cuti->id}")->assertForbidden();
        $this->asAdmin($bone)->put("/admin/cuti/{$cuti->id}/approve")->assertForbidden();
        $this->asAdmin($bone)->delete("/admin/cuti/{$cuti->id}")->assertForbidden();

        $this->assertSame('Menunggu', $cuti->fresh()->status);
    }

    // ==================================================================
    // 5. DINAS (UNGGAH DOKUMEN + APPROVAL)
    // ==================================================================

    private function payloadDinas(): array
    {
        return [
            'nomor_surat' => 'SK/001/BBWS-PJ/2026',
            'tanggal_mulai' => now('Asia/Makassar')->addDays(2)->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->addDays(4)->toDateString(),
            'keterangan' => 'Menghadiri rapat koordinasi perencanaan bendungan',
            'tujuan' => 'Kota Makassar',
            'transportasi' => 'Darat',
            'pembebanan_anggaran' => 'Bidang Perencanaan 2026',
        ];
    }

    public function test_dinas_diajukan_dengan_dokumen_pdf(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()])
            ->assertRedirect('/karyawan/dinas')
            ->assertSessionHasNoErrors();

        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();
        $this->assertSame('Menunggu', $dinas->status);
        $this->assertSame('surat-tugas.pdf', $dinas->dokumen_nama);
        $this->assertSame('application/pdf', $dinas->dokumen_mime);
        $this->assertTrue($dinas->hasDokumen());
        Storage::disk(config('filesystems.uploads.private_disk'))->assertExists($dinas->dokumen_path);
    }

    public function test_dinas_menerima_dokumen_gambar(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payloadDinas() + [
                'dokumen' => UploadedFile::fake()->image('scan-surat.jpg', 800, 600),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(DinasClaim::where('employee_id', $me->id)->firstOrFail()->dokumenIsImage());
    }

    public function test_dinas_dokumen_wajib_diunggah(): void
    {
        $this->actingAs($this->karyawan(), 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payloadDinas())
            ->assertSessionHasErrors('dokumen');

        $this->assertSame(0, DinasClaim::count());
    }

    public function test_dinas_menolak_berkas_yang_disamarkan_sebagai_pdf(): void
    {
        $me = $this->karyawan();

        // Nama .pdf tetapi isinya bukan PDF → harus ditolak oleh aturan mimetypes.
        $jahat = UploadedFile::fake()->create('jahat.pdf', 50, 'application/x-php');

        $this->actingAs($me, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $jahat])
            ->assertSessionHasErrors('dokumen');

        $this->assertSame(0, DinasClaim::count());
    }

    public function test_dinas_menolak_dokumen_lebih_dari_5mb(): void
    {
        $this->actingAs($this->karyawan(), 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf('besar.pdf', 6000)])
            ->assertSessionHasErrors('dokumen');

        $this->assertSame(0, DinasClaim::count());
    }

    public function test_dinas_menolak_tanggal_selesai_sebelum_mulai(): void
    {
        $payload = $this->payloadDinas();
        $payload['tanggal_selesai'] = now('Asia/Makassar')->addDays(1)->toDateString();

        $this->actingAs($this->karyawan(), 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $payload + ['dokumen' => $this->pdf()])
            ->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_dinas_alur_approve_dan_reject(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();

        $this->asAdmin($this->adminGowa())
            ->put("/admin/dinas/{$dinas->id}/approve")
            ->assertRedirect();
        $this->assertSame('Disetujui', $dinas->fresh()->status);

        // Pengajuan kedua → ditolak dengan catatan.
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf('surat-2.pdf')]);
        $dinas2 = DinasClaim::where('employee_id', $me->id)->where('id', '!=', $dinas->id)->firstOrFail();

        $this->asAdmin($this->adminGowa())
            ->put("/admin/dinas/{$dinas2->id}/reject", ['note' => 'Anggaran belum tersedia'])
            ->assertRedirect();

        $this->assertSame('Ditolak', $dinas2->fresh()->status);
        $this->assertSame('Anggaran belum tersedia', $dinas2->fresh()->note);
    }

    public function test_dinas_reject_tanpa_catatan_ditolak(): void
    {
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();

        $this->asAdmin($this->adminGowa())
            ->put("/admin/dinas/{$dinas->id}/reject", [])
            ->assertSessionHasErrors('note');

        $this->assertSame('Menunggu', $dinas->fresh()->status);
    }

    public function test_dinas_unduh_dokumen_hanya_oleh_pemilik(): void
    {
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();

        $this->actingAs($me, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Karyawan lain tidak boleh mengunduh.
        $this->actingAs($this->karyawanLain(), 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertForbidden();
    }

    public function test_dinas_admin_di_luar_wilayah_tidak_bisa_unduh_dokumen(): void
    {
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();

        $this->asAdmin($this->adminGowa())->get("/admin/dinas/{$dinas->id}/dokumen")->assertOk();
        $this->asAdmin($this->adminBone())->get("/admin/dinas/{$dinas->id}/dokumen")->assertForbidden();
    }

    public function test_dinas_membatalkan_pengajuan_menghapus_dokumen(): void
    {
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();
        $path = $dinas->dokumen_path;

        Storage::disk(config('filesystems.uploads.private_disk'))->assertExists($path);

        $this->actingAs($me, 'employee')
            ->from('/karyawan/dinas')
            ->delete("/karyawan/dinas/{$dinas->id}")
            ->assertRedirect('/karyawan/dinas');

        $this->assertDatabaseMissing('dinas_claims', ['id' => $dinas->id]);
        Storage::disk(config('filesystems.uploads.private_disk'))->assertMissing($path);
    }

    public function test_dinas_yang_sudah_disetujui_tidak_bisa_dibatalkan_karyawan(): void
    {
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->post('/karyawan/dinas', $this->payloadDinas() + ['dokumen' => $this->pdf()]);
        $dinas = DinasClaim::where('employee_id', $me->id)->firstOrFail();

        $this->asAdmin($this->adminGowa())->put("/admin/dinas/{$dinas->id}/approve");

        $this->actingAs($me, 'employee')
            ->delete("/karyawan/dinas/{$dinas->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('dinas_claims', ['id' => $dinas->id]);
    }

    // ==================================================================
    // 6. PENGUMUMAN
    // ==================================================================

    public function test_pengumuman_visibilitas_dan_penandaan_dibaca(): void
    {
        $admin = $this->adminGowa();
        $global = Announcement::create([
            'judul' => 'Libur Nasional',
            'konten' => 'Kantor tutup pada tanggal tersebut.',
            'scope' => 'Global',
            'region_id' => null,
            'pin' => true,
            'created_by' => $admin->id,
        ]);
        $wilayahGowa = Announcement::create([
            'judul' => 'Rapat Wilayah Gowa',
            'konten' => 'Rapat koordinasi wilayah Gowa.',
            'scope' => 'Wilayah',
            'region_id' => 2,
            'pin' => false,
            'created_by' => $admin->id,
        ]);
        $wilayahBone = Announcement::create([
            'judul' => 'Rapat Wilayah Bone',
            'konten' => 'Rapat koordinasi wilayah Bone.',
            'scope' => 'Wilayah',
            'region_id' => 4,
            'pin' => false,
            'created_by' => $admin->id,
        ]);

        // Karyawan Gowa (region 2) melihat global + Gowa, bukan Bone.
        $me = $this->karyawan();
        $this->actingAs($me, 'employee')
            ->get('/karyawan/pengumuman')
            ->assertOk()
            ->assertInertia(function ($page) use ($global, $wilayahGowa, $wilayahBone) {
                $ids = collect($page->toArray()['props']['list'])->pluck('id')->all();
                $this->assertContains($global->id, $ids);
                $this->assertContains($wilayahGowa->id, $ids);
                $this->assertNotContains($wilayahBone->id, $ids);
            });

        $this->actingAs($me, 'employee')
            ->from('/karyawan/pengumuman')
            ->post("/karyawan/pengumuman/{$global->id}/read")
            ->assertRedirect('/karyawan/pengumuman');

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $global->id,
            'employee_id' => $me->id,
        ]);

        $this->actingAs($me, 'employee')->post('/karyawan/pengumuman/read-all')->assertRedirect();
        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $wilayahGowa->id,
            'employee_id' => $me->id,
        ]);
        $this->assertDatabaseMissing('announcement_reads', [
            'announcement_id' => $wilayahBone->id,
            'employee_id' => $me->id,
        ]);
    }

    public function test_admin_membuat_mengubah_dan_menghapus_pengumuman(): void
    {
        $admin = $this->adminGowa();

        $this->asAdmin($admin)
            ->from('/admin/pengumuman')
            ->post('/admin/pengumuman', [
                'judul' => 'Pengumuman Baru',
                'konten' => 'Isi pengumuman baru untuk diuji.',
                'scope' => 'Wilayah',
                'region_id' => 2,
                'pin' => false,
            ])
            ->assertRedirect('/admin/pengumuman')
            ->assertSessionHasNoErrors();

        $p = Announcement::where('judul', 'Pengumuman Baru')->firstOrFail();

        $this->asAdmin($admin)
            ->put("/admin/pengumuman/{$p->id}", [
                'judul' => 'Pengumuman Diubah',
                'konten' => 'Isi sudah diubah untuk pengujian.',
                'scope' => 'Wilayah',
                'region_id' => 2,
                'pin' => true,
            ])
            ->assertRedirect();
        $this->assertSame('Pengumuman Diubah', $p->fresh()->judul);
        $this->assertTrue((bool) $p->fresh()->pin);

        $this->asAdmin($admin)->delete("/admin/pengumuman/{$p->id}")->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $p->id]);
    }

    public function test_admin_wilayah_lain_tidak_bisa_mengubah_pengumuman(): void
    {
        $p = Announcement::create([
            'judul' => 'Milik Gowa',
            'konten' => 'Pengumuman wilayah Gowa.',
            'scope' => 'Wilayah',
            'region_id' => 2,
            'pin' => false,
            'created_by' => $this->adminGowa()->id,
        ]);

        $this->asAdmin($this->adminBone())
            ->put("/admin/pengumuman/{$p->id}", [
                'judul' => 'Direbut',
                'konten' => 'Percobaan ubah lintas wilayah.',
                'scope' => 'Wilayah',
                'region_id' => 4,
                'pin' => false,
            ])
            ->assertForbidden();

        $this->assertSame('Milik Gowa', $p->fresh()->judul);
    }

    // ==================================================================
    // 7. PROFIL KARYAWAN
    // ==================================================================

    public function test_profil_memperbarui_data_diri(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil', ['phone' => '081234567890', 'email' => 'andi.baru@bbws-pj.go.id'])
            ->assertRedirect('/karyawan/profil')
            ->assertSessionHasNoErrors();

        $me->refresh();
        $this->assertSame('081234567890', $me->phone);
        $this->assertSame('andi.baru@bbws-pj.go.id', $me->email);
    }

    public function test_profil_menolak_email_tidak_valid(): void
    {
        $this->actingAs($this->karyawan(), 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil', ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_profil_unggah_dan_hapus_foto(): void
    {
        $me = $this->karyawan();
        $disk = config('filesystems.uploads.public_disk');

        $this->actingAs($me, 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil', ['foto' => UploadedFile::fake()->image('foto.jpg', 200, 200)])
            ->assertRedirect('/karyawan/profil')
            ->assertSessionHasNoErrors();

        $url = $me->fresh()->foto_url;
        $this->assertNotEmpty($url, 'foto_url harus terisi setelah unggah.');

        $this->actingAs($me, 'employee')
            ->from('/karyawan/profil')
            ->delete('/karyawan/profil/foto')
            ->assertRedirect('/karyawan/profil');

        $this->assertNull($me->fresh()->foto_url);
    }

    public function test_profil_ganti_password_dengan_password_lemah_ditolak(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil/password', [
                'current_password' => 'password123',
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_profil_ganti_password_dengan_password_kuat_berhasil(): void
    {
        $me = $this->karyawan();

        $this->actingAs($me, 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil/password', [
                'current_password' => 'password123',
                'password' => 'KuatSekali123',
                'password_confirmation' => 'KuatSekali123',
            ])
            ->assertRedirect('/karyawan/profil')
            ->assertSessionHasNoErrors();

        // Password baru harus benar-benar bisa dipakai login.
        Auth::guard('employee')->logout();
        $this->post('/karyawan/login', ['login' => '7371001234567890', 'password' => 'KuatSekali123'])
            ->assertRedirect('/karyawan');
    }

    public function test_profil_ganti_password_dengan_password_lama_salah_ditolak(): void
    {
        $this->actingAs($this->karyawan(), 'employee')
            ->from('/karyawan/profil')
            ->put('/karyawan/profil/password', [
                'current_password' => 'salah-total',
                'password' => 'KuatSekali123',
                'password_confirmation' => 'KuatSekali123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    // ==================================================================
    // 8. CRUD MASTER DATA ADMIN
    // ==================================================================

    public function test_admin_menambah_mengubah_menghapus_karyawan(): void
    {
        $admin = $this->superAdmin();
        $region = Region::where('name', 'Kab. Gowa')->firstOrFail();
        $site = $region->sites()->orderBy('id')->firstOrFail();

        $this->asAdmin($admin)
            ->from('/super-admin/employees')
            ->post('/super-admin/employees', [
                'nik' => '7371000000000777',
                'nip' => '',
                'nama' => 'Karyawan Simulasi',
                'email' => 'simulasi@bbws-pj.go.id',
                'gol' => 'III/a',
                'jabatan' => 'Staff Uji',
                'unit' => 'Bidang Uji',
                'status' => 'PNS',
                'region' => $region->name,
                'office_location_id' => $site->id,
            ])
            ->assertRedirect('/super-admin/employees')
            ->assertSessionHasNoErrors();

        $baru = Employee::where('nik', '7371000000000777')->firstOrFail();
        $this->assertSame($region->id, $baru->region_id);
        $this->assertSame($site->id, $baru->site_id);

        $this->asAdmin($admin)
            ->put("/super-admin/employees/{$baru->id}", [
                'nik' => '7371000000000777',
                'nip' => '',
                'nama' => 'Karyawan Simulasi Diubah',
                'email' => 'simulasi@bbws-pj.go.id',
                'gol' => 'III/b',
                'jabatan' => 'Staff Uji Senior',
                'unit' => 'Bidang Uji',
                'status' => 'PNS',
                'region' => $region->name,
                'office_location_id' => $site->id,
            ])
            ->assertRedirect();
        $this->assertSame('Karyawan Simulasi Diubah', $baru->fresh()->name);

        $this->asAdmin($admin)->delete("/super-admin/employees/{$baru->id}")->assertRedirect();
        $this->assertDatabaseMissing('employees', ['id' => $baru->id]);
    }

    public function test_admin_menolak_nik_duplikat_dan_nik_bukan_16_digit(): void
    {
        $region = Region::where('name', 'Kab. Gowa')->firstOrFail();
        $site = $region->sites()->orderBy('id')->firstOrFail();
        $base = [
            'nip' => '', 'nama' => 'Uji Duplikat', 'email' => 'dup@bbws-pj.go.id',
            'gol' => 'III/a', 'jabatan' => 'Staff', 'unit' => 'Bidang', 'status' => 'PNS',
            'region' => $region->name, 'office_location_id' => $site->id,
        ];

        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees', $base + ['nik' => '123']) // bukan 16 digit
            ->assertSessionHasErrors('nik');

        // NIK milik karyawan yang sudah ada.
        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees', $base + ['nik' => '7371001234567890'])
            ->assertSessionHasErrors('nik');
    }

    public function test_admin_mengelola_titik_kantor(): void
    {
        $admin = $this->adminGowa(); // region 2
        $region = Region::findOrFail(2);

        $response = $this->asAdmin($admin)
            ->from('/admin/regions')
            ->post("/admin/regions/{$region->id}/sites", [
                'nama_lokasi' => 'Pos Simulasi',
                'lat' => -5.2,
                'lng' => 119.5,
                'radius' => 150,
                'address' => 'Jalan Simulasi',
            ]);
        $response->assertSessionHasNoErrors();

        $site = Site::where('nama_lokasi', 'Pos Simulasi')->firstOrFail();
        $this->assertSame($region->id, $site->region_id);

        // Titik baru diarahkan ke halaman detailnya sendiri.
        $response->assertRedirect("/admin/regions/{$region->id}/sites/{$site->id}");

        $this->asAdmin($admin)
            ->put("/admin/sites/{$site->id}", [
                'nama_lokasi' => 'Pos Simulasi Diubah',
                'lat' => -5.3,
                'lng' => 119.6,
                'radius' => 250,
                'address' => 'Jalan Simulasi Baru',
            ])
            ->assertRedirect();
        $this->assertSame('Pos Simulasi Diubah', $site->fresh()->nama_lokasi);
        $this->assertSame(250, (int) $site->fresh()->radius_m);

        $this->asAdmin($admin)->delete("/admin/sites/{$site->id}")->assertRedirect();
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_admin_menolak_radius_di_luar_batas(): void
    {
        $region = Region::findOrFail(2);

        $this->asAdmin($this->adminGowa())
            ->from('/admin/regions')
            ->post("/admin/regions/{$region->id}/sites", [
                'nama_lokasi' => 'Radius Salah',
                'lat' => -5.2, 'lng' => 119.5,
                'radius' => 5000, // maksimal 1000
            ])
            ->assertSessionHasErrors('radius');
    }

    public function test_admin_wilayah_lain_tidak_bisa_membuat_titik_di_wilayah_orang(): void
    {
        $region = Region::findOrFail(2); // Gowa, sedangkan aktor Bone

        $this->asAdmin($this->adminBone())
            ->from('/admin/regions')
            ->post("/admin/regions/{$region->id}/sites", [
                'nama_lokasi' => 'Titik Selundupan',
                'lat' => -5.2, 'lng' => 119.5, 'radius' => 150,
            ])
            ->assertForbidden();

        $this->assertSame(0, Site::where('nama_lokasi', 'Titik Selundupan')->count());
    }

    public function test_admin_menugaskan_dan_memindahkan_karyawan_antar_titik(): void
    {
        $admin = $this->adminGowa();
        $me = $this->karyawan(); // region 2
        $sites = Region::findOrFail(2)->sites()->orderBy('id')->get();
        $lain = $sites->firstWhere('id', '!=', $me->site_id);

        $this->asAdmin($admin)
            ->from('/admin/regions')
            ->put("/admin/sites/{$lain->id}/move", ['employee_id' => $me->id])
            ->assertRedirect();

        $this->assertSame($lain->id, $me->fresh()->site_id);
    }

    public function test_admin_menolak_menugaskan_karyawan_lintas_wilayah(): void
    {
        $siteGowa = Region::findOrFail(2)->sites()->orderBy('id')->firstOrFail();
        $dewi = $this->karyawanLain(); // region 3

        $this->asAdmin($this->adminGowa())
            ->from('/admin/regions')
            ->post("/admin/sites/{$siteGowa->id}/employees", ['employee_ids' => [$dewi->id]])
            ->assertSessionHasErrors('employee_ids');
    }

    public function test_super_admin_mengelola_wilayah(): void
    {
        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/regions')
            ->post('/super-admin/regions', [
                'name' => 'Kab. Simulasi',
                'kantor' => 'Kantor Simulasi',
                'tipe' => 'cabang',
                'address' => 'Jalan Simulasi',
            ])
            ->assertRedirect('/super-admin/regions')
            ->assertSessionHasNoErrors();

        $region = Region::where('name', 'Kab. Simulasi')->firstOrFail();
        $this->assertSame('kab-simulasi', $region->slug);

        $this->asAdmin($this->superAdmin())
            ->put("/super-admin/regions/{$region->id}", [
                'name' => 'Kab. Simulasi Baru',
                'kantor' => 'Kantor Simulasi Baru',
                'tipe' => 'cabang',
            ])
            ->assertRedirect();
        $this->assertSame('Kab. Simulasi Baru', $region->fresh()->name);

        $this->asAdmin($this->superAdmin())->delete("/super-admin/regions/{$region->id}")->assertRedirect();
        $this->assertDatabaseMissing('regions', ['id' => $region->id]);
    }

    public function test_admin_wilayah_tidak_boleh_menambah_wilayah(): void
    {
        $this->asAdmin($this->adminGowa())
            ->from('/admin/regions')
            ->post('/admin/regions', ['name' => 'Wilayah Selundupan', 'kantor' => 'X', 'tipe' => 'cabang'])
            ->assertForbidden();

        $this->assertSame(0, Region::where('name', 'Wilayah Selundupan')->count());
    }

    public function test_super_admin_mengelola_hari_libur(): void
    {
        $tanggal = now('Asia/Makassar')->addMonths(3)->startOfMonth()->toDateString();

        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/holidays')
            ->post('/super-admin/holidays', ['tanggal' => $tanggal, 'nama' => 'Libur Simulasi', 'cuti_bersama' => false])
            ->assertRedirect('/super-admin/holidays')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('holidays', ['nama' => 'Libur Simulasi']);

        // Tanggal yang sama tidak boleh didaftarkan dua kali.
        // Rule::unique pada kolom `date` hanya akurat di MySQL: SQLite menyimpan
        // date sebagai "Y-m-d 00:00:00" sehingga duplikat lolos validasi dan baru
        // ditolak unique index di level DB (QueryException, bukan 422).
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Uji duplikat tanggal libur hanya berlaku di MySQL.');
        }

        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/holidays')
            ->post('/super-admin/holidays', ['tanggal' => $tanggal, 'nama' => 'Duplikat'])
            ->assertSessionHasErrors('tanggal');
    }

    public function test_admin_wilayah_tidak_boleh_mengubah_hari_libur(): void
    {
        $this->asAdmin($this->adminGowa())
            ->get('/admin/holidays')
            ->assertForbidden();
    }

    public function test_super_admin_memperbarui_pengaturan_absensi(): void
    {
        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/settings')
            ->put('/super-admin/settings', [
                'jamMasuk' => '08:00',
                'jamPulang' => '17:00',
                'toleransi' => 20,
                'loveMax' => 5,
            ])
            ->assertRedirect('/super-admin/settings')
            ->assertSessionHasNoErrors();

        $s = AttendanceSetting::firstOrFail();
        $this->assertSame('08:00', substr((string) $s->jam_masuk, 0, 5));
        $this->assertSame(20, (int) $s->toleransi_late_menit);
        $this->assertSame(5, (int) $s->love_max);
    }

    public function test_pengaturan_menolak_jam_masuk_setelah_jam_pulang(): void
    {
        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/settings')
            ->put('/super-admin/settings', [
                'jamMasuk' => '17:00',
                'jamPulang' => '08:00',
                'toleransi' => 15,
                'loveMax' => 4,
            ])
            ->assertSessionHasErrors('jamMasuk');
    }

    public function test_super_admin_mengelola_akun_admin_wilayah(): void
    {
        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/admin-wilayah')
            ->post('/super-admin/admin-wilayah', [
                'nama' => 'Admin Simulasi',
                'email' => 'admin.simulasi@bbws-pj.go.id',
                'region' => 'Kab. Gowa',
                'password' => 'RahasiaKuat123',
            ])
            ->assertRedirect('/super-admin/admin-wilayah')
            ->assertSessionHasNoErrors();

        $baru = User::where('email', 'admin.simulasi@bbws-pj.go.id')->firstOrFail();
        $this->assertSame('admin_wilayah', $baru->role);
        $this->assertSame(2, $baru->region_id);

        // Nonaktifkan → tidak boleh bisa login lagi.
        $this->asAdmin($this->superAdmin())
            ->put("/super-admin/admin-wilayah/{$baru->id}/toggle", ['is_active' => false])
            ->assertRedirect();
        $this->assertFalse((bool) $baru->fresh()->is_active);

        $this->post('/admin/login', ['email' => 'admin.simulasi@bbws-pj.go.id', 'password' => 'RahasiaKuat123'])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_menghapus_absensi_dan_melihat_rekap(): void
    {
        $me = $this->karyawan();
        $att = Attendance::create([
            'employee_id' => $me->id,
            'work_date' => now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:00:00',
            'status' => 'late',
            'lat_in' => $me->site->lat,
            'lng_in' => $me->site->lng,
            'distance_in_m' => 0,
            'site_id' => $me->site_id,
            'region_id' => $me->region_id,
        ]);

        $this->asAdmin($this->superAdmin())->get('/super-admin/attendances')->assertOk();
        $this->asAdmin($this->superAdmin())
            ->delete("/super-admin/attendances/{$att->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('attendances', ['id' => $att->id]);

        $this->asAdmin($this->superAdmin())
            ->get("/super-admin/employees/{$me->id}/rekap?bulan=".now('Asia/Makassar')->format('Y-m'))
            ->assertOk();
    }

    public function test_rekap_karyawan_menolak_format_bulan_salah(): void
    {
        $this->actingAs($this->karyawan(), 'employee')
            ->get('/karyawan/rekap/detail?bulan=2026-9')
            ->assertSessionHasErrors('bulan');
    }

    public function test_audit_log_tercatat_dan_hanya_super_admin(): void
    {
        $this->asAdmin($this->superAdmin())->get('/super-admin/audit-log')->assertOk();
        $this->asAdmin($this->adminGowa())->get('/admin/audit-log')->assertForbidden();

        // Aksi approve cuti harus tercatat.
        $me = $this->karyawan();
        $cuti = Leave::create([
            'employee_id' => $me->id,
            'jenis' => 'Tahunan',
            'mulai' => now()->addDays(3)->toDateString(),
            'selesai' => now()->addDays(4)->toDateString(),
            'alasan' => 'Untuk menguji audit log',
            'status' => 'Menunggu',
            'level' => 0,
        ]);

        $this->asAdmin($this->adminGowa())->put("/admin/cuti/{$cuti->id}/approve");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cuti.approve_level',
            'subject_type' => 'Leave',
            'subject_id' => $cuti->id,
        ]);
    }

    // ==================================================================
    // 9. UJI KEAMANAN — IDOR & BATAS WILAYAH
    // ==================================================================

    public function test_keamanan_admin_wilayah_hanya_melihat_karyawan_wilayahnya(): void
    {
        $this->asAdmin($this->adminGowa())
            ->get('/admin/employees')
            ->assertOk()
            ->assertInertia(function ($page) {
                $regionIds = collect($page->toArray()['props']['employees'])
                    ->pluck('regionId')->unique()->values()->all();
                $this->assertSame([2], $regionIds, 'Admin Gowa hanya boleh melihat karyawan region 2.');
            });
    }

    public function test_keamanan_admin_wilayah_tidak_bisa_mengubah_karyawan_lintas_wilayah(): void
    {
        $dewi = $this->karyawanLain(); // region 3

        $this->asAdmin($this->adminGowa())
            ->put("/admin/employees/{$dewi->id}", [
                'nik' => $dewi->nik,
                'nip' => '',
                'nama' => 'Direbut',
                'email' => $dewi->email,
                'gol' => 'III/a',
                'jabatan' => 'X',
                'unit' => 'X',
                'status' => 'PNS',
                'region' => 'Kab. Maros',
                'office_location_id' => $dewi->site_id,
            ])
            ->assertForbidden();

        $this->assertNotSame('Direbut', $dewi->fresh()->name);
    }

    public function test_keamanan_admin_wilayah_tidak_bisa_menghapus_karyawan_lintas_wilayah(): void
    {
        $dewi = $this->karyawanLain();

        $this->asAdmin($this->adminGowa())
            ->delete("/admin/employees/{$dewi->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('employees', ['id' => $dewi->id]);
    }

    public function test_keamanan_halaman_detail_titik_tidak_membocorkan_data_lintas_wilayah(): void
    {
        $siteGowa = Region::findOrFail(2)->sites()->orderBy('id')->firstOrFail();

        // Wilayah lain: ditolak — konsisten dengan update/destroy/assign/move
        // yang sejak awal sudah memakai abort_if cakupan.
        $this->asAdmin($this->adminBone())
            ->get("/admin/regions/2/sites/{$siteGowa->id}")
            ->assertForbidden();

        // Wilayah sendiri: halaman boleh dibuka, dan HANYA data wilayah itu yang dikirim.
        $response = $this->asAdmin($this->adminGowa())
            ->get("/admin/regions/2/sites/{$siteGowa->id}");

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $regionIds = collect($props['regions'])->pluck('id')->unique()->values()->all();
        $employeeRegionIds = collect($props['employees'])->pluck('regionId')->unique()->values()->all();

        $this->assertSame([2], $regionIds, 'Detail titik hanya boleh mengirim wilayah milik aktor.');
        $this->assertSame([2], $employeeRegionIds, 'Detail titik hanya boleh mengirim karyawan wilayah aktor.');
    }

    public function test_keamanan_admin_tidak_bisa_memindahkan_karyawan_ke_titik_lintas_wilayah(): void
    {
        $dewi = $this->karyawanLain(); // region 3
        $siteGowa = Region::findOrFail(2)->sites()->orderBy('id')->firstOrFail();

        $this->asAdmin($this->adminGowa())
            ->from('/admin/regions')
            ->put("/admin/sites/{$siteGowa->id}/move", ['employee_id' => $dewi->id])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(3, $dewi->fresh()->region_id);
    }

    /** @group regresi-keamanan */
    public function test_keamanan_ubah_karyawan_tidak_boleh_menugaskan_ke_titik_wilayah_lain(): void
    {
        $me = $this->karyawan(); // region 2, site 201
        $siteMaros = Region::findOrFail(3)->sites()->orderBy('id')->firstOrFail();

        $this->assertSame(201, (int) $me->site_id, 'Prasyarat: karyawan ada di titik 201.');

        // Region tetap Gowa, tetapi titiknya milik Maros → harus ditolak.
        $this->asAdmin($this->adminGowa())
            ->from('/admin/employees')
            ->put("/admin/employees/{$me->id}", [
                'nik' => $me->nik,
                'nip' => $me->nip ?? '',
                'nama' => $me->name,
                'email' => $me->email,
                'gol' => 'III/a',
                'jabatan' => $me->jabatan,
                'unit' => $me->unit_kerja,
                'status' => $me->status_kepegawaian,
                'region' => 'Kab. Gowa',
                'office_location_id' => $siteMaros->id,
            ]);

        $this->assertSame(201, (int) $me->fresh()->site_id,
            'Titik tidak boleh berpindah ke wilayah lain.');
    }

    /** @group regresi-keamanan */
    public function test_keamanan_karyawan_tidak_bisa_menandai_pengumuman_wilayah_lain(): void
    {
        $bone = Announcement::create([
            'judul' => 'Rahasia Wilayah Bone',
            'konten' => 'Pengumuman internal wilayah Bone.',
            'scope' => 'Wilayah',
            'region_id' => 4,
            'pin' => false,
            'created_by' => $this->adminBone()->id,
        ]);

        $me = $this->karyawan(); // region 2

        $this->actingAs($me, 'employee')
            ->post("/karyawan/pengumuman/{$bone->id}/read");

        $this->assertDatabaseMissing('announcement_reads', [
            'announcement_id' => $bone->id,
            'employee_id' => $me->id,
        ]);
    }

    /** @group regresi-keamanan */
    public function test_keamanan_admin_dengan_region_null_tidak_boleh_melihat_semua_data(): void
    {
        // Akun admin_wilayah tanpa region_id (kolomnya nullable).
        $adminTanpaWilayah = User::create([
            'name' => 'Admin Tanpa Wilayah',
            'email' => 'admin.null@bbws-pj.go.id',
            'password' => 'RahasiaKuat123',
            'role' => 'admin_wilayah',
            'region_id' => null,
            'site_id' => null,
            'is_active' => true,
        ]);

        // Akun admin_wilayah tanpa region_id tidak punya cakupan apa pun. Sebelum
        // diperbaiki, `$scope` menjadi null dan seluruh filter wilayah mati
        // sehingga akun ini melihat data semua wilayah.
        $this->asAdmin($adminTanpaWilayah)
            ->get('/admin/employees')
            ->assertForbidden();

        // Dan tidak ada satu pun karyawan yang bocor lewat jalur lain.
        $this->asAdmin($adminTanpaWilayah)->get('/admin/regions')->assertForbidden();
        $this->asAdmin($adminTanpaWilayah)->get('/admin/dinas')->assertForbidden();
    }

    /** @group regresi-keamanan */
    public function test_keamanan_menghapus_wilayah_berisi_karyawan_tidak_boleh_menghapus_karyawan(): void
    {
        $region = Region::findOrFail(2);
        $jumlahKaryawan = Employee::where('region_id', $region->id)->count();
        $this->assertGreaterThan(0, $jumlahKaryawan, 'Prasyarat: wilayah 2 harus berisi karyawan.');

        $this->asAdmin($this->superAdmin())
            ->from('/super-admin/regions')
            ->delete("/super-admin/regions/{$region->id}");

        $sisa = Employee::where('region_id', $region->id)->count();

        $this->assertSame($jumlahKaryawan, $sisa,
            'Menghapus wilayah tidak boleh ikut menghapus karyawannya (butuh pengaman).');
    }

    /** @group regresi-keamanan */
    public function test_keamanan_admin_nonaktif_kehilangan_akses_sesi(): void
    {
        $admin = $this->adminGowa();
        $this->asAdmin($admin)->get('/admin')->assertOk();

        $admin->update(['is_active' => false]);

        $this->asAdmin($admin->fresh())
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }
}
