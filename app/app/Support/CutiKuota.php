<?php

namespace App\Support;

use App\Models\AttendanceSetting;
use App\Models\Holiday;
use App\Models\Leave;
use Illuminate\Support\Carbon;

/**
 * CutiKuota — hitung dan tegakkan kuota cuti tahunan.
 *
 * Aturan yang dipakai (kuota besarannya bisa diubah Super Admin lewat
 * /super-admin/settings → "Kuota Cuti Tahunan"):
 *
 *  - Hanya jenis "Tahunan" yang memotong kuota. Sakit, Besar, dan Melahirkan
 *    tidak dibatasi kuota tahunan karena sifatnya berbasis bukti/masa kerja.
 *  - Kuota dihitung dalam HARI KERJA, bukan hari kalender: akhir pekan
 *    mengikuti setelan `hari_kerja` dan hari libur nasional dikecualikan.
 *  - "Menunggu" sudah memotong kuota supaya pengajuan tidak bisa di-spam,
 *    "Disetujui" tentu memotong, "Ditolak" tidak memotong.
 *  - Periode = tahun kalender. Pengajuan lintas tahun (mis. 30 Des → 2 Jan)
 *    dipecah per tahun dan divalidasi terhadap kuota tahun masing-masing.
 *
 * Helper ini sengaja bebas efek samping: controller yang memutuskan apa yang
 * dilakukan saat kuota tidak cukup (melempar 422), bukan helper.
 */
class CutiKuota
{
    /** Jenis cuti yang memotong kuota tahunan. */
    public const JENIS_BERKUOTA = ['Tahunan'];

    /** Status yang sudah dianggap "memakai" kuota. */
    public const STATUS_MEMOTONG = ['Menunggu', 'Disetujui'];

    /** Zona waktu perhitungan tanggal. */
    private const TZ = 'Asia/Makassar';

    public static function max(?AttendanceSetting $settings = null): int
    {
        $settings ??= AttendanceSetting::first();
        $max = (int) ($settings?->cuti_tahunan_max ?? 0);

        return $max > 0 ? $max : AttendanceSetting::CUTI_TAHUNAN_DEFAULT;
    }

    /** Total hari kerja pada rentang tanggal (inklusif kedua ujung). */
    public static function hariKerja(string $mulai, string $selesai, ?AttendanceSetting $settings = null): int
    {
        return array_sum(self::hariKerjaPerTahun($mulai, $selesai, $settings));
    }

    /**
     * Hari kerja pada rentang, dipecah per tahun kalender.
     *
     * @return array<int,int> tahun => jumlah hari kerja
     */
    public static function hariKerjaPerTahun(string $mulai, string $selesai, ?AttendanceSetting $settings = null): array
    {
        $awal = Carbon::parse($mulai, self::TZ)->startOfDay();
        $akhir = Carbon::parse($selesai, self::TZ)->startOfDay();
        if ($akhir->lt($awal)) {
            return [];
        }

        $settings ??= AttendanceSetting::first();
        $hariKerja = $settings?->hariKerjaList() ?? AttendanceSetting::HARI_KERJA_DEFAULT;

        $libur = Holiday::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn ($t) => Carbon::parse($t)->format('Y-m-d'))
            ->flip();

        $hasil = [];
        for ($d = $awal->copy(); $d->lte($akhir); $d->addDay()) {
            if (! in_array((string) $d->dayOfWeekIso, array_map('strval', $hariKerja), true)) {
                continue;
            }
            if ($libur->has($d->format('Y-m-d'))) {
                continue;
            }
            $hasil[$d->year] = ($hasil[$d->year] ?? 0) + 1;
        }

        return $hasil;
    }

    /**
     * Hari kerja cuti Tahunan yang sudah memotong kuota pada satu tahun.
     * Cuti yang melewati batas tahun hanya dihitung bagian yang masuk tahun itu.
     */
    public static function terpakai(int $employeeId, int $year, ?int $kecualiLeaveId = null, ?AttendanceSetting $settings = null): int
    {
        $awalTahun = Carbon::create($year, 1, 1, 0, 0, 0, self::TZ);
        $akhirTahun = Carbon::create($year, 12, 31, 0, 0, 0, self::TZ);

        $leaves = Leave::query()
            ->where('employee_id', $employeeId)
            ->whereIn('jenis', self::JENIS_BERKUOTA)
            ->whereIn('status', self::STATUS_MEMOTONG)
            ->when($kecualiLeaveId, fn ($q) => $q->whereKeyNot($kecualiLeaveId))
            ->where('mulai', '<=', $akhirTahun->toDateString())
            ->where('selesai', '>=', $awalTahun->toDateString())
            ->get(['id', 'mulai', 'selesai']);

        $total = 0;
        foreach ($leaves as $l) {
            $mulai = Carbon::parse($l->mulai, self::TZ)->max($awalTahun);
            $selesai = Carbon::parse($l->selesai, self::TZ)->min($akhirTahun);
            $total += self::hariKerja($mulai->toDateString(), $selesai->toDateString(), $settings);
        }

        return $total;
    }

    /** Ringkasan kuota satu karyawan untuk satu tahun. */
    public static function ringkasan(int $employeeId, int $year, ?AttendanceSetting $settings = null): array
    {
        $settings ??= AttendanceSetting::first();
        $max = self::max($settings);
        $terpakai = self::terpakai($employeeId, $year, null, $settings);

        return [
            'tahun' => $year,
            'max' => $max,
            'terpakai' => $terpakai,
            'sisa' => max(0, $max - $terpakai),
        ];
    }

    /**
     * Periksa apakah pengajuan baru melebihi sisa kuota.
     *
     * @return array<int,array{tahun:int,diminta:int,sisa:int}> daftar pelanggaran per tahun; kosong = lolos
     */
    public static function pelanggaran(int $employeeId, string $jenis, string $mulai, string $selesai, ?AttendanceSetting $settings = null): array
    {
        if (! in_array($jenis, self::JENIS_BERKUOTA, true)) {
            return [];
        }

        $settings ??= AttendanceSetting::first();
        $max = self::max($settings);
        $pelanggaran = [];

        foreach (self::hariKerjaPerTahun($mulai, $selesai, $settings) as $tahun => $diminta) {
            $sisa = max(0, $max - self::terpakai($employeeId, $tahun, null, $settings));
            if ($diminta > $sisa) {
                $pelanggaran[] = ['tahun' => $tahun, 'diminta' => $diminta, 'sisa' => $sisa];
            }
        }

        return $pelanggaran;
    }

    /** Pesan galat siap pakai untuk pelanggaran pertama. */
    public static function pesanPelanggaran(array $pelanggaran): string
    {
        $p = $pelanggaran[0];

        return "Sisa kuota cuti tahunan {$p['tahun']} hanya {$p['sisa']} hari kerja, "
            ."sedangkan pengajuan ini {$p['diminta']} hari kerja.";
    }
}
