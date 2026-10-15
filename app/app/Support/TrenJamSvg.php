<?php

namespace App\Support;

/**
 * TrenJamSvg — grafik tren jam absen sebagai SVG lepas.
 *
 * Komponen React `TrenJamChart.jsx` tidak bisa dipakai di dalam berkas PDF
 * (dompdf tidak menjalankan JavaScript), jadi grafik yang sama digambar ulang
 * di sini dengan rumus yang sama supaya rekap di layar dan rekap PDF tidak
 * pernah menunjukkan bentuk grafik yang berbeda.
 *
 * Rentang sumbu-Y dihitung dari data, bukan dipatok 06:00–10:00 / 15:00–22:00:
 * pekerja shift bisa masuk pukul 19:00 atau pulang pukul 07:00, dan rentang
 * tetap akan menutup-nutupi jam tersebut.
 */
class TrenJamSvg
{
    private const W = 720;

    private const H = 200;

    private const PAD_L = 44;

    private const PAD_R = 12;

    private const PAD_T = 12;

    private const PAD_B = 28;

    /**
     * @param  array<int, array<string, mixed>>  $tren  hasil RekapPresenter['tren']
     * @param  string  $mode  'masuk' | 'pulang'
     * @param  array<string, mixed>  $settings  RekapPresenter['settings']
     */
    public static function render(array $tren, string $mode, array $settings): string
    {
        if ($tren === []) {
            return '';
        }

        $isMasuk = $mode === 'masuk';
        $key = $isMasuk ? 'jam_masuk_min' : 'jam_pulang_min';
        $isShift = (bool) ($settings['isShift'] ?? false);
        $lastDay = count($tren);

        $values = [];
        foreach ($tren as $row) {
            if (isset($row[$key]) && $row[$key] !== null) {
                $values[] = (int) $row[$key];
            }
        }

        if ($values === []) {
            return '';
        }

        [$yMin, $yMax] = self::rentang($values, $settings, $isMasuk);

        $chartW = self::W - self::PAD_L - self::PAD_R;
        $chartH = self::H - self::PAD_T - self::PAD_B;

        $xFor = fn (int $day): float => self::PAD_L + (($day - 1) / max(1, $lastDay - 1)) * $chartW;
        $yFor = function (int $min) use ($yMin, $yMax, $chartH): float {
            $clamped = max($yMin, min($yMax, $min));

            return self::PAD_T + (($clamped - $yMin) / max(1, $yMax - $yMin)) * $chartH;
        };

        $out = [];
        $out[] = sprintf(
            '<svg viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg" style="font-family: DejaVu Sans, sans-serif;">',
            self::W, self::H, self::W, self::H
        );

        // Latar sumbu
        $out[] = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="1"/>',
            self::PAD_L, self::PAD_T, $chartW, $chartH
        );

        // Zona tepat/toleransi/terlambat hanya bermakna untuk jam kerja tetap.
        if (! $isShift && $isMasuk) {
            $batas = (int) ($settings['batasToleransiMin'] ?? 0);
            $masuk = (int) ($settings['jamMasukMin'] ?? 0);
            $yTepat = $yFor($masuk);
            $yTol = $yFor($batas);
            $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%s" fill="#DCFCE7" opacity="0.5"/>', self::PAD_L, self::PAD_T, $chartW, max(0, $yTepat - self::PAD_T));
            $out[] = sprintf('<rect x="%d" y="%s" width="%d" height="%s" fill="#FEF3C7" opacity="0.5"/>', self::PAD_L, $yTepat, $chartW, max(0, $yTol - $yTepat));
            $out[] = sprintf('<rect x="%d" y="%s" width="%d" height="%s" fill="#FEE2E2" opacity="0.5"/>', self::PAD_L, $yTol, $chartW, max(0, (self::H - self::PAD_B) - $yTol));
        }

        // Garis acuan
        if (! $isShift) {
            $garis = $isMasuk
                ? [[(int) ($settings['jamMasukMin'] ?? 0), '#64748B'], [(int) ($settings['batasToleransiMin'] ?? 0), '#F59E0B']]
                : [[(int) ($settings['jamPulangMin'] ?? 0), '#10B981']];

            foreach ($garis as [$min, $warna]) {
                if ($min < $yMin || $min > $yMax) {
                    continue;
                }
                $y = $yFor($min);
                $out[] = sprintf('<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" stroke="%s" stroke-width="1" stroke-dasharray="3,3"/>', self::PAD_L, self::W - self::PAD_R, $y, $y, $warna);
                $out[] = sprintf('<text x="%d" y="%.1f" font-size="9" fill="%s" text-anchor="end">%s</text>', self::PAD_L - 4, $y + 3, $warna, self::label($min));
            }
        }

