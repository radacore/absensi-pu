<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * SelfieThumb — swafoto absensi sebagai data URI kecil untuk berkas PDF.
 *
 * dompdf tidak bisa memuat URL ber-otorisasi (route selfie butuh sesi login
 * dan kebijakan `isRemoteEnabled => false` dipertahankan), jadi berkas dibaca
 * langsung dari disk privat lalu ditanam ke PDF.
 *
 * Foto asli dari kamera ponsel bisa 2 MB; menanamnya apa adanya membuat berkas
 * rekap membengkak puluhan MB. Karena itu setiap foto diperkecil lebih dulu
 * (GD) ke ukuran kartu. Hasilnya di-cache per berkas supaya rekap dengan
 * puluhan hari tidak membaca berkas yang sama dua kali.
 */
class SelfieThumb
{
    /** Kotak maksimum gambar di kartu detail (piksel). */
    private const MAKS_L = 92;

    private const MAKS_T = 122;

    /** @var array<string, array{src: string, width: int, height: int}|null> */
    private static array $cache = [];

    /**
     * @return array{src: string, width: int, height: int}|null null bila berkas
     *                                                          tidak ada atau tidak terbaca.
     */
    public static function untuk(?string $path): ?array
    {
        if (blank($path)) {
            return null;
        }

        $kunci = (string) $path;
        if (array_key_exists($kunci, self::$cache)) {
            return self::$cache[$kunci];
        }

        return self::$cache[$kunci] = self::buat($kunci);
    }

    /** Bersihkan cache — dipakai tes agar berkas antar-kasus tidak tercampur. */
    public static function lupakan(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{src: string, width: int, height: int}|null
     */
    private static function buat(string $path): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $disk = Storage::disk(config('filesystems.uploads.private_disk'));
            $isi = $disk->get($path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! is_string($isi) || $isi === '') {
            return null;
        }

        $gambar = @imagecreatefromstring($isi);
        if ($gambar === false) {
            return null;
        }

        $gambar = self::putarSesuaiExif($gambar, $isi);

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        if ($lebar < 1 || $tinggi < 1) {
            imagedestroy($gambar);

            return null;
        }

        $skala = min(self::MAKS_L / $lebar, self::MAKS_T / $tinggi, 1.0);
        $baruL = max(1, (int) round($lebar * $skala));
        $baruT = max(1, (int) round($tinggi * $skala));

        $kanvas = imagecreatetruecolor($baruL, $baruT);
        // Latar putih: PNG transparan tidak jadi hitam saat dikonversi ke JPEG.
        imagefilledrectangle($kanvas, 0, 0, $baruL, $baruT, imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $baruL, $baruT, $lebar, $tinggi);
        imagedestroy($gambar);

        ob_start();
        imagejpeg($kanvas, null, 72);
        $jpeg = (string) ob_get_clean();
        imagedestroy($kanvas);

        if ($jpeg === '') {
            return null;
        }

        return [
            'src' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'width' => $baruL,
            'height' => $baruT,
        ];
    }

    /**
     * Foto ponsel sering menyimpan orientasi di EXIF, bukan di pikselnya.
     * Tanpa koreksi ini foto di PDF bisa tampil miring.
     */
    private static function putarSesuaiExif(\GdImage $gambar, string $isi): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'selfie');
        if ($tmp === false) {
            return $gambar;
        }

        try {
            file_put_contents($tmp, $isi);
            $exif = @exif_read_data($tmp);
        } catch (Throwable) {
            $exif = false;
        } finally {
            @unlink($tmp);
        }

        $orientasi = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;
        $derajat = match ($orientasi) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($derajat === 0) {
            return $gambar;
        }

        $diputar = imagerotate($gambar, $derajat, 0);
        if ($diputar === false) {
            return $gambar;
        }

        imagedestroy($gambar);

        return $diputar;
    }
}
