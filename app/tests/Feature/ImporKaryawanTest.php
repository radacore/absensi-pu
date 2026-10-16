<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Support\KaryawanImpor;
use App\Support\KaryawanTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor akun karyawan massal (menu Karyawan → Impor).
 *
 * Perhatian pada dua hal yang mudah rusak tanpa terlihat:
 *   - NIK 16 digit dan NIP 18 digit melampaui presisi angka Excel. Bila kolomnya
 *     bertipe angka, digit terakhir sudah berubah sebelum berkas sampai ke
 *     server. Baris seperti itu harus ditolak, bukan ditebak.
 *   - Admin wilayah tidak boleh menyentuh karyawan di luar wilayahnya, walau
 *     NIK-nya diketik langsung di berkas.
 */
class ImporKaryawanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ── Bantu ────────────────────────────────────────────────────────────

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function adminGowa(): User
    {
        return User::where('email', 'admin.gowa@bbws-pj.go.id')->firstOrFail();
    }

    private function region(string $nama): Region
    {
        return Region::where('name', $nama)->firstOrFail();
    }

    private function titik(Region $region, int $urutan = 0): Site
    {
        return $region->sites()->orderBy('id')->get()[$urutan];
    }

    /**
     * Susun berkas XLSX uji dari daftar baris.
     *
     * Nilai bertipe int/float ditulis sebagai sel angka (meniru Excel), nilai
     * teks ditulis sebagai sel teks. Perbedaan ini yang menentukan apakah
     * NIK/NIP selamat atau tidak.
     */
    private function berkas(array $baris, ?array $judul = null): UploadedFile
    {
        $judul ??= array_column(KaryawanTemplate::KOLOM, 'judul');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($judul as $i => $teks) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).'1', $teks, DataType::TYPE_STRING);
        }

        foreach ($baris as $r => $nilai) {
            $nomor = $r + 2;
            foreach ($nilai as $kunci => $isi) {
                $huruf = KaryawanTemplate::huruf($kunci);

                if (is_int($isi) || is_float($isi)) {
                    $sheet->setCellValueExplicit($huruf.$nomor, $isi, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($huruf.$nomor, (string) $isi, DataType::TYPE_STRING);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uji-impor-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'karyawan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** Satu baris lengkap dan benar untuk wilayah tertentu. */
    private function barisValid(string|int $nik, string $nama, Region $region, array $tambahan = []): array
    {
        return array_merge([
            'nik' => $nik,
            'nama' => $nama,
            'email' => strtolower(str_replace(' ', '.', $nama)).'@uji.test',
            'wilayah' => $region->name,
            'titik' => $this->titik($region)->nama_lokasi,
            'jabatan' => 'Staf',
            'unit' => 'Bidang Umum',
            'status' => 'PNS',
        ], $tambahan);
    }

    // ── Templat ──────────────────────────────────────────────────────────

    public function test_super_admin_bisa_mengunduh_templat_xlsx(): void
    {
        $respon = $this->actingAs($this->superAdmin())->get('/super-admin/employees/impor/templat');

        $respon->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $respon->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'templat-impor-karyawan',
            (string) $respon->headers->get('content-disposition')
        );
    }

    public function test_admin_wilayah_bisa_mengunduh_templat(): void
    {
        $this->actingAs($this->adminGowa())
            ->get('/admin/employees/impor/templat')
            ->assertOk();
    }

    public function test_templat_super_admin_memuat_semua_wilayah(): void
    {
        $spreadsheet = KaryawanTemplate::buat(null);
        $daftar = $spreadsheet->getSheetByName('Daftar');
        $this->assertNotNull($daftar);

        $teks = $this->isiLembar($daftar);
        $this->assertStringContainsString('Kab. Gowa', $teks);
        $this->assertStringContainsString('Kab. Bone', $teks);
        $this->assertStringContainsString('Kota Makassar', $teks);
    }

    public function test_templat_admin_wilayah_hanya_memuat_wilayahnya(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $spreadsheet = KaryawanTemplate::buat($gowa->id);
        $teks = $this->isiLembar($spreadsheet->getSheetByName('Daftar'));

        $this->assertStringContainsString('Kab. Gowa', $teks);
        $this->assertStringNotContainsString('Kab. Bone', $teks);
        $this->assertStringNotContainsString('Kota Makassar', $teks);
    }

    public function test_templat_menyimpan_nik_dan_nip_sebagai_teks(): void
    {
        $spreadsheet = KaryawanTemplate::buat(null);
        $sheet = $spreadsheet->getSheetByName(KaryawanTemplate::LEMBAR);

        foreach (['nik', 'nip'] as $kunci) {
            $format = $sheet->getStyle(KaryawanTemplate::huruf($kunci).'2')
                ->getNumberFormat()
                ->getFormatCode();

            $this->assertSame('@', $format, "Kolom {$kunci} harus berformat Teks.");
        }
    }

    /** Membaca seluruh isi lembar sebagai satu teks, untuk pemeriksaan sederhana. */
    private function isiLembar($sheet): string
    {
        $keluar = '';
        foreach ($sheet->getRowIterator() as $baris) {
            foreach ($baris->getCellIterator() as $sel) {
                $keluar .= ' '.$sel->getValue();
            }
        }

        return $keluar;
    }

    // ── Pratinjau ────────────────────────────────────────────────────────

    public function test_pratinjau_tidak_menyimpan_apa_pun_ke_basis_data(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $sebelum = Employee::count();

        $berkas = $this->berkas([
            $this->barisValid('7371000000000101', 'Karyawan Baru Satu', $gowa),
            $this->barisValid('7371000000000102', 'Karyawan Baru Dua', $gowa),
        ]);

        $respon = $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah']);

        $respon->assertRedirect('/super-admin/employees');
        $respon->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertIsArray($pratinjau);
        $this->assertSame(2, $pratinjau['ringkasan']['total']);
        $this->assertSame(2, $pratinjau['ringkasan']['tambah']);
        $this->assertSame(0, $pratinjau['ringkasan']['gagal']);

        $this->assertSame($sebelum, Employee::count(), 'Pratinjau tidak boleh menulis ke basis data.');
        $this->assertNull(Employee::where('nik', '7371000000000101')->first());
    }

    /**
     * Pratinjau harus benar-benar sampai ke layar. Bila kuncinya tidak dibagikan
     * oleh HandleInertiaRequests, unggahan tampak berhasil tetapi tabelnya kosong.
     */
    public function test_hasil_pratinjau_diteruskan_ke_props_halaman(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000000111', 'Karyawan Pratinjau', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees/impor')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertRedirect('/super-admin/employees/impor');

        $this->get('/super-admin/employees/impor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeeImport')
                ->where('flash.importPreview.berkas', 'karyawan.xlsx')
                ->where('flash.importPreview.ringkasan.tambah', 1)
                ->where('flash.importPreview.baris.0.nama', 'Karyawan Pratinjau')
                ->where('flash.importPreview.baris.0.aksi', 'tambah')
            );
    }

    /** Hasil impor juga harus sampai ke layar, bukan hanya pesan sukses. */
    public function test_hasil_impor_diteruskan_ke_props_halaman(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000000121', 'Karyawan Hasil', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees/impor')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertRedirect('/super-admin/employees/impor');

        $this->get('/super-admin/employees/impor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EmployeeImport')
                ->where('flash.importHasil.berkas', 'karyawan.xlsx')
                ->where('flash.importHasil.ringkasan.tambah', 1)
                ->where('flash.importHasil.ringkasan.gagal', 0)
            );
    }

    public function test_pratinjau_menandai_baris_yang_bermasalah(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $bone = $this->region('Kab. Bone');

        $berkas = $this->berkas([
            $this->barisValid('7371000000000201', 'Benar Sekali', $gowa),
            $this->barisValid('7371000000000202', 'Wilayah Salah', $gowa, ['wilayah' => 'Kab. Tidak Terdaftar']),
            // NIK pendek seperti "123" kini SAH (berkas PPK memang begitu),
            // jadi yang diuji di sini adalah karakter yang memang terlarang.
            $this->barisValid('nik@aneh', 'Nik Aneh', $gowa),
            $this->barisValid('7371000000000203', 'Status Salah', $gowa, ['status' => 'CPNS']),
            $this->barisValid('7371000000000204', 'Titik Nyasar', $gowa, ['titik' => $this->titik($bone)->nama_lokasi]),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(1, $pratinjau['ringkasan']['tambah']);
        $this->assertSame(4, $pratinjau['ringkasan']['gagal']);

        $pesan = collect($pratinjau['baris'])->flatMap(fn ($b) => $b['pesan'])->implode(' | ');
        $this->assertStringContainsString('tidak terdaftar', $pesan);
        $this->assertStringContainsString('hanya boleh berisi angka atau huruf', $pesan);
        $this->assertStringContainsString('PNS, PPPK, Kontrak, atau Honorer', $pesan);
        $this->assertStringContainsString('tidak ada di', $pesan);
    }

    public function test_pratinjau_menandai_nik_kembar_di_dalam_satu_berkas(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000000301', 'Kembar Satu', $gowa),
            $this->barisValid('7371000000000301', 'Kembar Dua', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(0, $pratinjau['ringkasan']['tambah']);
        $this->assertSame(2, $pratinjau['ringkasan']['gagal']);

        $pesan = collect($pratinjau['baris'])->flatMap(fn ($b) => $b['pesan'])->implode(' | ');
        $this->assertStringContainsString('muncul di baris lain', $pesan);
    }

    // ── Penolakan berkas ─────────────────────────────────────────────────

    public function test_berkas_tanpa_kolom_wajib_ditolak(): void
    {
        // Mengikuti gaya berkas PPK, tetapi kolom SATKER dihilangkan. NIK/NIP,
        // PPK, Wilayah, dan Titik Proyek bukan kolom wajib lagi.
        $judul = ['NAMA', 'NIK/NIP', 'STATUS PEGAWAI', 'PPK', 'EMAIL'];
        $berkas = $this->berkas([[
            'nama' => 'Tanpa Satker',
            'nik' => '7371000000000401',
            'jabatan' => 'Staf',
            'ppk' => 'PPK BMN',
            'email' => 'tanpa.satker@uji.test',
        ]], $judul);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasErrors('berkas');

        $this->assertStringContainsString(
            'SATKER',
            session('errors')->first('berkas')
        );
        $this->assertSame(0, Employee::where('nik', '7371000000000401')->count());
    }

    public function test_berkas_bukan_excel_ditolak(): void
    {
        $berkas = UploadedFile::fake()->createWithContent('karyawan.txt', "nik,nama\n7371000000000501,Uji\n");

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasErrors('berkas');
    }

    public function test_mode_impor_yang_tidak_dikenal_ditolak(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $berkas = $this->berkas([$this->barisValid('7371000000000601', 'Mode Aneh', $gowa)]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'hapus-semua'])
            ->assertSessionHasErrors('mode');
    }

    // ── Simpan ───────────────────────────────────────────────────────────

    public function test_impor_menambahkan_karyawan_dan_mewajibkan_ganti_sandi(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $titik = $this->titik($gowa);

        $berkas = $this->berkas([
            $this->barisValid('7371000000000701', 'Karyawan Tujuh Satu', $gowa, [
                'nip' => '198501012010011071',
                'gol' => 'III/c',
                'regu' => 'Regu B',
                'is_shift' => 'Ya',
                'hari_kerja' => '1,2,3,4,5',
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $karyawan = Employee::where('nik', '7371000000000701')->first();
        $this->assertNotNull($karyawan);
        $this->assertSame('Karyawan Tujuh Satu', $karyawan->name);
        $this->assertSame('198501012010011071', $karyawan->nip);
        $this->assertSame('III/c', $karyawan->golongan);
        $this->assertSame('Regu B', $karyawan->regu);
        $this->assertTrue($karyawan->is_shift);
        $this->assertSame(['1', '2', '3', '4', '5'], $karyawan->hari_kerja);
        $this->assertSame($gowa->id, $karyawan->region_id);
        $this->assertSame($titik->id, $karyawan->site_id);
        $this->assertTrue($karyawan->must_change_password);

        // Sandi awal = NIK, dan tersimpan dalam bentuk hash.
        $this->assertTrue(Hash::check('7371000000000701', $karyawan->password));
    }

    public function test_impor_bisa_memakai_sandi_dari_kolom(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000000801', 'Sandi Kolom', $gowa, ['sandi' => 'rahasiaKu123']),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'kolom',
                'wajibGanti' => '0',
            ])
            ->assertSessionHasNoErrors();

        $karyawan = Employee::where('nik', '7371000000000801')->firstOrFail();
        $this->assertTrue(Hash::check('rahasiaKu123', $karyawan->password));
        $this->assertFalse($karyawan->must_change_password);
    }

    public function test_impor_menolak_nik_yang_terbaca_sebagai_angka(): void
    {
        $gowa = $this->region('Kab. Gowa');

        // Ditulis sebagai sel angka: inilah yang dilakukan Excel bila kolomnya
        // tidak diformat Teks, dan digit terakhirnya bisa sudah berubah.
        $berkas = $this->berkas([
            $this->barisValid(7371000000000901, 'Nik Angka', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(0, $pratinjau['ringkasan']['tambah']);
        $this->assertSame(1, $pratinjau['ringkasan']['gagal']);
        $this->assertStringContainsString('angka oleh Excel', $pratinjau['baris'][0]['pesan'][0]);
    }

    public function test_impor_menolak_nip_yang_terbaca_sebagai_angka(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000001001', 'Nip Angka', $gowa, ['nip' => 198501012010011001]),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(0, $pratinjau['ringkasan']['tambah']);
        $this->assertStringContainsString('NIP terbaca sebagai angka', $pratinjau['baris'][0]['pesan'][0]);
    }

    // ── Mode tambah / perbarui / gabungan ────────────────────────────────

    public function test_mode_tambah_melewati_nik_yang_sudah_terdaftar(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $andi = Employee::where('nik', '7371001234567890')->firstOrFail();
        $namaLama = $andi->name;

        $berkas = $this->berkas([
            $this->barisValid('7371001234567890', 'Nama Diubah', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($namaLama, $andi->fresh()->name, 'Mode "Tambah saja" tidak boleh mengubah data lama.');
        $this->assertSame(1, session('importHasil')['ringkasan']['lewati']);
        $this->assertStringContainsString('Tidak ada perubahan', (string) session('success'));
    }

    public function test_mode_perbarui_mengubah_data_tapi_tidak_menyentuh_sandi(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $andi = Employee::where('nik', '7371001234567890')->firstOrFail();
        $sandiLama = $andi->password;

        $berkas = $this->berkas([
            $this->barisValid('7371001234567890', 'Andi Saputra Baru', $gowa, [
                'jabatan' => 'Kepala Bidang',
                'unit' => 'Bidang Jalan',
                'status' => 'PPPK',
            ]),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'perbarui',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $andi->refresh();
        $this->assertSame('Andi Saputra Baru', $andi->name);
        $this->assertSame('Kepala Bidang', $andi->jabatan);
        $this->assertSame('PPPK', $andi->status_kepegawaian);

        $this->assertSame($sandiLama, $andi->password, 'Memperbarui data tidak boleh mengganti sandi.');
        $this->assertFalse($andi->must_change_password, 'Memperbarui data tidak boleh memaksa ganti sandi.');
    }

    public function test_mode_perbarui_melewati_nik_yang_belum_terdaftar(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000001101', 'Belum Terdaftar', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'perbarui'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(0, $pratinjau['ringkasan']['tambah']);
        $this->assertSame(1, $pratinjau['ringkasan']['lewati']);
        $this->assertNull(Employee::where('nik', '7371000000001101')->first());
    }

    public function test_mode_gabungan_menambah_dan_memperbarui_sekaligus(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371001234567890', 'Andi Diperbarui', $gowa),
            $this->barisValid('7371000000001201', 'Karyawan Baru', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'gabungan',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $hasil = session('importHasil')['ringkasan'];
        $this->assertSame(1, $hasil['tambah']);
        $this->assertSame(1, $hasil['perbarui']);

        $this->assertSame('Andi Diperbarui', Employee::where('nik', '7371001234567890')->firstOrFail()->name);
        $this->assertNotNull(Employee::where('nik', '7371000000001201')->first());
    }

    public function test_baris_bermasalah_dilewati_sisanya_tetap_masuk(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000001301', 'Baris Benar', $gowa),
            // Karakter terlarang pada NIK; "bukan-nik" justru kini sah.
            $this->barisValid('nik@aneh', 'Baris Salah', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Employee::where('nik', '7371000000001301')->first());
        $this->assertSame(1, session('importHasil')['ringkasan']['gagal']);
    }

    public function test_impor_yang_tidak_menghasilkan_apa_pun_memberi_pesan_kesalahan(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('nik@aneh', 'Tidak Ada Yang Benar', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasErrors('berkas');
    }

    // ── Cakupan wilayah ──────────────────────────────────────────────────

    public function test_admin_wilayah_tidak_bisa_mengimpor_karyawan_wilayah_lain(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $bone = $this->region('Kab. Bone');

        $berkas = $this->berkas([
            $this->barisValid('7371000000001401', 'Karyawan Gowa', $gowa),
            $this->barisValid('7371000000001402', 'Karyawan Bone', $bone),
        ]);

        $this->actingAs($this->adminGowa())
            ->from('/admin/employees')
            ->post('/admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Employee::where('nik', '7371000000001401')->first());

        $asing = Employee::where('nik', '7371000000001402')->first();
        $this->assertNull($asing, 'Admin wilayah tidak boleh menambah karyawan wilayah lain.');

        $this->assertSame(1, session('importHasil')['ringkasan']['gagal']);
    }

    public function test_admin_wilayah_tidak_bisa_mengubah_karyawan_wilayah_lain(): void
    {
        // Siti ada di Kota Makassar (wilayah 1), Andi di Kab. Gowa (wilayah 2).
        $siti = Employee::where('nik', '7371001234567891')->firstOrFail();
        $namaLama = $siti->name;
        $makassar = $this->region('Kota Makassar');

        $berkas = $this->berkas([
            $this->barisValid('7371001234567891', 'Siti Diubah Paksa', $makassar),
        ]);

        $this->actingAs($this->adminGowa())
            ->from('/admin/employees')
            ->post('/admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'perbarui',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasErrors('berkas');

        $this->assertSame($namaLama, $siti->fresh()->name);
    }

    public function test_admin_wilayah_boleh_impor_tanpa_mengisi_kolom_wilayah(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $baris = $this->barisValid('7371000000001501', 'Tanpa Wilayah', $gowa);
        unset($baris['wilayah']);

        $berkas = $this->berkas([$baris]);

        $this->actingAs($this->adminGowa())
            ->from('/admin/employees')
            ->post('/admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $karyawan = Employee::where('nik', '7371000000001501')->first();
        $this->assertNotNull($karyawan);
        $this->assertSame($gowa->id, $karyawan->region_id);
    }

    // ── Jejak audit ──────────────────────────────────────────────────────

    public function test_impor_mencatat_jejak_audit(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            $this->barisValid('7371000000001601', 'Untuk Audit', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'employee.import')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->meta['ringkasan']['tambah']);
    }

    // ── Batas berkas ─────────────────────────────────────────────────────

    public function test_berkas_lebih_dari_seribu_baris_ditolak(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $baris = [];

        for ($i = 0; $i < KaryawanImpor::MAX_BARIS + 1; $i++) {
            $baris[] = $this->barisValid('7371'.str_pad((string) $i, 12, '0', STR_PAD_LEFT), 'Massal '.$i, $gowa);
        }

        $berkas = $this->berkas($baris);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasErrors('berkas');

        $this->assertStringContainsString('1000', session('errors')->first('berkas'));
    }

    public function test_berkas_tanpa_baris_data_ditolak(): void
    {
        $berkas = $this->berkas([]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasErrors('berkas');
    }

    // ── Hak akses ────────────────────────────────────────────────────────

    public function test_tamu_tidak_bisa_mengakses_impor(): void
    {
        $this->get('/super-admin/employees/impor/templat')->assertRedirect();
    }

    public function test_akun_karyawan_tidak_bisa_mengakses_impor(): void
    {
        $karyawan = Employee::where('nik', '7371001234567890')->firstOrFail();

        $this->actingAs($karyawan, 'employee')
            ->get('/super-admin/employees/impor/templat')
            ->assertRedirect();
    }

    // ── Bentuk berkas: NIK sebagai teks harus diterima ───────────────────

    public function test_nik_teks_enam_belas_digit_diterima(): void
    {
        $gowa = $this->region('Kab. Gowa');

        // Persis kasus yang dirisaukan: 16 digit, tersimpan sebagai teks.
        $berkas = $this->berkas([
            $this->barisValid('7371123456780001', 'Nik Enam Belas', $gowa),
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $pratinjau = session('importPreview');
        $this->assertSame(1, $pratinjau['ringkasan']['tambah']);
        $this->assertSame('7371123456780001', $pratinjau['baris'][0]['nik']);
    }

    public function test_kolom_opsional_boleh_kosong(): void
    {
        $gowa = $this->region('Kab. Gowa');

        $berkas = $this->berkas([
            [
                'nik' => '7371000000001701',
                'nama' => 'Hanya Wajib',
                'email' => 'hanya.wajib@uji.test',
                'wilayah' => $gowa->name,
                'titik' => $this->titik($gowa)->nama_lokasi,
                'jabatan' => 'Staf',
                'unit' => 'Bidang Umum',
                'status' => 'Honorer',
            ],
        ]);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
            ])
            ->assertSessionHasNoErrors();

        $karyawan = Employee::where('nik', '7371000000001701')->first();
        $this->assertNotNull($karyawan);
        $this->assertNull($karyawan->nip);
        $this->assertNull($karyawan->golongan);
        $this->assertNull($karyawan->regu);
        $this->assertFalse($karyawan->is_shift);
        $this->assertNull($karyawan->hari_kerja);
    }

    public function test_nama_kolom_tidak_peka_huruf_besar_kecil_dan_spasi(): void
    {
        $gowa = $this->region('Kab. Gowa');

        // Judul templat yang sama, tetapi huruf besar/kecil dan spasinya diacak.
        $judul = array_map(
            fn ($teks) => ' '.strtolower((string) $teks).' ',
            array_column(KaryawanTemplate::KOLOM, 'judul')
        );

        $berkas = $this->berkas([[
            'nik' => '7371000000001801',
            'nama' => 'Judul Acak',
            'email' => 'judul.acak@uji.test',
            'wilayah' => $gowa->name,
            'titik' => $this->titik($gowa)->nama_lokasi,
            'jabatan' => 'Staf',
            'unit' => 'Bidang Umum',
            'status' => 'PNS',
        ]], $judul);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor/pratinjau', ['berkas' => $berkas, 'mode' => 'tambah'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, session('importPreview')['ringkasan']['tambah']);
    }

    // ── Halaman impor ────────────────────────────────────────────────────

    public function test_halaman_impor_bisa_dibuka_super_admin(): void
    {
        $respon = $this->actingAs($this->superAdmin())->get('/super-admin/employees/impor');

        $respon->assertOk();
        $respon->assertInertia(fn ($page) => $page
            ->component('Admin/EmployeeImport')
            ->has('regions')
            ->has('batas')
        );
    }

    public function test_halaman_impor_admin_wilayah_hanya_memuat_wilayahnya(): void
    {
        $respon = $this->actingAs($this->adminGowa())->get('/admin/employees/impor');

        $respon->assertOk();
        $respon->assertInertia(fn ($page) => $page
            ->component('Admin/EmployeeImport')
            ->where('regions.0.name', 'Kab. Gowa')
            ->count('regions', 1)
        );
    }

    public function test_halaman_impor_super_admin_memuat_semua_wilayah(): void
    {
        $respon = $this->actingAs($this->superAdmin())->get('/super-admin/employees/impor');

        $respon->assertOk();
        $respon->assertInertia(fn ($page) => $page->count('regions', Region::count()));
    }

    // ── Pembacaan berkas ─────────────────────────────────────────────────

    /**
     * Penjaga penting: setiap judul kolom pada templat yang kita terbitkan harus
     * dikenali oleh mesin impor. Salah ketik satu alias (pernah terjadi pada
     * "Sandi Awal") membuat kolom opsional diam-diam terabaikan tanpa galat.
     */
    public function test_templat_bawaan_bisa_diisi_lalu_dibaca_utuh(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $spreadsheet = KaryawanTemplate::buat(null);
        $sheet = $spreadsheet->getSheetByName(KaryawanTemplate::LEMBAR);

        $nilai = [
            'nik' => '7371000000002001',
            'nama' => 'Utuh Sekali',
            'email' => 'utuh.sekali@uji.test',
            'wilayah' => $gowa->name,
            'titik' => $this->titik($gowa)->nama_lokasi,
            'jabatan' => 'Pengelola Data',
            'unit' => 'Bidang Air',
            'status' => 'PPPK',
            'nip' => '199003032020121002',
            'gol' => 'IX',
            'regu' => 'Regu Malam',
            'is_shift' => 'Ya',
            'hari_kerja' => '1,3,5',
            'sandi' => 'sandiAwalKu1',
        ];

        foreach ($nilai as $kunci => $isi) {
            $sheet->setCellValueExplicit(
                KaryawanTemplate::huruf($kunci).'2',
                (string) $isi,
                DataType::TYPE_STRING
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'uji-templat-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $berkas = new UploadedFile(
            $path,
            'templat-terisi.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'kolom',
                'wajibGanti' => '0',
            ])
            ->assertSessionHasNoErrors();

        $karyawan = Employee::where('nik', '7371000000002001')->firstOrFail();

        $this->assertSame('Utuh Sekali', $karyawan->name);
        $this->assertSame('utuh.sekali@uji.test', $karyawan->email);
        $this->assertSame('199003032020121002', $karyawan->nip);
        $this->assertSame('IX', $karyawan->golongan);
        $this->assertSame('Pengelola Data', $karyawan->jabatan);
        $this->assertSame('Bidang Air', $karyawan->unit_kerja);
        $this->assertSame('PPPK', $karyawan->status_kepegawaian);
        $this->assertSame('Regu Malam', $karyawan->regu);
        $this->assertTrue($karyawan->is_shift);
        $this->assertSame(['1', '3', '5'], $karyawan->hari_kerja);
        $this->assertSame($gowa->id, $karyawan->region_id);
        $this->assertSame($this->titik($gowa)->id, $karyawan->site_id);

        // Kolom "Sandi Awal" harus benar-benar terpakai, bukan diabaikan.
        $this->assertTrue(Hash::check('sandiAwalKu1', $karyawan->password));
    }

    public function test_berkas_hasil_impor_bisa_dibaca_ulang(): void
    {
        // Memastikan berkas yang ditulis benar-benar XLSX sah (bukan rusak),
        // dengan membacanya memakai pembaca yang sama seperti di server.
        $gowa = $this->region('Kab. Gowa');
        $berkas = $this->berkas([$this->barisValid('7371000000001901', 'Bulat Balik', $gowa)]);

        $spreadsheet = IOFactory::createReaderForFile($berkas->getRealPath())->load($berkas->getRealPath());
        // Kolom A adalah NAMA (mengikuti berkas PPK), jadi NIK/NIP ada di B.
        $this->assertSame(
            '7371000000001901',
            (string) $spreadsheet->getSheet(0)->getCell('B2')->getValue()
        );
    }

    // ── Kesesuaian dengan berkas PPK apa adanya ───────────────────────────

    /**
     * Penjaga inti revisi ini.
     *
     * Berkas PPK yang asli punya enam kolom dengan judul panjang, NIK yang
     * kadang kosong atau kurang dari 16 digit, dan PPK berspasi di ujung.
     * Semua itu harus masuk tanpa berkasnya diubah sedikit pun.
     */
    public function test_berkas_ppk_apa_adanya_bisa_diimpor(): void
    {
        $gowa = $this->region('Kab. Gowa');

        // Judul disalin huruf demi huruf dari berkas PPK.
        $judul = [
            'NAMA',
            'NIK/NIP',
            'STATUS PEGAWAI (Petugas OP, Tenaga Pendukung, Konsultan Individu, Konsultan Supervisi, Pekarya dll)',
            'SATKER',
            'PPK',
            'EMAIL',
        ];

        $berkas = $this->berkas([
            [
                // NIK kosong, seperti baris "ABDUL RAHIM. T, S.Tr.T".
                'nama' => 'ABDUL RAHIM. T, S.Tr.T',
                'nik' => '',
                'jabatan' => 'SECURITY',
                'unit' => 'BBWS PJPA EMMY SAELAN',
                'ppk' => 'PPK BMN ',
                'email' => 'abdul.rahim@uji.test',
            ],
            [
                // NIK 15 digit, seperti baris "Sry Rusmia, ST".
                'nama' => 'Sry Rusmia, ST',
                'nik' => '737114210000009',
                'jabatan' => 'Konsultan Individu',
                'unit' => 'Satker OP SDA',
                'ppk' => 'PPK OP SDA II ',
                'email' => 'sry.rusmia@uji.test',
            ],
        ], $judul);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'tambah',
                'sandi' => 'nik',
                'wajibGanti' => '1',
                // Berkas PPK tidak memuat wilayah, jadi ditentukan di halaman.
                'wilayah' => $gowa->name,
                'titik' => $this->titik($gowa)->id,
                // Berkas PPK juga tidak punya kolom Status Kepegawaian.
                'statusDefault' => 'Kontrak',
            ])
            ->assertSessionHasNoErrors();

        $hasil = session('importHasil')['ringkasan'];
        $this->assertSame(2, $hasil['tambah']);
        $this->assertSame(0, $hasil['gagal']);

        // NIK kosong → disimpan null, sandi awal jatuh ke email.
        $tanpaNik = Employee::where('email', 'abdul.rahim@uji.test')->firstOrFail();
        $this->assertNull($tanpaNik->nik);
        $this->assertSame('ABDUL RAHIM. T, S.Tr.T', $tanpaNik->name);
        $this->assertSame('SECURITY', $tanpaNik->jabatan);
        $this->assertSame('BBWS PJPA EMMY SAELAN', $tanpaNik->unit_kerja);
        $this->assertSame('PPK BMN', $tanpaNik->ppk, 'Spasi di ujung PPK harus dipangkas.');
        $this->assertSame('Kontrak', $tanpaNik->status_kepegawaian, 'Status bawaan halaman harus terpakai.');
        $this->assertTrue(Hash::check('abdul.rahim@uji.test', $tanpaNik->password));

        // NIK 15 digit → diterima apa adanya, sandi awal = NIK.
        $nikPendek = Employee::where('email', 'sry.rusmia@uji.test')->firstOrFail();
        $this->assertSame('737114210000009', $nikPendek->nik);
        $this->assertSame('PPK OP SDA II', $nikPendek->ppk);
        $this->assertTrue(Hash::check('737114210000009', $nikPendek->password));
    }

    /**
     * Memperbarui tidak boleh mengosongkan kolom yang tidak ada di berkas.
     *
     * Berkas dari PPK sering hanya memuat sebagian kolom. Bila kolom yang tidak
     * disertakan ikut ditulis, NIK dan PPK yang sudah tersimpan akan terhapus
     * hanya karena berkasnya ringkas.
     */
    public function test_perbarui_tidak_menghapus_kolom_yang_tidak_ada_di_berkas(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $andi = Employee::where('nik', '7371001234567890')->firstOrFail();
        $andi->update(['ppk' => 'PPK BMN']);

        // Judul kosong pada posisi NIK/NIP dan PPK: kolomnya memang tidak ada.
        $judul = ['NAMA', '', 'STATUS PEGAWAI', 'SATKER', '', 'EMAIL'];
        $berkas = $this->berkas([[
            'nama' => 'Andi Tetap Punya Nik',
            'jabatan' => 'Kepala Bidang',
            'unit' => 'Bidang Jalan',
            'email' => $andi->email,
        ]], $judul);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'perbarui',
                'sandi' => 'nik',
                'wajibGanti' => '1',
                'wilayah' => $gowa->name,
                'titik' => $this->titik($gowa)->id,
                // Berkas ringkas ini juga tidak punya kolom Status Kepegawaian.
                'statusDefault' => 'PNS',
            ])
            ->assertSessionHasNoErrors();

        $andi->refresh();
        $this->assertSame('Andi Tetap Punya Nik', $andi->name);
        $this->assertSame('Kepala Bidang', $andi->jabatan);
        $this->assertSame('7371001234567890', $andi->nik, 'NIK lama tidak boleh terhapus.');
        $this->assertSame('PPK BMN', $andi->ppk, 'PPK lama tidak boleh terhapus.');
    }

    /**
     * Berkas yang NIK-nya kosong tetap bisa diperbarui lewat email, bukan
     * malah membuat akun kembar.
     */
    public function test_perbarui_menemukan_karyawan_lewat_email_bila_nik_kosong(): void
    {
        $gowa = $this->region('Kab. Gowa');
        $andi = Employee::where('nik', '7371001234567890')->firstOrFail();
        $jumlahSebelum = Employee::count();

        $judul = ['NAMA', '', 'STATUS PEGAWAI', 'SATKER', 'PPK', 'EMAIL'];
        $berkas = $this->berkas([[
            'nama' => 'Andi Lewat Email',
            'jabatan' => 'Staff Teknik',
            'unit' => 'Bidang Jalan',
            'ppk' => 'PPK BMN',
            'email' => $andi->email,
        ]], $judul);

        $this->actingAs($this->superAdmin())
            ->from('/super-admin/employees')
            ->post('/super-admin/employees/impor', [
                'berkas' => $berkas,
                'mode' => 'perbarui',
                'sandi' => 'nik',
                'wajibGanti' => '1',
                'wilayah' => $gowa->name,
                'titik' => $this->titik($gowa)->id,
                'statusDefault' => 'PNS',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($jumlahSebelum, Employee::count(), 'Tidak boleh ada akun kembar.');
        $this->assertSame('Andi Lewat Email', $andi->fresh()->name);
        $this->assertSame(1, session('importHasil')['ringkasan']['perbarui']);
    }
}
