<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Region;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Templat XLSX untuk impor akun karyawan.
 *
 * Lembar isian hanya memuat enam kolom, disalin huruf demi huruf dari berkas
 * yang biasa dikirim PPK (NAMA, NIK/NIP, STATUS PEGAWAI, SATKER, PPK, EMAIL).
 * Tujuannya supaya berkas lama tidak perlu diubah sama sekali: salin isinya ke
 * templat ini, dan kolom A–F langsung cocok. Kolom tambahan milik aplikasi
 * (wilayah, titik proyek, regu, shift, dan seterusnya) semuanya opsional, jadi
 * tidak dicetak — cukup diatur dari halaman impor atau disunting setelahnya.
 *
 * Kolom NIK/NIP sengaja berformat Teks. NIK 16 digit dan NIP 18 digit melampaui
 * presisi angka Excel (2^53), sehingga bila kolomnya berformat angka, digit
 * terakhir sudah hilang SEBELUM berkas sampai ke server dan tidak mungkin
 * dipulihkan lagi.
 *
 * Templat dibuat sesuai cakupan: admin wilayah hanya melihat wilayahnya sendiri
 * di lembar "Daftar", super admin melihat semuanya.
 */
class KaryawanTemplate
{
    /** Banyak baris kosong yang sudah diformat, supaya pengguna tinggal mengisi. */
    public const BARIS_KOSONG = 300;

    public const LEMBAR = 'Karyawan';

    /**
     * Susunan kolom yang dikenali impor. Urutan menentukan huruf kolom
     * (A, B, C, …) pada lembar isian.
     *
     * `wajib` dipakai untuk pewarnaan judul, teks petunjuk, dan aturan yang
     * berlaku di KaryawanImpor. Judul kolom sengaja TIDAK diberi tanda bintang:
     * satu karakter tambahan saja membuat judulnya tidak lagi sama persis
     * dengan berkas PPK.
     *
     * `tampil` bernilai false untuk kolom yang tidak dicetak di templat, tetapi
     * tetap dibaca bila berkasnya memuatnya.
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

        // ── Kolom tambahan, dikenali tetapi tidak dicetak ───────────────
        ['kunci' => 'wilayah', 'judul' => 'Wilayah', 'lebar' => 24, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'titik', 'judul' => 'Titik Proyek', 'lebar' => 30, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'nip', 'judul' => 'NIP', 'lebar' => 22, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'gol', 'judul' => 'Golongan', 'lebar' => 12, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'status', 'judul' => 'Status Kepegawaian', 'lebar' => 20, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'regu', 'judul' => 'Regu', 'lebar' => 14, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'is_shift', 'judul' => 'Shift', 'lebar' => 10, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'hari_kerja', 'judul' => 'Hari Kerja', 'lebar' => 22, 'wajib' => false, 'tampil' => false],
        ['kunci' => 'sandi', 'judul' => 'Sandi Awal', 'lebar' => 16, 'wajib' => false, 'tampil' => false],
    ];

    /**
     * Kolom yang benar-benar dicetak di lembar isian.
     *
     * @return list<array<string, mixed>>
     */
    public static function kolomLembar(): array
    {
        return array_values(array_filter(
            self::KOLOM,
            fn (array $kolom) => ($kolom['tampil'] ?? true) !== false
        ));
    }

