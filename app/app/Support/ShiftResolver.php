<?php

namespace App\Support;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use Illuminate\Support\Carbon;

/**
 * Satu tempat untuk menjawab "shift apa yang berlaku sekarang?".
 *
 * Dipakai bersama oleh absen masuk, absen pulang, dan tampilan jadwal supaya
 * aturannya tidak tersebar dan tidak berbeda antar halaman.
 *
 * Cakupan pencarian pola (K5) mengikuti setelan `cakupan_pola_shift` supaya
 * keputusan client soal "pola diatur di level mana" bisa diubah tanpa ubah
 * kode. Bawaannya bertingkat: per lokasi → per wilayah → global.
 */
class ShiftResolver
{
    /** Karyawan boleh absen masuk sampai sekian menit sebelum jam shift. */
    public const AWAL_MENIT = 120;

    /** Cakupan pencarian pola yang berlaku. */
    public static function cakupan(?AttendanceSetting $settings = null): string
    {
        $settings ??= AttendanceSetting::first();

        return $settings?->cakupanPola() ?? AttendanceSetting::CAKUPAN_POLA_DEFAULT;
    }

    /** Cakupan ini memakai pola per lokasi? */
    public static function pakaiLokasi(string $cakupan): bool
    {
        return $cakupan === 'bertingkat';
    }

    /** Cakupan ini masih memakai pola per wilayah? */
    public static function pakaiWilayah(string $cakupan): bool
    {
        return in_array($cakupan, ['bertingkat', 'wilayah'], true);
    }

    /**
     * Pola shift yang berlaku untuk karyawan — yang paling spesifik menang:
     * per lokasi → per wilayah → global, dipersempit sesuai setelan cakupan.
     *
     * `$siteId` diisi bila karyawan sedang berada di salah satu titik
     * tambahannya, supaya pola yang dipakai adalah pola titik tempat dia
     * benar-benar absen hari itu.
     */
    public static function patternFor(Employee $employee, ?AttendanceSetting $settings = null, ?int $siteId = null): ?ShiftPattern
    {
        if (! $employee->is_shift) {
            return null;
        }

        $cakupan = self::cakupan($settings);
        $base = fn () => ShiftPattern::where('is_active', true)->orderBy('id');
        $siteId ??= $employee->site_id;

        if (self::pakaiLokasi($cakupan) && $siteId) {
            $site = $base()->whereNotNull('site_id')->where('site_id', $siteId)->first();
            if ($site) {
                return $site;
            }
        }

        if (self::pakaiWilayah($cakupan) && $employee->region_id) {
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
    public static function patternsFor($employees, ?AttendanceSetting $settings = null): array
    {
        $cakupan = self::cakupan($settings);
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
            if (! $e->is_shift) {
                $out[$e->id] = null;

                continue;
            }

            $out[$e->id] = self::pakaiLokasi($cakupan) ? ($perSite[$e->site_id] ?? null) : null;
            $out[$e->id] ??= self::pakaiWilayah($cakupan) ? ($perRegion[$e->region_id] ?? null) : null;
            $out[$e->id] ??= $global;
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
    public static function currentShift(Employee $employee, Carbon $now, ?int $siteId = null): ?array
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

            $pattern = $schedule->pattern ?: self::patternFor($employee, null, $siteId);
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
