<?php

namespace App\Support;

use App\Models\DinasClaim;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Menyajikan dokumen pendukung perjalanan dinas dari disk privat.
 *
 * PENTING — kenapa tidak memakai Storage::exists()/size()/mimeType():
 * ketiganya memicu permintaan HTTP HEAD ke object storage. Endpoint S3 yang
 * dipakai (Neva Objects) terbukti sesekali membalas 403 pada HEAD — dari 30
 * permintaan HEAD berturut-turut atas objek yang sama, 1 gagal. AWS SDK
 * (doesObjectExistV2) sengaja melempar ulang 403, sehingga berkas yang
 * sebenarnya ADA akan dianggap hilang dan halaman gagal dengan 500.
 *
 * Permintaan GET terbukti stabil (30/30 sukses), jadi berkas dibaca langsung
 * dan kegagalan diterjemahkan menjadi 404. Efek sampingnya menguntungkan:
 * satu perjalanan bolak-balik lebih sedikit per unduhan.
 */
trait StreamsDokumen
{
    protected function streamDokumen(DinasClaim $dinas): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.uploads.private_disk'));

        try {
            $stream = $disk->readStream($dinas->dokumen_path);
        } catch (Throwable $e) {
            // Disk dikonfigurasi 'throw' => false, jadi umumnya kegagalan
            // menghasilkan null, bukan exception. Ini jaring pengaman.
            report($e);
            $stream = null;
        }

        abort_if(! is_resource($stream), 404);

        // Catat siapa mengunduh dokumen siapa. Sebelumnya aksi hapus/ubah
        // sudah teraudit, tetapi akses baca berkas sensitif belum.
        Audit::log(
            'dinas.dokumen',
            subject: null,
            label: "Dokumen dinas {$dinas->nomor_surat}",
            description: 'Unduh dokumen surat tugas perjalanan dinas',
            meta: [
                'dinas_id' => $dinas->id,
                'employee_id' => $dinas->employee_id,
                'dokumen_nama' => $dinas->dokumen_nama,
            ]
        );

        $nama = $dinas->dokumen_nama ?: basename((string) $dinas->dokumen_path);

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->set('Content-Type', $dinas->dokumen_mime ?: 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('inline', $nama, 'dokumen-dinas')
        );

        // Ukuran sudah tersimpan di basis data, jadi tidak perlu HEAD ke S3.
        if ($dinas->dokumen_size) {
            $response->headers->set('Content-Length', (string) $dinas->dokumen_size);
        }

        return $response;
    }
}
