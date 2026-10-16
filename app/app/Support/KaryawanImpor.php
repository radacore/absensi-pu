<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

/**
 * Mesin impor akun karyawan dari berkas Excel.
 *
 * Alur pemakaian:
 *   1. KaryawanImpor::periksa($path, $scopeRegionId, $mode) → ringkasan + baris
 *      untuk ditampilkan sebagai pratinjau. Tidak menulis apa pun ke basis data.
 *   2. KaryawanImpor::terapkan($path, $scopeRegionId, $opsi) → memeriksa ulang
 *      berkas yang sama lalu menyimpan baris yang lolos, dalam satu transaksi.
 *
 * Berkas diperiksa ulang saat menyimpan, bukan memakai hasil pratinjau yang
 * dikirim balik oleh peramban. Dengan begitu klien tidak pernah bisa menyuruh
 * server menyimpan data yang tidak pernah lolos pemeriksaan.
 */
class KaryawanImpor
{
    /** Batas jumlah baris data per berkas. */
    public const MAX_BARIS = 1000;

    /** Batas ukuran berkas unggahan (bita). */
    public const MAX_BITA = 5 * 1024 * 1024;

    /** Batas ukuran isi berkas setelah arsip zip dibuka (bita). */
    public const MAX_BITA_MENGEMBANG = 200 * 1024 * 1024;

    /** Mode impor: apa yang terjadi bila NIK-nya sudah terdaftar. */
    public const MODE = ['tambah', 'perbarui', 'gabungan'];

    /** Sumber sandi awal untuk karyawan baru. */
    public const SANDI = ['nik', 'kolom'];

    public const AKSI = ['tambah', 'perbarui', 'lewati', 'gagal'];

    /**
     * Judul kolom yang diterima → kunci internal. Semuanya sudah dinormalkan
     * (huruf kecil, tanpa spasi/tanda baca), jadi "Titik Proyek", "titik
     * proyek", dan "TITIKPROYEK" sama-sama cocok.
     */
    private const ALIAS = [
        'nik' => ['nik', 'nomorindukkependudukan', 'noindukkependudukan', 'nomorindukkaryawan'],
        'nama' => ['nama', 'namalengkap', 'namakaryawan', 'namapegawai'],
        'email' => ['email', 'surel', 'alamatemail'],
        'nip' => ['nip'],
        'gol' => ['golongan', 'gol', 'golruang'],
        'jabatan' => ['jabatan'],
        'unit' => ['unit', 'unitkerja'],
        'status' => ['status', 'statuskepegawaian'],
        'wilayah' => ['wilayah', 'kabupaten', 'kabupatenkota', 'kabkota', 'region'],
        'titik' => ['titikproyek', 'titik', 'lokasi', 'namalokasi', 'namatitik', 'titiklokasi'],
        'regu' => ['regu', 'grup', 'grupregu'],
        'is_shift' => ['shift', 'pekerjashift', 'isshift', 'penugasshift', 'shiftkerja'],
        'hari_kerja' => ['harikerja', 'harikerjakhusus', 'harikerjaperorangan'],
        'sandi' => ['sandi', 'sandiawal', 'sandiakun', 'katasandi', 'password'],
    ];

    /** Nama hari (sudah dinormalkan) → nomor ISO-8601, sama seperti pengaturan umum. */
    private const HARI = [
        '1' => '1', 'senin' => '1', 'mon' => '1', 'monday' => '1',
        '2' => '2', 'selasa' => '2', 'tue' => '2', 'tuesday' => '2',
        '3' => '3', 'rabu' => '3', 'wed' => '3', 'wednesday' => '3',
        '4' => '4', 'kamis' => '4', 'thu' => '4', 'thursday' => '4',
        '5' => '5', 'jumat' => '5', 'fri' => '5', 'friday' => '5',
        '6' => '6', 'sabtu' => '6', 'sat' => '6', 'saturday' => '6',
        '7' => '7', 'minggu' => '7', 'ahad' => '7', 'sun' => '7', 'sunday' => '7',
    ];

    private const STATUS = ['pns' => 'PNS', 'pppk' => 'PPPK', 'kontrak' => 'Kontrak', 'honorer' => 'Honorer'];

