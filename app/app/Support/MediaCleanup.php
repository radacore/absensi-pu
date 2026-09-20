<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pembersihan objek di object storage.
 *
 * Dipakai supaya berkas tidak tertinggal (yatim) ketika baris basis datanya
 * dihapus — termasuk saat penghapusan terjadi lewat cascade foreign key,
 * yang TIDAK memicu event model Eloquent.
 */
class MediaCleanup
{
    /** Hapus objek pada disk privat berdasarkan path yang tersimpan. */
    public static function deletePrivate(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        return self::delete(config('filesystems.uploads.private_disk'), $path);
    }

    /**
     * Hapus objek publik berdasarkan URL yang tersimpan di basis data.
     *
     * Mengembalikan false bila URL tidak bisa dipetakan ke kunci objek —
     * misalnya `data:` URL dari pratinjau sisi klien, atau URL domain lain.
     * Dalam kasus itu tidak ada yang dihapus, dan itu memang disengaja.
     */
    public static function deleteByPublicUrl(?string $url): bool
    {
        $key = self::publicKeyFromUrl($url);

        if ($key === null) {
            return false;
        }

        return self::delete(config('filesystems.uploads.public_disk'), $key);
    }

    /**
     * Ubah URL publik menjadi kunci objek pada disk publik.
     *
     * Base URL diturunkan dari disk itu sendiri (bukan dirakit manual dari
     * env), sehingga tetap benar untuk disk lokal maupun S3 — termasuk
     * perbedaan path-style dan virtual-hosted-style.
     */
    public static function publicKeyFromUrl(?string $url): ?string
    {
        if (blank($url) || str_starts_with($url, 'data:')) {
            return null;
        }

        $base = self::publicBaseUrl();

        if ($base === null || ! str_starts_with($url, $base.'/')) {
            return null;
        }

        $key = ltrim(substr($url, strlen($base)), '/');

        return $key !== '' ? $key : null;
    }

    /** Base URL disk publik. */
    private static function publicBaseUrl(): ?string
    {
        try {
            // Kunci kosong ditolak validator SDK S3, jadi pakai penanda
            // sekali pakai lalu buang penandanya dari hasil.
            $sentinel = '__base__';

            return rtrim(
                substr(Storage::disk(config('filesystems.uploads.public_disk'))->url($sentinel), 0, -strlen($sentinel)),
                '/'
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function delete(string $diskName, string $path): bool
    {
        try {
            return Storage::disk($diskName)->delete($path);
        } catch (Throwable $e) {
            // Kegagalan membersihkan berkas tidak boleh menggagalkan
            // operasi utama (mis. menghapus data karyawan).
            report($e);

            return false;
        }
    }
}
