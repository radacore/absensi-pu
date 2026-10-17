<?php

namespace App\Models;

use App\Support\MediaCleanup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        // Snapshot shift yang berlaku saat absen masuk (null = bukan pekerja shift).
        'shift_ke',
        // WFO (bawaan) atau WFA. WFA hanya untuk karyawan non-shift.
        'jenis_kerja',
        'wfa_alasan',
        'wfa_status',
        'wfa_note',
        'wfa_approved_by',
        'wfa_approved_at',
        'status',
        'lat_in',
        'lng_in',
        'lat_out',
        'lng_out',
        'distance_in_m',
        // Absen WFO di luar radius titik terdaftar tetap dicatat, hanya
        // ditandai (jawaban E6).
        'di_luar_titik',
        'di_luar_titik_pulang',
        // CATATAN: kedua kolom ini menyimpan PATH berkas di disk privat
        // (bukan URL publik). URL-nya dibentuk lewat selfieRouteUrl().
        'selfie_url',
        'selfie_pulang_url',
        // Buku catatan pekerjaan yang diisi karyawan saat absen pulang.
        'catatan_pulang',
        'site_id',
        // Titik absen pulang bila berbeda dari titik absen masuk.
        'site_out_id',
        'region_id',
        'tolerance_claim_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'di_luar_titik' => 'boolean',
            'di_luar_titik_pulang' => 'boolean',
            'wfa_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Attendance $attendance) {
            // Baris absensi dihapus → swafoto di disk privat ikut dibersihkan
            // agar tidak menumpuk sebagai berkas yatim di object storage.
            MediaCleanup::deletePrivate($attendance->selfie_url);
            MediaCleanup::deletePrivate($attendance->selfie_pulang_url);
        });
    }

    /**
     * URL ber-otorisasi untuk swafoto absensi.
     *
     * Mengembalikan null bila tidak ada berkas. Otorisasi (karyawan pemilik
     * atau admin sesuai cakupan) ditangani AttendanceSelfieController.
     */
    public function selfieRouteUrl(string $jenis = 'masuk'): ?string
    {
        $path = $jenis === 'pulang' ? $this->selfie_pulang_url : $this->selfie_url;

        if (blank($path)) {
            return null;
        }

        return route('attendance.selfie', ['attendance' => $this, 'jenis' => $jenis]);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Titik absen masuk.
     *
     * Disimpan per baris absensi, bukan dibaca dari titik utama karyawan:
     * karyawan boleh absen di titik tambahan mana pun, dan titik utama bisa
     * berubah setelah absensi terjadi.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Titik absen pulang (null untuk absensi lama atau yang belum pulang).
     */
    public function siteOut(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_out_id');
    }

    public function toleranceClaim(): BelongsTo
    {
        return $this->belongsTo(ToleranceClaim::class);
    }

    /** Admin yang memutuskan pengajuan WFA pada baris absensi ini. */
    public function wfaApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wfa_approved_by');
    }

    /** Apakah baris ini dicatat sebagai kerja dari luar kantor. */
    public function isWfa(): bool
    {
        return $this->jenis_kerja === 'WFA';
    }
}