    private const BENAR = ['ya', 'y', 'iya', 'yes', 'true', '1', 'shift'];

    private const SALAH = ['tidak', 't', 'tdk', 'engga', 'enggak', 'no', 'false', '0', 'non', 'nonshift', 'bukan'];

    /**
     * Periksa berkas tanpa menyimpan apa pun.
     *
     * @return array{ringkasan: array, baris: array}
     */
    public static function periksa(string $path, ?int $scopeRegionId, string $mode): array
    {
        $analisa = self::analisa($path, $scopeRegionId, ['mode' => $mode]);

        return [
            'ringkasan' => $analisa['ringkasan'],
            'baris' => array_map(self::untukTampilan(...), $analisa['baris']),
        ];
    }

    /**
     * Periksa ulang berkas lalu simpan baris yang lolos, dalam satu transaksi.
     *
     * @param  array{mode: string, sandi: string, wajib_ganti: bool}  $opsi
     * @return array{ringkasan: array, baris: array}
     */
    public static function terapkan(string $path, ?int $scopeRegionId, array $opsi): array
    {
        $mode = in_array($opsi['mode'] ?? '', self::MODE, true) ? $opsi['mode'] : 'tambah';
        $sumber = in_array($opsi['sandi'] ?? '', self::SANDI, true) ? $opsi['sandi'] : 'nik';
        $wajibGanti = (bool) ($opsi['wajib_ganti'] ?? true);

        $analisa = self::analisa($path, $scopeRegionId, ['mode' => $mode, 'sandi' => $sumber]);
        $ringkasan = $analisa['ringkasan'];

        DB::transaction(function () use ($analisa, $sumber, $wajibGanti) {
            foreach ($analisa['baris'] as $baris) {
                $data = $baris['data'];
                if ($data === null) {
                    continue;
                }

                if ($baris['aksi'] === 'tambah') {
                    Employee::create([
                        'nik' => $data['nik'],
                        'nip' => $data['nip'],
                        'name' => $data['nama'],
                        'email' => $data['email'],
                        'golongan' => $data['gol'],
                        'jabatan' => $data['jabatan'],
                        'unit_kerja' => $data['unit'],
                        'status_kepegawaian' => $data['status'],
                        'region_id' => $data['region_id'],
                        'site_id' => $data['site_id'],
                        'is_shift' => $data['is_shift'],
                        'hari_kerja' => $data['hari_kerja'],
                        'regu' => $data['regu'],
                        'password' => $sumber === 'kolom' && $data['sandi'] !== null
                            ? $data['sandi']
                            : $data['nik'],
                        'must_change_password' => $wajibGanti,
                    ]);
                } elseif ($baris['aksi'] === 'perbarui' && $data['employee'] !== null) {
                    // Sandi sengaja tidak diubah saat memperbarui: mengubah data
                    // kepegawaian tidak boleh sekaligus mengunci orang dari akunnya.
                    $data['employee']->update([
                        'nip' => $data['nip'],
                        'name' => $data['nama'],
                        'email' => $data['email'],
                        'golongan' => $data['gol'],
                        'jabatan' => $data['jabatan'],
                        'unit_kerja' => $data['unit'],
                        'status_kepegawaian' => $data['status'],
                        'region_id' => $data['region_id'],
                        'site_id' => $data['site_id'],
                        'is_shift' => $data['is_shift'],
                        'hari_kerja' => $data['hari_kerja'],
                        'regu' => $data['regu'],
                    ]);
                }
            }
        });

        return [
            'ringkasan' => $ringkasan,
            'baris' => array_map(self::untukTampilan(...), $analisa['baris']),
        ];
    }

