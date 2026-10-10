<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Swafoto absensi harus privat: tersimpan di disk privat dan hanya keluar
 * lewat route ber-otorisasi — bukan URL publik yang bisa dibuka siapa saja.
 */
class AttendanceSelfieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    private function empMaros(): Employee
    {
        return Employee::where('region_id', 3)->firstOrFail();
    }

    /** Buat baris absensi dengan swafoto tersimpan di disk privat. */
    private function absensiDenganSelfie(Employee $emp): Attendance
    {
        $path = "selfie/{$emp->id}/uji.jpg";
        Storage::disk(config('filesystems.uploads.private_disk'))
            ->put($path, 'ISI-GAMBAR');

        return Attendance::create([
            'employee_id' => $emp->id,
            'work_date' => Carbon::now('Asia/Makassar')->toDateString(),
            'clock_in_at' => '07:35:00',
            'status' => 'on_time',
            'lat_in' => 0,
            'lng_in' => 0,
            'distance_in_m' => 10,
            'selfie_url' => $path,
            'site_id' => $emp->site_id,
            'region_id' => $emp->region_id,
        ]);
    }

    public function test_clock_in_menyimpan_swafoto_di_disk_privat_bukan_url_publik(): void
    {
        $me = $this->empGowa();
        $site = $me->site;

        $this->actingAs($me, 'employee')
            ->post('/karyawan/absensi/clock-in', [
                'lat' => $site->lat,
                'lng' => $site->lng,
                'selfie' => UploadedFile::fake()->image('selfie.jpg', 120, 120),
            ])
            ->assertSessionHasNoErrors();

        $row = Attendance::where('employee_id', $me->id)->firstOrFail();

        $this->assertStringNotContainsString('http', (string) $row->selfie_url, 'selfie_url harus berisi path, bukan URL publik.');
        Storage::disk(config('filesystems.uploads.private_disk'))->assertExists($row->selfie_url);

        // Disk publik dan privat boleh menunjuk disk yang sama (mis. keduanya
        // `s3`), jadi pemeriksaan "tidak ada di disk publik" hanya bermakna
        // kalau keduanya memang disk berbeda.
        $publik = config('filesystems.uploads.public_disk');
        if ($publik !== config('filesystems.uploads.private_disk')) {
            Storage::disk($publik)->assertMissing($row->selfie_url);
        }
    }

    public function test_karyawan_bisa_melihat_swafotonya_sendiri(): void
    {
        $me = $this->empGowa();
        $row = $this->absensiDenganSelfie($me);

        $this->actingAs($me, 'employee')
            ->get("/absensi/{$row->id}/selfie/masuk")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_karyawan_tidak_bisa_melihat_swafoto_karyawan_lain(): void
    {
        $row = $this->absensiDenganSelfie($this->empMaros());

        $this->actingAs($this->empGowa(), 'employee')
            ->get("/absensi/{$row->id}/selfie/masuk")
            ->assertForbidden();
    }

    public function test_admin_wilayah_hanya_bisa_melihat_swafoto_wilayahnya(): void
    {
        $gowa = $this->absensiDenganSelfie($this->empGowa());
        $maros = $this->absensiDenganSelfie($this->empMaros());

        $this->actingAs($this->adminGowa())
            ->get("/absensi/{$gowa->id}/selfie/masuk")
            ->assertOk();

        $this->actingAs($this->adminGowa())
            ->get("/absensi/{$maros->id}/selfie/masuk")
            ->assertForbidden();
    }

    public function test_super_admin_bisa_melihat_swafoto_semua_wilayah(): void
    {
        $maros = $this->absensiDenganSelfie($this->empMaros());

        $this->actingAs($this->superAdmin())
            ->get("/absensi/{$maros->id}/selfie/masuk")
            ->assertOk();
    }

    public function test_tamu_tidak_bisa_mengakses_swafoto(): void
    {
        $row = $this->absensiDenganSelfie($this->empGowa());

        $this->get("/absensi/{$row->id}/selfie/masuk")->assertForbidden();
    }

    public function test_swafoto_hilang_mengembalikan_404(): void
    {
        $me = $this->empGowa();
        $row = $this->absensiDenganSelfie($me);

        Storage::disk(config('filesystems.uploads.private_disk'))->delete($row->selfie_url);

        $this->actingAs($me, 'employee')
            ->get("/absensi/{$row->id}/selfie/masuk")
            ->assertNotFound();
    }

    public function test_jenis_swafoto_tidak_dikenal_mengembalikan_404(): void
    {
        $me = $this->empGowa();
        $row = $this->absensiDenganSelfie($me);

        $this->actingAs($me, 'employee')
            ->get("/absensi/{$row->id}/selfie/samping")
            ->assertNotFound();
    }

    public function test_menghapus_baris_absensi_ikut_menghapus_swafotonya(): void
    {
        $me = $this->empGowa();
        $row = $this->absensiDenganSelfie($me);
        $path = $row->selfie_url;

        Storage::disk(config('filesystems.uploads.private_disk'))->assertExists($path);

        $row->delete();

        Storage::disk(config('filesystems.uploads.private_disk'))->assertMissing($path);
    }
}
