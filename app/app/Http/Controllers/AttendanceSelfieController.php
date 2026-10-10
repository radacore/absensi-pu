<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Menyajikan swafoto absensi dari disk privat.
 *
 * Sebelumnya swafoto disimpan di disk publik dan URL-nya bisa dibuka siapa
 * saja yang tahu tautannya — kebocoran privasi. Sekarang berkas ada di disk
 * privat dan hanya keluar lewat route ini setelah otorisasi:
 *
 *  - Karyawan  : hanya swafoto miliknya sendiri.
 *  - Admin     : super_admin semua wilayah; admin_wilayah hanya wilayahnya.
 */
class AttendanceSelfieController extends Controller
{
    public function show(Request $request, Attendance $attendance, string $jenis)
    {
        abort_unless(in_array($jenis, ['masuk', 'pulang'], true), 404);

        $this->authorizeViewer($attendance);

        $path = $jenis === 'pulang'
            ? $attendance->selfie_pulang_url
            : $attendance->selfie_url;

        abort_if(blank($path), 404);

        // Baca langsung tanpa exists()/mimeType() — endpoint S3 yang dipakai
        // (Neva Objects) sesekali membalas 403 pada permintaan HEAD, sehingga
        // berkas yang ADA bisa dikira hilang. Pola sama dengan StreamsDokumen.
        $disk = Storage::disk(config('filesystems.uploads.private_disk'));

        try {
            $stream = $disk->readStream($path);
        } catch (Throwable $e) {
            report($e);
            $stream = null;
        }

        abort_if(! is_resource($stream), 404);

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->set('Content-Type', $this->mimeFromPath($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Privat: jangan biarkan proxy bersama menyimpannya.
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }

    private function authorizeViewer(Attendance $attendance): void
    {
        $employee = Auth::guard('employee')->user();

        if ($employee) {
            abort_if((int) $attendance->employee_id !== (int) $employee->id, 403, 'Swafoto milik karyawan lain.');

            return;
        }

        $admin = Auth::guard('web')->user();
        abort_unless($admin, 403, 'Harus login untuk melihat swafoto.');

        if ($admin->role !== 'super_admin') {
            abort_if((int) $attendance->region_id !== (int) $admin->region_id, 403, 'Swafoto di luar cakupan Anda.');
        }
    }

    /** Tentukan MIME dari ekstensi berkas — menghindari HEAD ke object storage. */
    private function mimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