        // Label sumbu-Y
        foreach (self::labelY($yMin, $yMax) as $min) {
            $out[] = sprintf('<text x="%d" y="%.1f" font-size="10" fill="#64748B" text-anchor="end">%s</text>', self::PAD_L - 4, $yFor($min) + 3, self::label($min));
        }

        // Label sumbu-X
        foreach ([1, 7, 14, 21, 28, $lastDay] as $d) {
            if ($d > $lastDay) {
                continue;
            }
            $out[] = sprintf('<text x="%.1f" y="%d" font-size="10" fill="#64748B" text-anchor="middle">%d</text>', $xFor($d), self::H - self::PAD_B + 14, $d);
        }

        // Garis data
        $titik = [];
        foreach ($tren as $i => $row) {
            if (isset($row[$key]) && $row[$key] !== null) {
                $titik[] = ['day' => $i + 1, 'val' => (int) $row[$key]];
            }
        }

        if (count($titik) > 1) {
            $segments = [];
            foreach ($titik as $i => $t) {
                $segments[] = sprintf('%s %.1f %.1f', $i === 0 ? 'M' : 'L', $xFor($t['day']), $yFor($t['val']));
            }
            $out[] = sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>', implode(' ', $segments), $isMasuk ? '#0D9488' : '#1E3A8A');
        }

        foreach ($titik as $t) {
            $out[] = sprintf('<circle cx="%.1f" cy="%.1f" r="2.5" fill="%s"/>', $xFor($t['day']), $yFor($t['val']), $isMasuk ? '#0D9488' : '#1E3A8A');
        }

        // Hari kerja tanpa absen
        foreach ($tren as $i => $row) {
            if (($row[$key] ?? null) !== null) {
                continue;
            }
            if (! self::hariKerja($row, $settings)) {
                continue;
            }
            $out[] = sprintf('<circle cx="%.1f" cy="%d" r="2.5" fill="#EF4444" opacity="0.7"/>', $xFor($i + 1), self::H - self::PAD_B - 2);
        }

        $out[] = sprintf('<text x="%d" y="%d" font-size="10" fill="#334155">%s</text>', self::PAD_L, 10, $isMasuk ? 'Tren jam masuk' : 'Tren jam pulang');
        $out[] = '</svg>';

        return implode('', $out);
    }

    /**
     * Rentang sumbu-Y: dari data (dibulatkan ke jam) dengan sedikit kelonggaran.
     * Untuk jam kerja tetap, rentang tetap dipertahankan supaya zona
     * tepat/toleransi/terlambat selalu terlihat utuh.
     *
     * @param  array<int, int>  $values
     * @return array{0: int, 1: int}
     */
    private static function rentang(array $values, array $settings, bool $isMasuk): array
    {
        if (! ($settings['isShift'] ?? false)) {
            return $isMasuk ? [360, 600] : [900, 1320];
        }

        $min = min($values);
        $max = max($values);
        $bawah = (int) (floor($min / 60) * 60) - 60;
        $atas = (int) (ceil($max / 60) * 60) + 60;

        return [max(0, $bawah), min(24 * 60, $atas)];
    }

    /**
     * Label sumbu-Y: pilih langkah 30/60/120/180 menit supaya jumlah baris
     * antara 2–5 saja (grafik tetap terbaca, tidak penuh garis bantu).
     *
     * @return array<int, int>
     */
    private static function labelY(int $yMin, int $yMax): array
    {
        $rentang = max(1, $yMax - $yMin);
        $langkah = 180;
        foreach ([30, 60, 120, 180, 240, 360] as $kandidat) {
            if (($rentang / $kandidat) <= 4) {
                $langkah = $kandidat;
                break;
            }
        }

        $labels = [];
        for ($min = (int) (ceil($yMin / $langkah) * $langkah); $min <= $yMax; $min += $langkah) {
            $labels[] = $min;
        }

        return $labels;
    }

    /** Apakah baris ini hari kerja menurut acuan yang berlaku? */
    private static function hariKerja(array $row, array $settings): bool
    {
        if (isset($row['is_hari_kerja'])) {
            return (bool) $row['is_hari_kerja'];
        }

        return ! ($row['is_weekend'] ?? false) && ! ($row['is_holiday'] ?? false);
    }

    private static function label(int $min): string
    {
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }
}
