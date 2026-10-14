<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal shift seorang karyawan pada satu tanggal.
 *
 * `shift_ke` null = libur (baris tetap disimpan supaya admin bisa menandai
 * libur secara eksplisit). Pekerja shift malam tetap SATU baris absensi pada
 * tanggal mulai shift — clock_out_at boleh jatuh keesokan harinya.
 */
class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'tanggal',
        'shift_ke',
        'shift_pattern_id',
        'catatan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'shift_pattern_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLibur(): bool
    {
        return $this->shift_ke === null;
    }

    /** Label untuk UI: null kalau libur. */
    public function label(): ?string
    {
        if ($this->shift_ke === null) {
            return null;
        }

        return $this->pattern
            ? $this->pattern->labelShift((int) $this->shift_ke)
            : 'Shift '.$this->shift_ke;
    }

    /** Jam mulai "HH:MM" untuk UI, atau null kalau libur. */
    public function jamMulai(): ?string
    {
        if ($this->shift_ke === null || ! $this->pattern) {
            return null;
        }

        return $this->pattern->jamMulaiShift((int) $this->shift_ke);
    }
}
