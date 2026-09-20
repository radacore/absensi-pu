<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\User;
use App\Support\MediaCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji pembersihan berkas di object storage + pembatasan laju unggah.
 *
 * Semua uji hermetis: disk dipalsukan, jadi tidak menyentuh bucket sungguhan.
 */
class StorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake(config('filesystems.uploads.private_disk'));
        Storage::fake(config('filesystems.uploads.public_disk'));
    }

    private function privateDisk()
    {
        return Storage::disk(config('filesystems.uploads.private_disk'));
    }

    private function publicDisk()
    {
        return Storage::disk(config('filesystems.uploads.public_disk'));
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    /** Berkas PDF asli (diawali magic bytes %PDF) supaya lolos rule mimetypes. */
    private function pdfFile(string $name = 'surat-tugas.pdf'): UploadedFile
    {
        $body = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $body .= str_repeat(' ', max(0, 2048 - strlen($body)));

        $path = tempnam(sys_get_temp_dir(), 'bersih-pdf-');
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

    private function ajukan(Employee $emp, array $overrides = []): DinasClaim
    {
        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return DinasClaim::where('employee_id', $emp->id)->latest('id')->firstOrFail();
    }

    // ------------------------------------------------------------------
    // Pembersihan dokumen dinas
    // ------------------------------------------------------------------

    public function test_menghapus_baris_dinas_lewat_eloquent_ikut_menghapus_dokumen(): void
    {
        $dinas = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);
        $path = $dinas->dokumen_path;

        $this->privateDisk()->assertExists($path);

        // Event model, bukan lewat controller — ini yang dulu bocor.
        $dinas->delete();

        $this->privateDisk()->assertMissing($path);
    }

    public function test_menghapus_karyawan_menghapus_semua_dokumen_dinasnya(): void
    {
        $emp = $this->empGowa();

        $satu = $this->ajukan($emp, ['dokumen' => $this->pdfFile(), 'nomor_surat' => 'A/1/2026']);
        $dua = $this->ajukan($emp, ['dokumen' => $this->pdfFile(), 'nomor_surat' => 'B/2/2026']);

        $this->privateDisk()->assertExists($satu->dokumen_path);
        $this->privateDisk()->assertExists($dua->dokumen_path);

        // Cascade foreign key menghapus baris di level basis data tanpa
        // memicu event model, jadi berkas akan tertinggal tanpa hook ini.
        $emp->delete();

        $this->privateDisk()->assertMissing($satu->dokumen_path);
        $this->privateDisk()->assertMissing($dua->dokumen_path);
        $this->assertDatabaseCount('dinas_claims', 0);
    }

    public function test_admin_menghapus_pengajuan_ikut_menghapus_dokumen(): void
    {
        $dinas = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);
        $path = $dinas->dokumen_path;

        $this->actingAs($this->adminGowa(), 'web')
            ->from('/admin/dinas')
            ->delete("/admin/dinas/{$dinas->id}")
            ->assertSessionHas('success');

        $this->privateDisk()->assertMissing($path);
    }

    // ------------------------------------------------------------------
    // Pembersihan foto profil
    // ------------------------------------------------------------------

    public function test_menghapus_foto_profil_ikut_menghapus_objeknya(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->put('/karyawan/profil', ['foto' => UploadedFile::fake()->image('wajah.jpg', 80, 60)]);

        $emp->refresh();
        $this->assertNotEmpty($emp->foto_url, 'Foto seharusnya tersimpan.');

        $key = MediaCleanup::publicKeyFromUrl($emp->foto_url);
        $this->assertNotNull($key, 'URL foto harus bisa dipetakan ke kunci objek.');
        $this->publicDisk()->assertExists($key);

        $this->actingAs($emp, 'employee')
            ->delete('/karyawan/profil/foto')
            ->assertSessionHas('success');

        $this->publicDisk()->assertMissing($key);
        $this->assertNull($emp->fresh()->foto_url);
    }

    public function test_mengganti_foto_profil_menghapus_foto_lama(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->put('/karyawan/profil', ['foto' => UploadedFile::fake()->image('lama.jpg', 80, 60)]);

        $keyLama = MediaCleanup::publicKeyFromUrl($emp->fresh()->foto_url);
        $this->publicDisk()->assertExists($keyLama);

        $this->actingAs($emp, 'employee')
            ->put('/karyawan/profil', ['foto' => UploadedFile::fake()->image('baru.jpg', 90, 70)]);

        $keyBaru = MediaCleanup::publicKeyFromUrl($emp->fresh()->foto_url);

        $this->assertNotSame($keyLama, $keyBaru);
        $this->publicDisk()->assertMissing($keyLama, 'Foto lama harus dibuang saat diganti.');
        $this->publicDisk()->assertExists($keyBaru);
    }

    public function test_menghapus_karyawan_ikut_menghapus_foto_profilnya(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->put('/karyawan/profil', ['foto' => UploadedFile::fake()->image('wajah.jpg', 80, 60)]);

        $key = MediaCleanup::publicKeyFromUrl($emp->fresh()->foto_url);
        $this->publicDisk()->assertExists($key);

        $emp->delete();

        $this->publicDisk()->assertMissing($key);
    }

    // ------------------------------------------------------------------
    // Pembatasan laju unggah
    // ------------------------------------------------------------------

    public function test_unggahan_dinas_dibatasi_sepuluh_per_menit(): void
    {
        $emp = $this->empGowa();

        // 10 pengajuan pertama lolos.
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($emp, 'employee')
                ->from('/karyawan/dinas')
                ->post('/karyawan/dinas', $this->payload([
                    'nomor_surat' => "UJI/{$i}/2026",
                    'dokumen' => $this->pdfFile("surat-{$i}.pdf"),
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('dinas_claims', 10);

        // Pengajuan ke-11 ditolak oleh limiter, bukan tersimpan.
        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload([
                'nomor_surat' => 'UJI/11/2026',
                'dokumen' => $this->pdfFile('surat-11.pdf'),
            ]))
            ->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('dinas_claims', 10);
    }

    // ------------------------------------------------------------------
    // Audit akses berkas
    // ------------------------------------------------------------------

    public function test_unduh_dokumen_tercatat_di_audit_log(): void
    {
        $emp = $this->empGowa();
        $dinas = $this->ajukan($emp, ['dokumen' => $this->pdfFile()]);

        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAs($emp, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertOk();

        $log = AuditLog::where('action', 'dinas.dokumen')->first();
        $this->assertNotNull($log, 'Unduhan dokumen harus tercatat di audit log.');
        $this->assertSame('employee', $log->actor_type);
        $this->assertSame($emp->id, (int) $log->actor_id);
        $this->assertSame($dinas->id, (int) ($log->meta['dinas_id'] ?? 0));
    }

    public function test_admin_unduh_dokumen_juga_tercatat(): void
    {
        $dinas = $this->ajukan($this->empGowa(), ['dokumen' => $this->pdfFile()]);

        $this->actingAs($this->adminGowa(), 'web')
            ->get("/admin/dinas/{$dinas->id}/dokumen")
            ->assertOk();

        $log = AuditLog::where('action', 'dinas.dokumen')->first();
        $this->assertNotNull($log);
        $this->assertSame('user', $log->actor_type);
    }
}
