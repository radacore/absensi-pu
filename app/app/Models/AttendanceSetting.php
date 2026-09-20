<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'jam_masuk',
        'jam_pulang',
        'toleransi_late_menit',
        'love_max',
        'absen_libur_aktif',
        'absen_libur_mode',
        'hari_kerja',
        'timezone',
        'updated_by',
    ];

    /** Hari kerja default (1 = Senin … 7 = Minggu, ISO-8601). */
    public const HARI_KERJA_DEFAULT = ['1', '2', '3', '4', '5'];

    protected function casts(): array
    {
        return [
            'hari_kerja' => 'array',
            'absen_libur_aktif' => 'boolean',
        ];
    }

    /** Daftar hari kerja sebagai string, selalu terisi. */
    public function hariKerjaList(): array
    {
        $list = $this->hari_kerja;

        return is_array($list) && $list !== []
            ? array_map('strval', $list)
            : self::HARI_KERJA_DEFAULT;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
