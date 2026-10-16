<?php

namespace App\Support;

use App\Models\Region;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Templat XLSX untuk impor akun karyawan.
 *
 * Enam kolom pertama disalin huruf demi huruf dari berkas yang biasa dikirim
 * PPK (NAMA, NIK/NIP, STATUS PEGAWAI, SATKER, PPK, EMAIL). Tujuannya supaya
 * berkas lama tidak perlu diubah sama sekali: salin isinya ke templat ini, dan
 * kolom A–F langsung cocok. Kolom tambahan milik aplikasi diletakkan setelahnya
 * dan boleh dibiarkan kosong.
 *
 * Kolom NIK/NIP dan NIP sengaja berformat Teks. NIK 16 digit dan NIP 18 digit
 * melampaui presisi angka Excel (2^53), sehingga bila kolomnya berformat
 * angka, digit terakhir sudah hilang SEBELUM berkas sampai ke server dan tidak
 * mungkin dipulihkan lagi.
 *
 * Templat dibuat sesuai cakupan: admin wilayah hanya melihat wilayahnya sendiri
 * di daftar dropdown dan daftar titik, super admin melihat semuanya.
 */
class KaryawanTemplate
{
    /** Banyak baris kosong yang sudah diformat, supaya pengguna tinggal mengisi. */
    public const BARIS_KOSONG = 300;

    public const LEMBAR = 'Karyawan';

    /**
     * Susunan kolom templat. Urutan menentukan huruf kolom (A, B, C, …).
     *
     * `wajib` dipakai untuk pewarnaan judul, teks petunjuk, dan aturan yang
     * berlaku di KaryawanImpor. Judul kolom sengaja TIDAK diberi tanda bintang:
     * satu karakter tambahan saja membuat judulnya tidak lagi sama persis
     * dengan berkas PPK.
     */
    public const KOLOM = [
        // ── Enam kolom berkas PPK, disalin persis ──────────────────────
        ['kunci' => 'nama', 'judul' => 'NAMA', 'lebar' => 30, 'wajib' => true],
        ['kunci' => 'nik', 'judul' => 'NIK/NIP', 'lebar' => 22, 'wajib' => false],
        [
            'kunci' => 'jabatan',
            'judul' => 'STATUS PEGAWAI (Petugas OP, Tenaga Pendukung, Konsultan Individu, Konsultan Supervisi, Pekarya dll)',
            'lebar' => 44,
            'wajib' => true,
        ],
        ['kunci' => 'unit', 'judul' => 'SATKER', 'lebar' => 26, 'wajib' => true],
        ['kunci' => 'ppk', 'judul' => 'PPK', 'lebar' => 24, 'wajib' => false],
        ['kunci' => 'email', 'judul' => 'EMAIL', 'lebar' => 32, 'wajib' => true],

        // ── Kolom tambahan, boleh dikosongkan ──────────────────────────
        ['kunci' => 'wilayah', 'judul' => 'Wilayah', 'lebar' => 24, 'wajib' => false],
        ['kunci' => 'titik', 'judul' => 'Titik Proyek', 'lebar' => 30, 'wajib' => false],
        ['kunci' => 'nip', 'judul' => 'NIP', 'lebar' => 22, 'wajib' => false],
        ['kunci' => 'gol', 'judul' => 'Golongan', 'lebar' => 12, 'wajib' => false],
        ['kunci' => 'status', 'judul' => 'Status Kepegawaian', 'lebar' => 20, 'wajib' => false],
        ['kunci' => 'regu', 'judul' => 'Regu', 'lebar' => 14, 'wajib' => false],
        ['kunci' => 'is_shift', 'judul' => 'Shift', 'lebar' => 10, 'wajib' => false],
        ['kunci' => 'hari_kerja', 'judul' => 'Hari Kerja', 'lebar' => 22, 'wajib' => false],
        ['kunci' => 'sandi', 'judul' => 'Sandi Awal', 'lebar' => 16, 'wajib' => false],
    ];

    /**
     * Kunci kolom yang harus ada di baris 1 berkas unggahan.
     *
     * Wilayah dan Titik Proyek tidak lagi termasuk: keduanya bisa ditentukan
     * sekaligus untuk seluruh berkas lewat pilihan di halaman impor.
     */
    public static function kolomWajib(): array
    {
        return array_values(array_map(
            fn (array $kolom) => $kolom['kunci'],
            array_filter(self::KOLOM, fn (array $kolom) => $kolom['wajib'])
        ));
    }

    /** Huruf kolom untuk sebuah kunci, mis. 'nik' → 'B'. */
    public static function huruf(string $kunci): string
    {
        foreach (self::KOLOM as $i => $kolom) {
            if ($kolom['kunci'] === $kunci) {
                return Coordinate::stringFromColumnIndex($i + 1);
            }
        }

        throw new \InvalidArgumentException("Kolom templat tidak dikenal: {$kunci}");
    }