    /**
     * Baca berkas, periksa tiap baris, lalu tentukan aksinya.
     *
     * @return array{ringkasan: array, baris: array}
     */
    private static function analisa(string $path, ?int $scopeRegionId, array $opsi): array
    {
        $spreadsheet = self::muat($path);
        $sheet = $spreadsheet->getSheet(0);
        $peta = self::petaKolom($sheet, $scopeRegionId);
        $ctx = self::konteks($scopeRegionId);

        $mentah = self::bacaSemuaBaris($sheet, $peta);

        if ($mentah === []) {
            throw new KaryawanImporException('Berkas tidak berisi data. Isi baris di bawah judul kolom, lalu unggah ulang.');
        }

        // Tahap 1 — periksa tiap baris sendiri-sendiri (format, keberadaan wilayah/titik).
        $hasil = [];
        foreach ($mentah as $baris) {
            $hasil[] = self::periksaBaris($baris, $ctx, $opsi);
        }

        // Tahap 2 — bentrokan antar-baris: NIK/email/NIP kembar di dalam satu berkas.
        self::tandaiKembar($hasil);

        // Tahap 3 — bentrokan dengan data yang sudah ada di basis data.
        self::tandaiBentrokDatabase($hasil, $opsi['mode'] ?? 'tambah');

        $ringkasan = ['total' => count($hasil), 'tambah' => 0, 'perbarui' => 0, 'lewati' => 0, 'gagal' => 0];
        foreach ($hasil as $baris) {
            $ringkasan[$baris['aksi']]++;
        }

        return ['ringkasan' => $ringkasan, 'baris' => $hasil];
    }

    /**
     * Muat berkas jadi objek Spreadsheet, dengan penjagaan sebelum parsing.
     */
    private static function muat(string $path): Spreadsheet
    {
        $ukuran = @filesize($path);
        if ($ukuran === false || $ukuran <= 0) {
            throw new KaryawanImporException('Berkas kosong atau tidak terbaca.');
        }

        if ($ukuran > self::MAX_BITA) {
            throw new KaryawanImporException('Ukuran berkas melebihi '.round(self::MAX_BITA / 1024 / 1024).' MB.');
        }

        // XLSX adalah arsip zip. Berkas 5 MB bisa mengembang jadi ratusan MB,
        // jadi ukuran setelah dibuka diperiksa lebih dulu agar tidak menghabiskan memori.
        self::periksaArsipZip($path);

        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (\Throwable) {
            throw new KaryawanImporException('Berkas tidak dikenali. Simpan ulang sebagai Excel (.xlsx), lalu unggah lagi.');
        }

        if (! $reader instanceof Xlsx && ! $reader instanceof Xls) {
            throw new KaryawanImporException('Format berkas harus Excel (.xlsx).');
        }

        // Hanya nilai sel yang dibaca: gaya, gambar, dan rumus tidak diperlukan
        // dan memperbesar peluang berkas jahat lolos ke pemrosesan.
        $reader->setReadDataOnly(true);

        try {
            return $reader->load($path);
        } catch (\Throwable) {
            throw new KaryawanImporException('Berkas tidak bisa dibaca. Pastikan berkas Excel yang belum rusak.');
        }
    }

