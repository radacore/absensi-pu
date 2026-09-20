<?php

namespace App\Models;

use App\Support\MediaCleanup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Employee extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'nik',
        'nip',
        'name',
        'golongan',
        'jabatan',
        'unit_kerja',
        'status_kepegawaian',
        'region_id',
        'site_id',
        'password',
        'foto_url',
        'email',
        'phone',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function toleranceClaims(): HasMany
    {
        return $this->hasMany(ToleranceClaim::class);
    }

    public function dinasClaims(): HasMany
    {
        return $this->hasMany(DinasClaim::class);
    }

    /**
     * Bersihkan berkas milik karyawan sebelum barisnya dihapus.
     *
     * Foreign key `cascadeOnDelete` menghapus baris `dinas_claims` di level
     * basis data TANPA memicu event model Eloquent — sehingga dokumen di
     * object storage akan tertinggal sebagai yatim. Karena itu baris dinas
     * dihapus lewat Eloquent lebih dulu supaya `DinasClaim::deleting` jalan.
     */
    protected static function booted(): void
    {
        static::deleting(function (Employee $employee): void {
            $employee->dinasClaims()->get()->each->delete();

            MediaCleanup::deleteByPublicUrl($employee->foto_url);
        });
    }
}
