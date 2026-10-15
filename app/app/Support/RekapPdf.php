<?php

namespace App\Support;

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

        return $pdf->download('rekap-presensi-'.$rekap['karyawan']['nik'].'-'.$bulan.'.pdf');
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
            'logoSrc' => self::logoSrc(),
            'tanggalCetak' => self::tanggalCetak(),
            'trenMasukSvg' => TrenJamSvg::render($rekap['tren'], 'masuk', $s),
            'trenPulangSvg' => TrenJamSvg::render($rekap['tren'], 'pulang', $s),
        ];
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