    private static function periksaArsipZip(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }

        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $total += (int) $stat['size'];
                if ($total > self::MAX_BITA_MENGEMBANG) {
                    throw new KaryawanImporException('Isi berkas terlalu besar setelah dibuka.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Petakan judul kolom di baris 1 → nomor kolom.
     *
     * @return array<string, int>
     */
    private static function petaKolom(Worksheet $sheet, ?int $scopeRegionId): array
    {
        $terakhir = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $peta = [];

        for ($kolom = 1; $kolom <= $terakhir; $kolom++) {
            $judul = self::norm((string) $sheet->getCell(Coordinate::stringFromColumnIndex($kolom).'1')->getValue());
            if ($judul === '') {
                continue;
            }

            foreach (self::ALIAS as $kunci => $alias) {
                if (! isset($peta[$kunci]) && in_array($judul, $alias, true)) {
                    $peta[$kunci] = $kolom;
                    break;
                }
            }
        }

        foreach (KaryawanTemplate::kolomWajib($scopeRegionId) as $kunci) {
            if (! isset($peta[$kunci])) {
                throw new KaryawanImporException(
                    'Kolom "'.self::judulKolom($kunci).'" tidak ditemukan di baris 1. '
                    .'Unduh templat terbaru dari menu Karyawan → Impor, lalu isi templat itu.'
                );
            }
        }

        return $peta;
    }

    private static function judulKolom(string $kunci): string
    {
        foreach (KaryawanTemplate::KOLOM as $kolom) {
            if ($kolom['kunci'] === $kunci) {
                return $kolom['judul'];
            }
        }

        return $kunci;
    }

    /**
     * Baca seluruh baris data. Baris yang semua kolomnya kosong dilewati.
     *
     * @return list<array{nilai: array<string, string>, angka: array<string, ?float>, baris: int}>
     */
    private static function bacaSemuaBaris(Worksheet $sheet, array $peta): array
    {
        $terakhir = $sheet->getHighestDataRow();
        $hasil = [];
        $dibaca = 0;

        for ($baris = 2; $baris <= $terakhir; $baris++) {
            $nilai = [];
            $angka = [];
            $ada = false;

            foreach ($peta as $kunci => $kolom) {
                $sel = $sheet->getCell(Coordinate::stringFromColumnIndex($kolom).$baris);
                $nilai[$kunci] = self::teksSel($sel);
                $angka[$kunci] = self::angkaSel($sel);
                if ($nilai[$kunci] !== '') {
                    $ada = true;
                }
            }

            if (! $ada) {
                continue;
            }

            if (++$dibaca > self::MAX_BARIS) {
                throw new KaryawanImporException('Jumlah baris data melebihi '.self::MAX_BARIS.'. Bagi berkas menjadi beberapa bagian.');
            }

            $hasil[] = ['nilai' => $nilai, 'angka' => $angka, 'baris' => $baris];
        }

        return $hasil;
    }

    private static function teksSel(Cell $sel): string
    {
        $nilai = $sel->getValue();

        if ($nilai === null) {
            return '';
        }

        if (is_bool($nilai)) {
            return $nilai ? 'Ya' : 'Tidak';
        }

        if (is_float($nilai)) {
            // 7371123456789012.0 → "7371123456789012", bukan "7.371E+15".
            return rtrim(rtrim(sprintf('%.10F', $nilai), '0'), '.');
        }

        return trim((string) $nilai);
    }

    private static function angkaSel(Cell $sel): ?float
    {
        $nilai = $sel->getValue();

        return is_int($nilai) || is_float($nilai) ? (float) $nilai : null;
    }

    /**
     * Data acuan yang dibaca sekali: cakupan wilayah, daftar titik, dan
     * karyawan yang sudah terdaftar (agar tidak ada satu pun kueri per baris).
     */
    private static function konteks(?int $scopeRegionId): array
    {
        $regions = Region::with('sites')
            ->when($scopeRegionId, fn ($q) => $q->where('id', $scopeRegionId))
            ->get();

        $petaWilayah = [];
        $petaTitik = [];
        $saranTitik = [];

        foreach ($regions as $region) {
            $petaWilayah[self::normWilayah($region->name)] = $region;
            $petaWilayah[self::norm($region->name)] = $region;

            $namaTitik = $region->sites->pluck('nama_lokasi')->sort()->values();

            $petaTitik[$region->id] = [];
            foreach ($region->sites as $site) {
                $petaTitik[$region->id][self::norm($site->nama_lokasi)] = $site;
            }

            $saranTitik[$region->id] = $namaTitik->take(8)->implode(', ')
                .($namaTitik->count() > 8 ? ', dan '.($namaTitik->count() - 8).' titik lainnya' : '');
        }

        return [
            'scope' => $scopeRegionId,
            'regionScope' => $scopeRegionId ? $regions->firstWhere('id', $scopeRegionId) : null,
            'petaWilayah' => $petaWilayah,
            'petaTitik' => $petaTitik,
            'saranTitik' => $saranTitik,
        ];
    }

    /**
     * Periksa satu baris. Mengembalikan data yang sudah bersih, atau daftar
     * alasan baris itu tidak bisa dipakai.
     */
    private static function periksaBaris(array $mentah, array $ctx, array $opsi): array
    {
        $n = $mentah['nilai'];
        $angka = $mentah['angka'];
        $pesan = [];

        // --- NIK: 16 digit, dan tidak boleh lewat format angka Excel ---
        $nik = '';
        $nikMentah = self::rapatkan($n['nik'] ?? '');
        if ($nikMentah === '') {
            $pesan[] = 'NIK wajib diisi.';
        } elseif (self::angkaBermasalah($angka['nik'])) {
            $pesan[] = 'NIK terbaca sebagai angka oleh Excel, padahal Excel hanya menyimpan 15 angka pertama dengan tepat — '
                .'digit terakhir NIK bisa sudah berubah. Blok kolom NIK, ubah format sel menjadi Teks, '
                .'ketik ulang NIK-nya, lalu unggah lagi.';
        } elseif (! preg_match('/^\d{16}$/', $nikMentah)) {
            $pesan[] = 'NIK harus 16 digit angka (terbaca: '.$nikMentah.').';
        } else {
            $nik = $nikMentah;
        }

        // --- Nama, jabatan, unit kerja ---
        $nama = self::potong($n['nama'] ?? '', 150);
        if ($nama === '') {
            $pesan[] = 'Nama wajib diisi.';
        }

        $jabatan = self::potong($n['jabatan'] ?? '', 150);
        if ($jabatan === '') {
            $pesan[] = 'Jabatan wajib diisi.';
        }

        $unit = self::potong($n['unit'] ?? '', 150);
        if ($unit === '') {
            $pesan[] = 'Unit Kerja wajib diisi.';
        }

        // --- Email: dipakai untuk login ---
        $email = self::potong($n['email'] ?? '', 255);
        if ($email === '') {
            $pesan[] = 'Email wajib diisi.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan[] = 'Email tidak sah (terbaca: '.$email.').';
        }

        // --- Status kepegawaian ---
        $statusMentah = self::potong($n['status'] ?? '', 40);
        $status = self::STATUS[self::norm($statusMentah)] ?? null;
        if ($status === null) {
            $pesan[] = 'Status harus PNS, PPPK, Kontrak, atau Honorer (terbaca: '
                .($statusMentah === '' ? 'kosong' : '"'.$statusMentah.'"').').';
        }

        // --- Wilayah ---
        $wilayahMentah = self::potong($n['wilayah'] ?? '', 120);
        $region = null;

        if ($ctx['scope'] !== null) {
            $region = $ctx['regionScope'];
            if ($wilayahMentah !== '' && self::normWilayah($wilayahMentah) !== self::normWilayah($region->name)) {
                $pesan[] = 'Wilayah "'.$wilayahMentah.'" di luar cakupan Anda ('.$region->name.').';
                $region = null;
            }
        } elseif ($wilayahMentah === '') {
            $pesan[] = 'Wilayah wajib diisi.';
        } else {
            $region = $ctx['petaWilayah'][self::normWilayah($wilayahMentah)]
                ?? $ctx['petaWilayah'][self::norm($wilayahMentah)]
                ?? null;
            if ($region === null) {
                $pesan[] = 'Wilayah "'.$wilayahMentah.'" tidak terdaftar. Lihat daftarnya di lembar "Daftar" pada templat.';
            }
        }

        // --- Titik proyek: harus benar-benar ada di wilayah baris itu ---
        $titikMentah = self::potong($n['titik'] ?? '', 150);
        $site = null;

        if ($region !== null) {
            if ($titikMentah === '') {
                $pesan[] = 'Titik Proyek wajib diisi.';
            } else {
                $site = $ctx['petaTitik'][$region->id][self::norm($titikMentah)] ?? null;
                if ($site === null) {
                    $pesan[] = 'Titik "'.$titikMentah.'" tidak ada di '.$region->name
                        .'. Yang tersedia: '.$ctx['saranTitik'][$region->id].'.';
                }
            }
        }

        // --- NIP opsional, tapi tidak boleh lewat format angka (18 digit > presisi Excel) ---
        $nipMentah = self::rapatkan($n['nip'] ?? '');
        $nip = null;
        if ($nipMentah !== '' && $nipMentah !== '-') {
            if (self::angkaBermasalah($angka['nip'])) {
                $pesan[] = 'NIP terbaca sebagai angka oleh Excel sehingga digitnya bisa sudah berubah. '
                    .'Ubah format kolom NIP menjadi Teks, ketik ulang NIP-nya, lalu unggah lagi.';
            } elseif (mb_strlen($nipMentah) > 30) {
                $pesan[] = 'NIP terlalu panjang.';
            } else {
                $nip = $nipMentah;
            }
        }

        // --- Kolom opsional lain ---
        $gol = self::potong($n['gol'] ?? '', 10);
        $gol = ($gol === '' || $gol === '-') ? null : $gol;

        $regu = self::potong($n['regu'] ?? '', 30);
        $regu = $regu === '' ? null : $regu;

        [$isShift, $galatShift] = self::bacaBoolean($n['is_shift'] ?? '');
        if ($galatShift !== null) {
            $pesan[] = $galatShift;
        }

        [$hariKerja, $galatHari] = self::bacaHariKerja($n['hari_kerja'] ?? '');
        if ($galatHari !== null) {
            $pesan[] = $galatHari;
        }

        // --- Sandi awal dari kolom, kalau memang itu sumbernya ---
        $sandi = null;
        if (($opsi['sandi'] ?? 'nik') === 'kolom') {
            $sandiMentah = trim($n['sandi'] ?? '');
            if ($sandiMentah !== '') {
                if (mb_strlen($sandiMentah) < 6) {
                    $pesan[] = 'Sandi Awal minimal 6 karakter.';
                } else {
                    $sandi = $sandiMentah;
                }
            }
        }

        $lengkap = $pesan === [] && $nik !== '' && $region !== null && $site !== null
            && $nama !== '' && $email !== '' && $status !== null;

        return [
            'baris' => $mentah['baris'],
            'aksi' => $lengkap ? 'tambah' : 'gagal',
            'pesan' => $pesan,
            'tampil' => [
                'nik' => $nik !== '' ? $nik : $nikMentah,
                'nama' => $nama !== '' ? $nama : self::potong($n['nama'] ?? '', 150),
                'email' => $email,
                'wilayah' => $region?->name ?? $wilayahMentah,
                'titik' => $site?->nama_lokasi ?? $titikMentah,
                'jabatan' => $jabatan,
                'unit' => $unit,
                'status' => $status ?? $statusMentah,
                'nip' => $nip,
                'gol' => $gol,
                'regu' => $regu,
                'isShift' => $isShift,
                'hariKerja' => $hariKerja,
            ],
            'data' => $lengkap ? [
                'nik' => $nik,
                'nip' => $nip,
                'nama' => $nama,
                'email' => $email,
                'gol' => $gol,
                'jabatan' => $jabatan,
                'unit' => $unit,
                'status' => $status,
                'region_id' => $region->id,
                'site_id' => $site->id,
                'is_shift' => $isShift,
                'hari_kerja' => $hariKerja,
                'regu' => $regu,
                'sandi' => $sandi,
                'employee' => null,
            ] : null,
        ];
    }

    /**
     * Tandai NIK, email, atau NIP yang muncul lebih dari sekali di berkas yang
     * sama. Semua baris yang terlibat ditandai, bukan hanya yang kedua, supaya
     * pengguna tahu persis baris mana saja yang harus dibereskan.
     */
    private static function tandaiKembar(array &$hasil): void
    {
        $peta = ['nik' => [], 'email' => [], 'nip' => []];

        foreach ($hasil as $i => $baris) {
            if ($baris['data'] === null) {
                continue;
            }
            foreach (['nik', 'email', 'nip'] as $kunci) {
                $nilai = $baris['data'][$kunci];
                if ($nilai !== null && $nilai !== '') {
                    $peta[$kunci][$nilai][] = $i;
                }
            }
        }

        $label = ['nik' => 'NIK', 'email' => 'Email', 'nip' => 'NIP'];

        foreach ($peta as $kunci => $daftar) {
            foreach ($daftar as $nilai => $indeks) {
                if (count($indeks) < 2) {
                    continue;
                }

                $nomorBaris = array_map(fn ($i) => $hasil[$i]['baris'], $indeks);

                foreach ($indeks as $i) {
                    $lain = array_values(array_diff($nomorBaris, [$hasil[$i]['baris']]));
                    $hasil[$i]['pesan'][] = $label[$kunci].' '.$nilai.' muncul di baris lain ('
                        .implode(', ', $lain).'). Setiap '.$label[$kunci].' hanya boleh sekali.';
                    $hasil[$i]['aksi'] = 'gagal';
                    $hasil[$i]['data'] = null;
                }
            }
        }
    }

    /**
     * Bandingkan dengan karyawan yang sudah ada, lalu tentukan aksi tiap baris
     * sesuai mode impor.
     */
    private static function tandaiBentrokDatabase(array &$hasil, string $mode): void
    {
        $nik = [];
        $email = [];
        $nip = [];

        foreach ($hasil as $baris) {
            if ($baris['data'] === null) {
                continue;
            }
            $nik[] = $baris['data']['nik'];
            $email[] = $baris['data']['email'];
            if ($baris['data']['nip'] !== null) {
                $nip[] = $baris['data']['nip'];
            }
        }

        $adaNik = $nik === [] ? collect() : Employee::whereIn('nik', $nik)->get()->keyBy('nik');
        $adaEmail = $email === [] ? collect() : Employee::whereIn('email', $email)->get()->keyBy('email');
        $adaNip = $nip === [] ? collect() : Employee::whereIn('nip', $nip)->get()->keyBy('nip');

        foreach ($hasil as $i => $baris) {
            if ($baris['data'] === null) {
                continue;
            }

            $data = $baris['data'];
            $tersimpan = $adaNik->get($data['nik']);

            // Email dan NIP hanya boleh dipakai satu orang. Kalau pemiliknya
            // orang yang sama (baris ini memang memperbarui dia), itu wajar.
            $pemilikEmail = $adaEmail->get($data['email']);
            if ($pemilikEmail !== null && $pemilikEmail->id !== $tersimpan?->id) {
                $hasil[$i]['pesan'][] = 'Email '.$data['email'].' sudah dipakai karyawan lain (NIK '.$pemilikEmail->nik.').';
            }

            if ($data['nip'] !== null) {
                $pemilikNip = $adaNip->get($data['nip']);
                if ($pemilikNip !== null && $pemilikNip->id !== $tersimpan?->id) {
                    $hasil[$i]['pesan'][] = 'NIP '.$data['nip'].' sudah dipakai karyawan lain (NIK '.$pemilikNip->nik.').';
                }
            }

            if ($hasil[$i]['pesan'] !== []) {
                $hasil[$i]['aksi'] = 'gagal';
                $hasil[$i]['data'] = null;

                continue;
            }

            if ($tersimpan === null) {
                if ($mode === 'perbarui') {
                    $hasil[$i]['aksi'] = 'lewati';
                    $hasil[$i]['pesan'][] = 'NIK belum terdaftar — dilewati karena mode impor "Perbarui saja".';
                } else {
                    $hasil[$i]['aksi'] = 'tambah';
                }

                continue;
            }

            if ($mode === 'tambah') {
                $hasil[$i]['aksi'] = 'lewati';
                $hasil[$i]['pesan'][] = 'NIK sudah terdaftar — dilewati karena mode impor "Tambah saja".';

                continue;
            }

            $hasil[$i]['aksi'] = 'perbarui';
            $hasil[$i]['data']['employee'] = $tersimpan;
        }
    }

    /**
     * Bentuk baris untuk dikirim ke peramban: tanpa objek model dan tanpa
     * kunci internal.
     */
    private static function untukTampilan(array $baris): array
    {
        return [
            'baris' => $baris['baris'],
            'aksi' => $baris['aksi'],
            'pesan' => $baris['pesan'],
            ...$baris['tampil'],
        ];
    }

    /** "Ya"/"Tidak"/"1"/"0" → boolean. */
    private static function bacaBoolean(string $mentah): array
    {
        $nilai = self::norm($mentah);

        if ($nilai === '') {
            return [false, null];
        }

        if (in_array($nilai, self::BENAR, true)) {
            return [true, null];
        }

        if (in_array($nilai, self::SALAH, true)) {
            return [false, null];
        }

        return [false, 'Kolom Shift harus diisi "Ya" atau "Tidak" (terbaca: "'.$mentah.'").'];
    }

    /**
     * "1,2,3,4,5", "12345", "Senin-Jumat", atau nama hari → daftar nomor hari.
     * Kosong berarti karyawan mengikuti hari kerja umum (null).
     */
    private static function bacaHariKerja(string $mentah): array
    {
        $teks = trim($mentah);

        if ($teks === '') {
            return [null, null];
        }

        if (in_array(self::norm($teks), ['umum', 'semua', 'default', 'ikutumum', 'harikerjaumum'], true)) {
            return [null, null];
        }

        // Bentuk rapat tanpa pemisah: "12345".
        if (preg_match('/^[1-7]+$/', $teks)) {
            $hari = array_values(array_unique(str_split($teks)));
            sort($hari);

            return [$hari, null];
        }

        $bagian = preg_split('/[,;\/]+|\s+dan\s+/u', $teks) ?: [];
        $hari = [];

        foreach ($bagian as $potongan) {
            $potongan = trim($potongan);
            if ($potongan === '') {
                continue;
            }

            // Rentang: "Senin-Jumat" atau "1-5".
            if (preg_match('/^(.+?)\s*[–-]\s*(.+)$/u', $potongan, $cocok)) {
                $awal = self::HARI[self::norm($cocok[1])] ?? null;
                $akhir = self::HARI[self::norm($cocok[2])] ?? null;

                if ($awal === null || $akhir === null || (int) $awal > (int) $akhir) {
                    return [null, 'Rentang hari kerja "'.$potongan.'" tidak dikenal.'];
                }

                for ($h = (int) $awal; $h <= (int) $akhir; $h++) {
                    $hari[] = (string) $h;
                }

                continue;
            }

            $nomor = self::HARI[self::norm($potongan)] ?? null;
            if ($nomor === null) {
                return [null, 'Hari kerja "'.$potongan.'" tidak dikenal. Pakai angka 1–7 atau nama hari, mis. "1,2,3,4,5".'];
            }

            $hari[] = $nomor;
        }

        if ($hari === []) {
            return [null, null];
        }

        $hari = array_values(array_unique($hari));
        sort($hari);

        return [$hari, null];
    }

    /**
     * Angka yang tidak boleh dipercaya bila datang dari sel bertipe angka.
     *
     * Excel hanya menyimpan 15 angka penting dengan tepat. NIK 16 digit dan NIP
     * 18 digit sudah melewati batas itu, sehingga digit terakhirnya bisa
     * berubah tanpa peringatan apa pun (7371123456780001 → 7371123456780000).
     * Sel seperti ini selalu ditolak dan pengguna diminta mengubah kolomnya
     * menjadi Teks, bukan dicoba ditebak.
     */
    private static function angkaBermasalah(?float $nilai): bool
    {
        // 1e15 adalah bilangan 16 digit terkecil; di bawah itu aman.
        return $nilai !== null && abs($nilai) >= 1e15;
    }

    /** Buang spasi dan tanda kutip agar NIK/NIP yang tersalin rapi tetap terbaca. */
    private static function rapatkan(string $nilai): string
    {
        return str_replace([' ', "'", '’', "\u{00A0}"], '', trim($nilai));
    }

    private static function potong(string $nilai, int $maks): string
    {
        return mb_substr(trim($nilai), 0, $maks);
    }

    /** Huruf kecil, tanpa spasi dan tanda baca: "Kab. Gowa" → "kabgowa". */
    private static function norm(string $nilai): string
    {
        $nilai = mb_strtolower(trim($nilai));

        return preg_replace('/[^a-z0-9]+/u', '', $nilai) ?? '';
    }

    /** Sama seperti norm(), tetapi awalan "Kab./Kabupaten/Kota" dibuang. */
    private static function normWilayah(string $nilai): string
    {
        $n = self::norm($nilai);

        foreach (['kabupaten', 'kab', 'kota'] as $awalan) {
            if ($n !== $awalan && str_starts_with($n, $awalan)) {
                return substr($n, strlen($awalan));
            }
        }

        return $n;
    }
}