    /**
     * Kunci kolom yang harus ada di baris 1 berkas unggahan.
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

        self::lembarIsian($spreadsheet);
        self::lembarDaftar($spreadsheet, $regions);
        self::lembarPetunjuk($spreadsheet);

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

    private static function lembarIsian(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::LEMBAR);

        $kolomLembar = self::kolomLembar();

        foreach ($kolomLembar as $kolom) {
            $huruf = self::huruf($kolom['kunci']);
            $sheet->setCellValue("{$huruf}1", $kolom['judul']);
            $sheet->getColumnDimension($huruf)->setWidth($kolom['lebar']);
        }

        $akhir = self::huruf($kolomLembar[array_key_last($kolomLembar)]['kunci']);

        // Judul kolom wajib berlatar gelap, kolom opsional berlatar abu muda.
        // Perbedaan warna inilah pengganti tanda bintang, supaya judulnya tetap
        // sama persis dengan berkas PPK.
        foreach ($kolomLembar as $kolom) {
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

        // NIK/NIP sebagai Teks: tanpa ini Excel memotong digit terakhirnya.
        $huruf = self::huruf('nik');
        $sheet->getStyle("{$huruf}2:{$huruf}{$barisAkhir}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        $sheet->getStyle("A2:{$akhir}{$barisAkhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$akhir}1");
        $sheet->setSelectedCell('A2');
    }

    /**
     * Lembar rujukan: daftar titik proyek per wilayah.
     */
    private static function lembarDaftar(Spreadsheet $spreadsheet, $regions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Daftar');

        $sheet->setCellValue('A1', 'Wilayah');
        $sheet->setCellValue('B1', 'Kantor');
        $sheet->setCellValue('C1', 'Titik Proyek');

        $baris = 2;
        foreach ($regions as $region) {
            foreach ($region->sites->sortBy('nama_lokasi') as $site) {
                $sheet->setCellValue("A{$baris}", $region->name);
                $sheet->setCellValue("B{$baris}", $region->kantor_name);
                $sheet->setCellValueExplicit("C{$baris}", $site->nama_lokasi, DataType::TYPE_STRING);
                $baris++;
            }
        }

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->setAutoFilter('A1:C'.max($baris - 1, 1));
        $sheet->freezePane('A2');
    }

    private static function lembarPetunjuk(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Petunjuk');

        $baris = [
            ['Cara mengisi templat impor akun karyawan', 'judul'],
            ['', ''],
            ['1. Isi data pada lembar "Karyawan", mulai baris 2.', 'teks'],
            ['2. Jangan mengubah, menghapus, atau mengurutkan ulang baris judul di baris 1.', 'teks'],
            ['3. Judul kolom berlatar gelap wajib diisi; judul berlatar abu-abu boleh dikosongkan.', 'teks'],
            ['4. Jangan menambah kolom baru di depan. Kolom tambahan boleh diletakkan paling kanan.', 'teks'],
            ['5. Kolom NIK/NIP sudah diformat sebagai Teks — biarkan begitu.', 'teks'],
            ['   Bila diubah ke format Angka, Excel akan memotong digit terakhir dan data tidak bisa dipulihkan.', 'catatan'],
            ['6. Wilayah dan titik proyek dipilih di halaman impor, tidak di berkas ini.', 'teks'],
            ['7. Simpan berkas, lalu unggah di menu Karyawan → Impor.', 'teks'],
            ['', ''],
            ['Kolom pada lembar "Karyawan"', 'subjudul'],
            ['NAMA — nama lengkap karyawan.', 'teks'],
            ['NIK/NIP — boleh dikosongkan, dan boleh kurang dari 16 digit. Isi apa adanya.', 'teks'],
            ['STATUS PEGAWAI — bebas diisi, mis. "Petugas OP", "Tenaga Pendukung", "Konsultan Individu".', 'teks'],
            ['SATKER — satuan kerja karyawan, mis. "OP SDA".', 'teks'],
            ['PPK — label PPK karyawan, mis. "PPK OP SDA II". Hanya keterangan.', 'teks'],
            ['EMAIL — dipakai untuk login, harus belum terdaftar, dan harus unik.', 'teks'],
            ['', ''],
            ['Kolom lain dari berkas sendiri', 'subjudul'],
            ['Berkas milik PPK boleh diunggah apa adanya. Bila berkas itu memuat kolom tambahan', 'teks'],
            ['(Wilayah, Titik Proyek, NIP, Golongan, Status Kepegawaian, Regu, Shift, Hari Kerja, Sandi Awal),', 'teks'],
            ['isinya tetap dibaca. Kolom yang tidak ada cukup diatur dari halaman impor.', 'teks'],
            ['', ''],
            ['Catatan', 'subjudul'],
            ['Sandi awal karyawan baru wajib diganti saat login pertama.', 'catatan'],
            ['Sandi awal bawaan untuk semua akun baru adalah '.Employee::SANDI_BAWAAN.'.', 'catatan'],
            ['Bila sumber sandi dipilih NIK dan NIK-nya kosong, sandi awal memakai email karyawan.', 'catatan'],
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
