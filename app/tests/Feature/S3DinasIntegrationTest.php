<?php

namespace Tests\Feature;

use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji integrasi SUNGGAHAN ke object storage (S3 / Neva Objects).
 *
 * Berbeda dengan DinasDokumenTest yang memalsukan disk, uji ini sengaja
 * TIDAK memalsukan apa pun — berkas benar-benar ditulis ke bucket, diunduh
 * kembali lewat route ber-otorisasi, lalu dihapus.
 *
 * Karena menyentuh jaringan, uji ini DILEWATI secara bawaan. Jalankan dengan:
 *
 *   S3_INTEGRATION=1 php artisan test --filter=S3DinasIntegrationTest
 */
class S3DinasIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> path objek yang dibuat, untuk dibersihkan di akhir. */
    private array $dibuat = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('S3_INTEGRATION', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Uji integrasi S3 dilewati. Jalankan dengan S3_INTEGRATION=1.');
        }

        $this->seed();
    }

    protected function tearDown(): void
    {
        $disk = Storage::disk(config('filesystems.uploads.private_disk'));
        foreach ($this->dibuat as $path) {
            // Hapus tanpa cek exists() lebih dulu — DELETE bersifat idempoten,
            // sedangkan exists() (HEAD) tidak stabil di endpoint ini.
            $disk->delete($path);
        }

        parent::tearDown();
    }

    private function diskName(): string
    {
        return config('filesystems.uploads.private_disk');
    }

    private function empGowa(): Employee
    {
        return Employee::where('region_id', 2)->firstOrFail();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function pdfFile(string $name = 'surat-tugas.pdf'): UploadedFile
    {
        $body = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $path = tempnam(sys_get_temp_dir(), 's3-pdf-');
        file_put_contents($path, $body);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nomor_surat' => 'S3/SPT/0627/2026',
            'tanggal_mulai' => now('Asia/Makassar')->addDays(2)->toDateString(),
            'tanggal_selesai' => now('Asia/Makassar')->addDays(4)->toDateString(),
            'keterangan' => 'Uji integrasi penyimpanan objek',
            'tujuan' => 'Bone',
            'transportasi' => 'mobil',
            'pembebanan_anggaran' => 'Kendaraan Sewa',
        ], $overrides);
    }

    /**
     * Cek keberadaan objek lewat GET.
     *
     * Sengaja TIDAK memakai Storage::exists(): itu memicu HEAD, dan endpoint
     * Neva Objects sesekali membalas 403 pada HEAD sehingga objek yang ada
     * dilaporkan hilang. GET terbukti stabil.
     */
    private function adaDiBucket(string $path): bool
    {
        return Storage::disk($this->diskName())->get($path) !== null;
    }

    public function test_disk_yang_dipakai_adalah_s3(): void
    {
        $this->assertSame('s3', $this->diskName(), 'Uji ini hanya bermakna bila UPLOAD_PRIVATE_DISK=s3.');
    }

    public function test_alur_lengkap_dinas_di_atas_s3(): void
    {
        $emp = $this->empGowa();

        // 1) Unggah pengajuan + dokumen -> harus mendarat di bucket.
        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload(['dokumen' => $this->pdfFile()]))
            ->assertSessionHasNoErrors();

        $dinas = DinasClaim::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->dibuat[] = $dinas->dokumen_path;

        $this->assertStringStartsWith('dinas/', $dinas->dokumen_path);
        $this->assertTrue(
            $this->adaDiBucket($dinas->dokumen_path),
            'Dokumen tidak ditemukan di bucket S3 setelah unggah.'
        );

        // 2) Objek privat tidak boleh bisa diakses anonim.
        $endpoint = rtrim((string) config('filesystems.disks.s3.endpoint'), '/');
        $bucket = config('filesystems.disks.s3.bucket');
        $anonUrl = "$endpoint/$bucket/{$dinas->dokumen_path}";
        for ($i = 0; $i < 3; $i++) {
            $code = trim((string) shell_exec(
                'curl -s -o /dev/null -w "%{http_code}" --max-time 20 '.escapeshellarg($anonUrl)
            ));
            $this->assertNotSame('200', $code, "Objek privat bocor ke publik (HTTP $code).");
        }

        // 3) Unduh lewat route ber-otorisasi -> 200 dan isi cocok.
        $res = $this->actingAs($emp, 'employee')
            ->get("/karyawan/dinas/{$dinas->id}/dokumen")
            ->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $res->streamedContent());

        // 4) Batalkan pengajuan -> baris DB DAN objek S3 ikut terhapus.
        $path = $dinas->dokumen_path;
        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->delete("/karyawan/dinas/{$dinas->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('dinas_claims', ['id' => $dinas->id]);
        $this->assertFalse(
            $this->adaDiBucket($path),
            'Objek S3 masih ada setelah pengajuan dibatalkan (berkas yatim).'
        );
    }

    public function test_super_admin_bisa_unduh_dokumen_dari_bucket(): void
    {
        $emp = $this->empGowa();

        $this->actingAs($emp, 'employee')
            ->from('/karyawan/dinas')
            ->post('/karyawan/dinas', $this->payload(['dokumen' => $this->pdfFile()]))
            ->assertSessionHasNoErrors();

        $dinas = DinasClaim::where('employee_id', $emp->id)->latest('id')->firstOrFail();
        $this->dibuat[] = $dinas->dokumen_path;

        $this->actingAs($this->superAdmin(), 'web')
            ->get("/super-admin/dinas/{$dinas->id}/dokumen")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
