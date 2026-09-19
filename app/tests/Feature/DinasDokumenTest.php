<?php

namespace Tests\Feature;

use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DinasDokumenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    private function empMaros(): Employee
    {
        return Employee::where('region_id', 3)->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    /**
     * Berkas PDF asli (diawali magic bytes %PDF) supaya lolos validasi
     * mimetypes yang membaca isi berkas, bukan sekadar ekstensi.
     */
    private function pdfFile(string $name = 'surat-tugas.pdf', int $bytes = 2048): UploadedFile
    {
        $body = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        if ($bytes > strlen($body)) {
            $body .= str_repeat(' ', $bytes - strlen($body));
        }

        $path = tempnam(sys_get_temp_dir(), 'dinas-pdf-');
        file_put_contents($path, $body);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nomor_surat' => '2485/SPT/0627/2026',
            'tanggal_mulai' => now('Asia/Makassar')->addDays(2)->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->addDays(4)->toDateString(),
            'keterangan' => 'Monitoring pelaksanaan kegiatan',
            'tujuan' => 'Bone',
            'transportasi' => 'mobil',
            'pembebanan_anggaran' => 'Kendaraan Sewa',
        ], $overrides);
    }

    /** Kirim pengajuan dinas sebagai karyawan tertentu. */
    private function ajukan(Employee $emp, array $overrides = []): DinasClaim
    {
        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return DinasClaim::where('employee_id', $emp->id)->latest('id')->firstOrFail();
    }

    public function test_pengajuan_dinas_dengan_dokumen_pdf_tersimpan(): void
    {
        $emp = $this->empGowa();

        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);

        $this->assertSame('Menunggu', $dinas->status);
        $this->assertSame('surat-tugas.pdf', $dinas->dokumen_nama);
        $this->assertSame('application/pdf', $dinas->dokumen_mime);
        $this->assertGreaterThan(0, $dinas->dokumen_size);
        $this->assertTrue($dinas->hasDokumen());
        $this->assertFalse($dinas->dokumenIsImage());
        $this->assertStringStartsWith('dinas/', $dinas->dokumen_path);

        Storage::disk('local')->assertExists($dinas->dokumen_path);
    }

    public function test_pengajuan_dinas_dengan_dokumen_gambar_tersimpan(): void
    {
        $emp = $this->empGowa();

        $dinas = $this->ajukan($emp, [
            'dokumen' => UploadedFile::fake()->image('surat-scan.jpg', 80, 60),
        ]);

        $this->assertSame('surat-scan.jpg', $dinas->dokumen_nama);
        $this->assertSame('image/jpeg', $dinas->dokumen_mime);
        $this->assertTrue($dinas->dokumenIsImage());
        Storage::disk('local')->assertExists($dinas->dokumen_path);
    }

    public function test_pengajuan_dinas_tanpa_dokumen_ditolak(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload())
            ->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('dinas_claims', 0);
    }

    public function test_dokumen_berformat_tidak_diizinkan_ditolak(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload([
                'dokumen' => UploadedFile::fake()->create('catatan.txt', 10, 'text/plain'),
            ]))
            ->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('dinas_claims', 0);
    }

    public function test_berkas_php_yang_disamarkan_sebagai_pdf_ditolak(): void
    {
        $emp = $this->empGowa();

        $path = tempnam(sys_get_temp_dir(), 'dinas-jahat-');
        file_put_contents($path, '<?php echo "pwned"; ?>');

        // Klien mengaku application/pdf (lolos rule mimes), tapi isi berkas
        // terdeteksi sebagai PHP → rule mimetypes harus menolaknya.
        $palsu = new UploadedFile($path, 'surat.pdf', 'application/pdf', null, true);

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload(['dokumen' => $palsu]))
            ->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('dinas_claims', 0);
    }

    public function test_dokumen_lebih_dari_lima_mb_ditolak(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload([
                'dokumen' => $this->pdfFile('surat-besar.pdf', 5 * 1024 * 1024 + 4096),
            ]))
            ->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('dinas_claims', 0);
    }

    public function test_karyawan_bisa_unduh_dokumen_sendiri(): void
    {
        $emp = $this->empGowa();
        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);

        $this->actingAs($emp, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_karyawan_tidak_bisa_unduh_dokumen_karyawan_lain(): void
    {
        $dinas = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);

        $this->actingAs($this->empMaros(), 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertForbidden();
    }

    public function test_admin_wilayah_hanya_bisa_unduh_dokumen_wilayahnya(): void
    {
        $dinasGowa = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);
        $dinasMaros = $this->ajukan($this->empMaros(), ['dokumen' => $this->pdfFile()]);

        // Wilayah sendiri → boleh
        $this->actingAs($this->adminGowa(), 'web')
            ->get("/admin/dinas/{$dinasGowa->id}/dokumen")
            ->assertOk();

        // Wilayah lain → 403
        $this->actingAs($this->adminGowa(), 'web')
            ->get("/admin/dinas/{$dinasMaros->id}/dokumen")
            ->assertForbidden();
    }

    public function test_super_admin_bisa_unduh_dokumen_semua_wilayah(): void
    {
        $dinas = $this->ajukan($this->empMaros(), ['dokumen' => $this->pdfFile()]);

        $this->actingAs($this->superAdmin(), 'web')
            ->get("/super-admin/dinas/{$dinas->id}/dokumen")
            ->assertOk();
    }

    public function test_dokumen_hilang_dari_penyimpanan_mengembalikan_404(): void
    {
        $emp = $this->empGowa();
        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);

        Storage::disk('local')->delete($dinas->dokumen_path);

        $this->actingAs($emp, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertNotFound();
    }

    public function test_pengajuan_lama_tanpa_dokumen_mengembalikan_404(): void
    {
        $emp = $this->empGowa();
        $dinas = DinasClaim::create([
            'employee_id' => $emp->id,
            'nomor_surat' => '001/LEGACY/2026',
            'tanggal_mulai' => now('Asia/Makassar')->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->toDateString(),
            'tanggal_pengajuan' => now('Asia/Makassar')->toDateString(),
            'keterangan' => 'Data lama tanpa dokumen',
            'tujuan' => 'Bone',
            'status' => 'Menunggu',
        ]);

        $this->assertFalse($dinas->hasDokumen());
        $this->assertSame('-', $dinas->dokumenSizeLabel());

        $this->actingAs($emp, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertNotFound();
    }

    public function test_batalkan_pengajuan_ikut_menghapus_dokumen(): void
    {
        $emp = $this->empGowa();
        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);
        $path = $dinas->dokumen_path;

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->delete("/karyawan/dinas/{$dinas->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('dinas_claims', ['id' => $dinas->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_admin_hapus_pengajuan_ikut_menghapus_dokumen(): void
    {
        $dinas = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);
        $path = $dinas->dokumen_path;

        $this->actingAs($this->adminGowa(), 'web')
            ->from('/admin/dinas')
            ->delete("/admin/dinas/{$dinas->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('dinas_claims', ['id' => $dinas->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_halaman_karyawan_dan_admin_mengirim_info_dokumen(): void
    {
        $emp = $this->empGowa();
        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);

        $this->actingAs($emp, 'employee')
            ->get('/karyawan/dinas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Karyawan/Dinas')
                ->where('list.0.dokumen_nama', 'surat-tugas.pdf')
                ->where('list.0.dokumen_is_image', false)
                ->where('list.0.dokumen_url', route('karyawan.dinas.dokumen', $dinas->id))
            );

        $this->actingAs($this->adminGowa(), 'web')
            ->get('/admin/dinas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dinas')
                ->where('list.0.dokumen_nama', 'surat-tugas.pdf')
                ->where('list.0.dokumen_size', '2.0 KB')
            );
    }
}