    public static function buat(?int $scopeRegionId): Spreadsheet
    {
        $regions = Region::with('sites')
            ->when($scopeRegionId, fn ($q) => $q->where('id', $scopeRegionId))
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        self::lembarIsian($spreadsheet, $regions);
        self::lembarDaftar($spreadsheet, $regions);
        self::lembarPetunjuk($spreadsheet, $scopeRegionId);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public static function simpan(?int $scopeRegionId, string $path): void
    {
        $writer = new Xlsx(self::buat($scopeRegionId));
        // Rumus tidak perlu dihitung ulang: templat hanya berisi nilai statis.
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
    }

    private static function lembarIsian(Spreadsheet $spreadsheet, $regions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::LEMBAR);

        foreach (self::KOLOM as $kolom) {
            $huruf = self::huruf($kolom['kunci']);
            $sheet->setCellValue("{$huruf}1", $kolom['judul']);
            $sheet->getColumnDimension($huruf)->setWidth($kolom['lebar']);
        }

        $akhir = self::huruf(self::KOLOM[array_key_last(self::KOLOM)]['kunci']);

        // Judul kolom wajib berlatar gelap, kolom opsional berlatar abu muda.
        // Perbedaan warna inilah pengganti tanda bintang, supaya judulnya tetap
        // sama persis dengan berkas PPK.
        foreach (self::KOLOM as $kolom) {
            $huruf = self::huruf($kolom['kunci']);
            $gaya = $sheet->getStyle("{$huruf}1");

            if ($kolom['wajib']) {
                $gaya->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
            } else {
                $gaya->getFont()->setBold(true)->getColor()->setARGB('FF334155');
                $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
            }

            $gaya->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $gaya->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $gaya->getAlignment()->setWrapText(true);
        }

        // Judul STATUS PEGAWAI panjang dan memakan dua baris bila dibungkus.
        $sheet->getRowDimension(1)->setRowHeight(46);

        $barisAkhir = self::BARIS_KOSONG + 1;

        // NIK/NIP dan NIP sebagai Teks: tanpa ini Excel memotong digit terakhirnya.
        foreach (['nik', 'nip'] as $kunci) {
            $huruf = self::huruf($kunci);
            $sheet->getStyle("{$huruf}2:{$huruf}{$barisAkhir}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $sheet->getStyle("A2:{$akhir}{$barisAkhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Status kepegawaian: daftar tertutup sesuai kolom enum di basis data.
        self::dropdown($sheet, 'status', $barisAkhir, '"PNS,PPPK,Kontrak,Honorer"');

        // Shift: hanya pekerja shift yang perlu mengabsen di luar hari kerja umum.
        self::dropdown($sheet, 'is_shift', $barisAkhir, '"Tidak,Ya"');

        // Wilayah: daftar diambil dari lembar "Daftar" agar admin wilayah tidak
        // pernah melihat (apalagi memilih) wilayah milik orang lain.
        $wilayahCount = max($regions->count(), 1);
        self::dropdown(
            $sheet,
            'wilayah',
            $barisAkhir,
            'Daftar!$E$2:$E$'.($wilayahCount + 1)
        );

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$akhir}1");
        $sheet->setSelectedCell('A2');
    }

    private static function dropdown($sheet, string $kunci, int $barisAkhir, string $formula): void
    {
        $huruf = self::huruf($kunci);

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setFormula1($formula);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle('Nilai tidak dikenal');
        $validation->setError('Pilih salah satu nilai dari daftar.');

        $sheet->setDataValidation("{$huruf}2:{$huruf}{$barisAkhir}", $validation);
    }

    /**
     * Lembar rujukan: daftar titik proyek per wilayah, plus daftar wilayah
     * unik di kolom E yang dipakai sebagai sumber dropdown.
     */
    private static function lembarDaftar(Spreadsheet $spreadsheet, $regions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Daftar');

        $sheet->setCellValue('A1', 'Wilayah');
        $sheet->setCellValue('B1', 'Kantor');
        $sheet->setCellValue('C1', 'Titik Proyek');
        $sheet->setCellValue('E1', 'Wilayah (daftar dropdown)');

        $baris = 2;
        foreach ($regions as $region) {
            foreach ($region->sites->sortBy('nama_lokasi') as $site) {
                $sheet->setCellValue("A{$baris}", $region->name);
                $sheet->setCellValue("B{$baris}", $region->kantor_name);
                $sheet->setCellValueExplicit("C{$baris}", $site->nama_lokasi, DataType::TYPE_STRING);
                $baris++;
            }
        }

        $barisWilayah = 2;
        foreach ($regions as $region) {
            $sheet->setCellValueExplicit("E{$barisWilayah}", $region->name, DataType::TYPE_STRING);
            $barisWilayah++;
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(3);
        $sheet->getColumnDimension('E')->setWidth(28);
        $sheet->setAutoFilter('A1:C'.max($baris - 1, 1));
        $sheet->freezePane('A2');
    }

    private static function lembarPetunjuk(Spreadsheet $spreadsheet, ?int $scopeRegionId): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Petunjuk');

        $wilayahWajib = $scopeRegionId === null;

        $baris = [
            ['Cara mengisi templat impor akun karyawan', 'judul'],
            ['', ''],
            ['1. Isi data pada lembar "Karyawan", mulai baris 2.', 'teks'],
            ['2. Jangan mengubah, menghapus, atau mengurutkan ulang baris judul di baris 1.', 'teks'],
            ['3. Judul kolom berlatar gelap wajib diisi; judul berlatar abu-abu boleh dikosongkan.', 'teks'],
            ['4. Jangan menambah kolom baru di depan. Kolom tambahan boleh diletakkan paling kanan.', 'teks'],
            ['5. Kolom NIK/NIP dan NIP sudah diformat sebagai Teks — biarkan begitu.', 'teks'],
            ['   Bila diubah ke format Angka, Excel akan memotong digit terakhir dan data tidak bisa dipulihkan.', 'catatan'],
            ['6. Nama wilayah dan nama titik harus sama persis dengan daftar di lembar "Daftar".', 'teks'],
            ['7. Simpan berkas, lalu unggah di menu Karyawan → Impor.', 'teks'],
            ['', ''],
            ['Kolom wajib', 'subjudul'],
            ['NAMA — nama lengkap karyawan.', 'teks'],
            ['STATUS PEGAWAI — bebas diisi, mis. "Petugas OP", "Tenaga Pendukung", "Konsultan Individu".', 'teks'],
            ['SATKER — satuan kerja karyawan, mis. "OP SDA".', 'teks'],
            ['EMAIL — dipakai untuk login, harus belum terdaftar, dan harus unik.', 'teks'],
            [$wilayahWajib
                ? 'Wilayah & Titik Proyek — boleh dikosongkan bila dipilih di halaman impor.'
                : 'Wilayah & Titik Proyek — boleh dikosongkan; otomatis memakai wilayah Anda.', 'teks'],
            ['', ''],
            ['Kolom opsional', 'subjudul'],
            ['NIK/NIP — boleh dikosongkan, dan boleh kurang dari 16 digit. Isi apa adanya.', 'teks'],
            ['PPK — label PPK karyawan, mis. "PPK OP SDA II". Hanya keterangan.', 'teks'],
            ['NIP, Golongan — boleh dikosongkan.', 'teks'],
            ['Status Kepegawaian — PNS, PPPK, Kontrak, atau Honorer. Kosong berarti memakai', 'teks'],
            ['   pilihan "Status kepegawaian bawaan" di halaman impor.', 'catatan'],
            ['Regu — label bebas, mis. "Regu A". Kosongkan bila tidak memakai regu.', 'teks'],
            ['Shift — isi "Ya" bila karyawan bekerja shift, selain itu "Tidak" atau kosongkan.', 'teks'],
            ['Hari Kerja — mis. "1,2,3,4,5" (Senin–Jumat) atau "Senin-Jumat".', 'teks'],
            ['   Kosongkan bila karyawan mengikuti hari kerja umum.', 'catatan'],
            ['Sandi Awal — kosongkan bila sandi awal diambil dari NIK atau email.', 'teks'],
            ['', ''],
            ['Catatan', 'subjudul'],
            ['Sandi awal karyawan baru wajib diganti saat login pertama.', 'catatan'],
            ['Bila NIK kosong, sandi awal otomatis memakai email karyawan.', 'catatan'],
            ['Baris yang gagal tidak akan diimpor; sisanya tetap diproses.', 'catatan'],
        ];

        foreach ($baris as $i => [$teks, $gaya]) {
            $n = $i + 1;
            $sheet->setCellValueExplicit("A{$n}", $teks, DataType::TYPE_STRING);

            if ($gaya === 'judul') {
                $sheet->getStyle("A{$n}")->getFont()->setBold(true)->setSize(13);
            } elseif ($gaya === 'subjudul') {
                $sheet->getStyle("A{$n}")->getFont()->setBold(true);
            } elseif ($gaya === 'catatan') {
                $sheet->getStyle("A{$n}")->getFont()->getColor()->setARGB('FF92400E');
            }
        }

        $sheet->getColumnDimension('A')->setWidth(110);
    }
}
