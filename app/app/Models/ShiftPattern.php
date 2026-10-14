<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pola shift kerja.
 *
 * Admin hanya mengisi jam mulai shift pertama; panjang tiap shift DITURUNKAN
 * dari jumlah shift (24 jam ÷ jumlah shift): 2 shift → 12 jam, 3 shift → 8 jam,
 * 4 shift → 6 jam.
 */
class ShiftPattern extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'jumlah_shift',
        'jam_mulai_shift_1',
        'region_id',
        'site_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const MIN_SHIFT = 2;

    public const MAX_SHIFT = 4;

    /** Nama ramah tiap shift, per jumlah shift. */
    public const NAMA_SHIFT = [
        2 => ['Pagi', 'Malam'],
        3 => ['Pagi', 'Siang', 'Malam'],
        4 => ['Pagi', 'Siang', 'Malam', 'Dini Hari'],
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    /** Panjang satu shift dalam menit: 24 jam ÷ jumlah shift. */
    public function durasiMenit(): int
    {
        return intdiv(24 * 60, max(1, (int) $this->jumlah_shift));
    }

    /** Jam mulai shift pertama dalam menit sejak 00:00. */
    public function jamMulaiMenit(): int
    {
        [$h, $m] = array_map('intval', explode(':', substr((string) $this->jam_mulai_shift_1, 0, 5)));

        return ($h * 60) + $m;
    }

    /** Label shift ke-$n ("Pagi", "Siang", …) — fallback "Shift n". */
    public function labelShift(int $shiftKe): string
    {
        $nama = self::NAMA_SHIFT[(int) $this->jumlah_shift] ?? [];

        return $nama[$shiftKe - 1] ?? "Shift {$shiftKe}";
    }

    /** Jam mulai shift ke-$n sebagai "HH:MM" (bisa melewati tengah malam). */
    public function jamMulaiShift(int $shiftKe): string
    {
        $menit = ($this->jamMulaiMenit() + (($shiftKe - 1) * $this->durasiMenit())) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    /**
     * Rentang waktu shift ke-$n pada tanggal tertentu.
     *
     * @return array{mulai: Carbon, selesai: Carbon}
     */
    public function windowFor(Carbon $tanggal, int $shiftKe): array
    {
        $total = $this->jamMulaiMenit() + (($shiftKe - 1) * $this->durasiMenit());
        $mulai = $tanggal->copy()->startOfDay()
            ->addDays(intdiv($total, 24 * 60))
            ->addMinutes($total % (24 * 60));

        return [
            'mulai' => $mulai,
            'selesai' => $mulai->copy()->addMinutes($this->durasiMenit()),
        ];
    }

    /** Ringkasan untuk UI: "3 shift • 8 jam/shift • mulai 07:00". */
    public function ringkasan(): string
    {
        return sprintf(
            '%d shift • %d jam/shift • mulai %s',
            (int) $this->jumlah_shift,
            intdiv($this->durasiMenit(), 60),
            $this->jamMulaiShift(1)
        );
    }
}
