<?php

namespace App\Support;

use App\Models\Attendance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * RekapPdf — jembatan antara data RekapPresenter dan berkas PDF.
 *
 * Dipakai bersama oleh admin/super admin (rekap perorangan karyawan) dan
 * karyawan (rekap dirinya sendiri) supaya berkas PDF yang diunduh kedua pihak
 * identik, dan supaya penyetelan dompdf hanya ada di satu tempat.
 */
class RekapPdf
{
    /**
     * Susun respons unduhan PDF dari data rekap.
     *
     * @param  array<string, mixed>  $rekap  hasil RekapPresenter::buildRekap()
     * @param  string  $bulan  "YYYY-MM" (dipakai untuk nama berkas)
     */
    public static function unduh(array $rekap, string $bulan): Response
    {
        $pdf = Pdf::loadView('pdf.rekap-presensi', self::data($rekap));

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOptions([
            'dpi' => 96,
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);

        // NIK kini boleh kosong (kebijakan impor PPK), jadi nama berkas jatuh ke id.
        $penanda = $rekap['karyawan']['nik'] ?: $rekap['karyawan']['id'];

        return $pdf->download('rekap-presensi-'.$penanda.'-'.$bulan.'.pdf');
    }

    /**
     * Data untuk view `pdf.rekap-presensi`. Dipisah supaya template bisa diuji
     * tanpa menjalankan dompdf.
     *
     * @param  array<string, mixed>  $rekap
     * @return array<string, mixed>
     */
    public static function data(array $rekap): array
    {
        $s = $rekap['settings'];

        $detail = self::denganSwafoto($rekap['detailHari']);

        return [
            'k' => $rekap['karyawan'],
            'p' => $rekap['periode'],
            'd' => $rekap['distribusi'],
            'm' => $rekap['metrikHadir'],
            's' => $s,
            'harian' => $rekap['harian'],
            'keterlambatan' => $rekap['keterlambatan'],
            'dispensasi' => $rekap['dispensasi'],
            'totalKeterlambatan' => $rekap['total_keterlambatan'],
            'totalPsw' => $rekap['total_psw'],
            'adaPulangCepat' => $rekap['ada_pulang_cepat'],
            // Lembur dan WFA adalah angka tersendiri, tidak digabung ke
            // distribusi kehadiran (A8, C4).
            'lemburMenit' => $rekap['total_lembur_menit'] ?? 0,
            'hariWfa' => $rekap['hari_wfa'] ?? 0,
            'hariDiLuarTitik' => $rekap['hari_di_luar_titik'] ?? 0,
            'logoSrc' => self::logoSrc(),
            'tanggalCetak' => self::tanggalCetak(),
            'trenMasukImg' => TrenJamSvg::dataUri($rekap['tren'], 'masuk', $s),
            'trenPulangImg' => TrenJamSvg::dataUri($rekap['tren'], 'pulang', $s),
            'distribusiSvg' => DistribusiSvg::dataUri($rekap['distribusi']),
            'detailHari' => $detail,
            // Detail harian diindeks per tanggal supaya template bisa
            // mencetak satu kartu per hari tanpa pencarian berulang.
            'detailPerHari' => collect($detail)->keyBy('iso')->all(),
        ];
    }

    /**
     * Lampirkan swafoto (masuk & pulang) ke tiap hari yang punya absensi.
     *
     * Swafoto disimpan di disk privat, jadi berkasnya dibaca lewat penyimpanan
     * aplikasi lalu diperkecil oleh SelfieThumb — bukan lewat URL, karena
     * dompdf berjalan tanpa sesi login (`isRemoteEnabled => false`).
     *
     * @param  array<int, array<string, mixed>>  $detail
     * @return array<int, array<string, mixed>>
     */
    private static function denganSwafoto(array $detail): array
    {
        $ids = array_values(array_filter(array_column($detail, 'attendance_id')));
        if ($ids === []) {
            return $detail;
        }

        $baris = Attendance::whereIn('id', $ids)
            ->get(['id', 'selfie_url', 'selfie_pulang_url'])
            ->keyBy('id');

        foreach ($detail as &$hari) {
            $att = $baris->get($hari['attendance_id']);
            foreach (['masuk' => 'selfie_url', 'pulang' => 'selfie_pulang_url'] as $arah => $kolom) {
                if ($hari[$arah] === null) {
                    continue;
                }

                $hari[$arah]['foto'] = SelfieThumb::untuk($att?->{$kolom});
            }
        }
        unset($hari);

        return $detail;
    }

    /**
     * Logo kop sebagai data URI supaya tidak bergantung pada berkas yang bisa
     * dijangkau dompdf: kebijakan `isRemoteEnabled => false` dipertahankan dan
     * chroot tidak perlu diperlebar hanya untuk membaca satu gambar.
     */
    private static function logoSrc(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /** Tanggal cetak untuk footer/tanda tangan, dalam zona operasional aplikasi. */
    public static function tanggalCetak(): string
    {
        return Carbon::now('Asia/Makassar')->locale('id')->isoFormat('D MMMM YYYY');
    }
}
