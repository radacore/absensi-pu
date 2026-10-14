<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use Illuminate\Support\Carbon;

/**
 * Satu tempat untuk menjawab "shift apa yang berlaku sekarang?".
 *
 * Dipakai bersama oleh absen masuk, absen pulang, dan tampilan jadwal supaya
 * aturannya tidak tersebar dan tidak berbeda antar halaman.
 */
class ShiftResolver
{
    /** Karyawan boleh absen masuk sampai sekian menit sebelum jam shift. */
    public const AWAL_MENIT = 120;

    /**
     * Pola shift yang berlaku untuk karyawan — yang paling spesifik menang:
     * per lokasi → per wilayah → global.
     */
    public static function patternFor(Employee $employee): ?ShiftPattern
    {
        if (! $employee->is_shift) {
            return null;
        }

        $base = fn () => ShiftPattern::where('is_active', true)->orderBy('id');

        if ($employee->site_id) {
            $site = $base()->whereNotNull('site_id')->where('site_id', $employee->site_id)->first();
            if ($site) {
                return $site;
            }
        }

        if ($employee->region_id) {
            $wilayah = $base()->whereNull('site_id')->whereNotNull('region_id')
                ->where('region_id', $employee->region_id)->first();
            if ($wilayah) {
                return $wilayah;
            }
        }

        return $base()->whereNull('site_id')->whereNull('region_id')->first();
    }

    /** Baris jadwal karyawan pada satu tanggal (null bila belum dijadwalkan). */
    public static function scheduleFor(Employee $employee, Carbon $date): ?ShiftSchedule
    {
        return ShiftSchedule::with('pattern')
            ->where('employee_id', $employee->id)
            ->whereDate('tanggal', $date->toDateString())
            ->first();
    }

    /**
     * Resolusi pola untuk banyak karyawan sekaligus — dipakai halaman admin
     * supaya tidak menembak satu query per karyawan.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return array<int, ShiftPattern|null> kunci = employee_id
     */
    public static function patternsFor($employees): array
    {
        $pola = ShiftPattern::where('is_active', true)->orderBy('id')->get();

        $perSite = [];
        $perRegion = [];
        $global = null;
        foreach ($pola as $p) {
            if ($p->site_id) {
                $perSite[$p->site_id] ??= $p;
            } elseif ($p->region_id) {
                $perRegion[$p->region_id] ??= $p;
            } elseif ($global === null) {
                $global = $p;
            }
        }

        $out = [];
        foreach ($employees as $e) {
            $out[$e->id] = $e->is_shift
                ? ($perSite[$e->site_id] ?? $perRegion[$e->region_id] ?? $global)
                : null;
        }

        return $out;
    }

    /** Label shift terjadwal pada tanggal tertentu (null = libur / tanpa jadwal). */
    public static function labelFor(Employee $employee, Carbon $date): ?string
    {
        return self::scheduleFor($employee, $date)?->label();
    }

    /**
     * Shift yang sedang berlaku saat $now.
     *
     * Jadwal hari ini diperiksa lebih dulu, lalu jadwal kemarin — supaya shift
     * malam yang melewati tengah malam tetap tertangkap. Kembalian null berarti
     * tidak ada shift berjalan (mis. pekerja shift sedang libur).
     *
     * @return array{schedule: ShiftSchedule, pattern: ShiftPattern, mulai: Carbon, selesai: Carbon}|null
     */
    public static function currentShift(Employee $employee, Carbon $now): ?array
    {
        if (! $employee->is_shift) {
            return null;
        }

        $cadangan = null;

        foreach ([0, 1] as $mundur) {
            $tanggal = $now->copy()->startOfDay()->subDays($mundur);
            $schedule = self::scheduleFor($employee, $tanggal);

            if (! $schedule || $schedule->shift_ke === null) {
                continue;
            }

            $pattern = $schedule->pattern ?: self::patternFor($employee);
            if (! $pattern) {
                continue;
            }

            $w = $pattern->windowFor($tanggal, (int) $schedule->shift_ke);
            $hasil = ['schedule' => $schedule, 'pattern' => $pattern, 'mulai' => $w['mulai'], 'selesai' => $w['selesai']];

            if ($now->betweenIncluded($w['mulai']->copy()->subMinutes(self::AWAL_MENIT), $w['selesai'])) {
                return $hasil;
            }

            // Datang jauh sebelum jam shift: tetap tempelkan ke jadwal hari ini
            // supaya karyawan tidak terkunci ("tidak ada shift berjalan").
            $cadangan ??= $hasil;
        }

        return $cadangan;
    }
}
