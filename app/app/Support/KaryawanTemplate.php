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
 * Kolom NIK dan NIP sengaja berformat Teks. NIK 16 digit dan NIP 18 digit
 * melampaui presisi angka Excel (2^53), sehingga bila kolomnya berformat
 * angka, digit terakhir sudah hilang SEBELUM berkas sampai ke server dan
 * tidak mungkin dipulihkan lagi.
 *
 * Templat dibuat sesuai cakupan: admin wilayah hanya melihat wilayahnya
 * sendiri di daftar dropdown dan daftar titik, super admin melihat semuanya.
 */
class KaryawanTemplate
{
    /** Banyak baris kosong yang sudah diformat, supaya pengguna tinggal mengisi. */
    public const BARIS_KOSONG = 300;

    public const LEMBAR = 'Karyawan';

    /**
     * Susunan kolom templat. Urutan menentukan huruf kolom (A, B, C, …).
     *
     * `wajib` hanya dipakai untuk pewarnaan judul dan teks petunjuk; aturan
     * yang sebenarnya berlaku ada di KaryawanImpor.
     */
    public const KOLOM = [
        ['kunci' => 'nik', 'judul' => 'NIK', 'lebar' => 20],
        ['kunci' => 'nama', 'judul' => 'Nama', 'lebar' => 26],
        ['kunci' => 'email', 'judul' => 'Email', 'lebar' => 30],
        ['kunci' => 'wilayah', 'judul' => 'Wilayah', 'lebar' => 28],
        ['kunci' => 'titik', 'judul' => 'Titik Proyek', 'lebar' => 30],
        ['kunci' => 'jabatan', 'judul' => 'Jabatan', 'lebar' => 22],
        ['kunci' => 'unit', 'judul' => 'Unit Kerja', 'lebar' => 22],
        ['kunci' => 'status', 'judul' => 'Status', 'lebar' => 14],
        ['kunci' => 'nip', 'judul' => 'NIP', 'lebar' => 22],
        ['kunci' => 'gol', 'judul' => 'Golongan', 'lebar' => 12],
        ['kunci' => 'regu', 'judul' => 'Regu', 'lebar' => 14],
        ['kunci' => 'is_shift', 'judul' => 'Shift', 'lebar' => 10],
        ['kunci' => 'hari_kerja', 'judul' => 'Hari Kerja', 'lebar' => 22],
        ['kunci' => 'sandi', 'judul' => 'Sandi Awal', 'lebar' => 16],
    ];

    /** Kolom yang wajib diisi, per cakupan. Wilayah tidak wajib bagi admin wilayah. */
    public static function kolomWajib(?int $scopeRegionId): array
    {
        $wajib = ['nik', 'nama', 'email', 'titik', 'jabatan', 'unit', 'status'];

        if ($scopeRegionId === null) {
            $wajib[] = 'wilayah';
        }

        return $wajib;
    }

    /** Huruf kolom untuk sebuah kunci, mis. 'nik' → 'A'. */
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

        self::lembarIsian($spreadsheet, $scopeRegionId, $regions);
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

    private static function lembarIsian(Spreadsheet $spreadsheet, ?int $scopeRegionId, $regions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::LEMBAR);

        $wajib = self::kolomWajib($scopeRegionId);

        foreach (self::KOLOM as $i => $kolom) {
            $huruf = self::huruf($kolom['kunci']);
            $sheet->setCellValue("{$huruf}1", $kolom['judul']);
            $sheet->getColumnDimension($huruf)->setWidth($kolom['lebar']);
        }

        $akhir = self::huruf(self::KOLOM[array_key_last(self::KOLOM)]['kunci']);

        $judul = $sheet->getStyle("A1:{$akhir}1");
        $judul->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $judul->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
        $judul->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $judul->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Judul kolom wajib diberi penanda bintang agar jelas mana yang harus diisi.
        foreach ($wajib as $kunci) {
            $huruf = self::huruf($kunci);
            $sheet->setCellValue("{$huruf}1", self::judulKolom($kunci).' *');
        }

        $barisAkhir = self::BARIS_KOSONG + 1;

        // NIK dan NIP sebagai Teks: tanpa ini Excel memotong digit terakhirnya.
        foreach (['nik', 'nip'] as $kunci) {
            $huruf = self::huruf($kunci);
            $sheet->getStyle("{$huruf}2:{$huruf}{$barisAkhir}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $sheet->getStyle("A2:{$akhir}{$barisAkhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Status: daftar tertutup sesuai kolom enum di basis data.
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

    private static function judulKolom(string $kunci): string
    {
        foreach (self::KOLOM as $kolom) {
            if ($kolom['kunci'] === $kunci) {
                return $kolom['judul'];
            }
        }

        return $kunci;
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
            ['3. Jangan menambah kolom baru. Kolom yang tidak dipakai boleh dikosongkan.', 'teks'],
            ['4. Kolom NIK dan NIP sudah diformat sebagai Teks — biarkan begitu.', 'teks'],
            ['   Bila diubah ke format Angka, Excel akan memotong digit terakhir dan data tidak bisa dipulihkan.', 'catatan'],
            ['5. Nama wilayah dan nama titik harus sama persis dengan daftar di lembar "Daftar".', 'teks'],
            ['6. Simpan berkas, lalu unggah di menu Karyawan → Impor.', 'teks'],
            ['', ''],
            ['Kolom wajib', 'subjudul'],
            ['NIK — 16 digit angka.', 'teks'],
            ['Nama — nama lengkap karyawan.', 'teks'],
            ['Email — dipakai untuk login, harus belum terdaftar.', 'teks'],
            ['Titik Proyek — satu karyawan hanya boleh di satu titik.', 'teks'],
            ['Jabatan, Unit Kerja — sesuai data kepegawaian.', 'teks'],
            ['Status — PNS, PPPK, Kontrak, atau Honorer.', 'teks'],
            [$wilayahWajib
                ? 'Wilayah — wajib diisi.'
                : 'Wilayah — boleh dikosongkan; otomatis memakai wilayah Anda.', 'teks'],
            ['', ''],
            ['Kolom opsional', 'subjudul'],
            ['NIP, Golongan — boleh dikosongkan.', 'teks'],
            ['Regu — label bebas, mis. "Regu A". Kosongkan bila tidak memakai regu.', 'teks'],
            ['Shift — isi "Ya" bila karyawan bekerja shift, selain itu "Tidak" atau kosongkan.', 'teks'],
            ['Hari Kerja — mis. "1,2,3,4,5" (Senin–Jumat) atau "Senin-Jumat".', 'teks'],
            ['   Kosongkan bila karyawan mengikuti hari kerja umum.', 'catatan'],
            ['Sandi Awal — kosongkan bila sandi awal ingin disamakan dengan NIK.', 'teks'],
            ['', ''],
            ['Catatan', 'subjudul'],
            ['Sandi awal karyawan baru disamakan dengan NIK dan wajib diganti saat login pertama.', 'catatan'],
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
