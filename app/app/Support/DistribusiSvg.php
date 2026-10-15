<?php

namespace App\Support;

/**
 * DistribusiSvg — diagram donat distribusi kehadiran sebagai SVG lepas.
 *
 * Komponen React tidak bisa dipakai di dalam berkas PDF (dompdf tidak
 * menjalankan JavaScript) dan dompdf tidak mengenal `conic-gradient`, jadi
 * potongan donat digambar manual sebagai path busur. Warna tiap kategori sama
 * dengan kartu distribusi di layar supaya PDF dan halaman web terbaca sama.
 */
class DistribusiSvg
{
    private const R = 70;

    private const R_DALAM = 44;

    /** Urutan tetap: hadir, tanpa keterangan, dinas, cuti. */
    private const WARNA = [
        'hadir' => '#10B981',
        'tanpa_keterangan' => '#EF4444',
        'dinas' => '#3B82F6',
        'cuti' => '#F59E0B',
    ];

    /**
     * SVG sebagai data URI — dompdf hanya menggambar SVG lewat atribut `src`
     * gambar, bukan lewat elemen `<svg>` yang ditulis langsung di HTML.
     *
     * @param  array<string, int>  $distribusi
     */
    public static function dataUri(array $distribusi): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::render($distribusi));
    }

    /**
     * @param  array<string, int>  $distribusi  RekapPresenter['distribusi']
     */
    public static function render(array $distribusi): string
    {
        $total = array_sum($distribusi);
        $c = 80;
        $out = sprintf(
            '<svg viewBox="0 0 160 160" width="150" height="150" xmlns="http://www.w3.org/2000/svg">'
        );

        if ($total <= 0) {
            $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="#E2E8F0"/>', $c, $c, self::R);
            $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="#FFFFFF"/>', $c, $c, self::R_DALAM);

            return $out.'</svg>';
        }

        $nonZero = array_filter($distribusi, fn ($n) => (int) $n > 0);

        // Satu kategori saja: busur penuh akan merosot jadi titik (start =
        // end), jadi digambar sebagai dua lingkaran.
        if (count($nonZero) === 1) {
            $key = array_key_first($nonZero);
            $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="%s"/>', $c, $c, self::R, self::WARNA[$key] ?? '#94A3B8');
            $out .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="#FFFFFF"/>', $c, $c, self::R_DALAM);

            return $out.'</svg>';
        }

        $sudut = -90.0;
        foreach ($distribusi as $key => $nilai) {
            $nilai = (int) $nilai;
            if ($nilai <= 0) {
                continue;
            }

            $besar = ($nilai / $total) * 360.0;
            $a1 = $sudut;
            $a2 = $sudut + $besar;
            $sudut = $a2;

            // Busur > 180° butuh flag large-arc.
            $flag = $besar > 180 ? 1 : 0;
            $warna = self::WARNA[$key] ?? '#94A3B8';

            [$x1, $y1] = self::titik($c, self::R, $a1);
            [$x2, $y2] = self::titik($c, self::R, $a2);
            [$x3, $y3] = self::titik($c, self::R_DALAM, $a2);
            [$x4, $y4] = self::titik($c, self::R_DALAM, $a1);

            $out .= sprintf(
                '<path d="M%.2f %.2f A%d %d 0 %d 1 %.2f %.2f L%.2f %.2f A%d %d 0 %d 0 %.2f %.2f Z" fill="%s"/>',
                $x1, $y1, self::R, self::R, $flag, $x2, $y2,
                $x3, $y3, self::R_DALAM, self::R_DALAM, $flag, $x4, $y4, $warna
            );
        }

        return $out.'</svg>';
    }

    /**
     * Titik pada lingkaran untuk sudut (derajat, 0 = jam 3, negatif = atas).
     *
     * @return array{0: float, 1: float}
     */
    private static function titik(int $c, int $r, float $sudut): array
    {
        $rad = deg2rad($sudut);

        return [$c + $r * cos($rad), $c + $r * sin($rad)];
    }
}
