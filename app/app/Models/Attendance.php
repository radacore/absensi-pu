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
        'status',
        'lat_in',
        'lng_in',
        'lat_out',
        'lng_out',
        'distance_in_m',
        // CATATAN: kedua kolom ini menyimpan PATH berkas di disk privat
        // (bukan URL publik). URL-nya dibentuk lewat selfieRouteUrl().
        'selfie_url',
        'selfie_pulang_url',
        // Buku catatan pekerjaan yang diisi karyawan saat absen pulang.
        'catatan_pulang',
        'site_id',
        'region_id',
        'tolerance_claim_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
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

    public function toleranceClaim(): BelongsTo
    {
        return $this->belongsTo(ToleranceClaim::class);
    }
}
