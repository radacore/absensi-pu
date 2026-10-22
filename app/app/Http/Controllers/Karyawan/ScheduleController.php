<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Holiday;
use App\Models\ShiftSchedule;
use App\Support\ShiftResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Halaman jadwal karyawan (A7).
 *
 * Pekerja shift melihat jadwal 4 minggu ke depan (termasuk hari liburnya);
 * karyawan non-shift melihat hari kerja, jam kerja, dan libur nasional pada
 * rentang yang sama. Jadwal shift hanya bisa diubah admin, halaman ini
 * bersifat baca saja.
 */
class ScheduleController extends Controller
{
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function index()
    {
        $me = Auth::guard('employee')->user()->load(['region', 'site', 'sites']);
        $settings = AttendanceSetting::first();
        $now = now('Asia/Makassar');

        // Empat minggu penuh dimulai dari Senin minggu berjalan, supaya
        // tampilan selalu utuh per minggu.
        $start = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addWeeks(3)->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $holidays = Holiday::whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [
                $h->tanggal->format('Y-m-d') => [
                    'nama' => $h->nama,
                    'cuti_bersama' => (bool) $h->cuti_bersama,
                ],
            ])
            ->all();

        $isShift = (bool) $me->is_shift;
        $hariKerja = array_map('strval', $me->hariKerjaList($settings));
        $pola = $isShift ? ShiftResolver::patternFor($me, $settings) : null;

        $jadwal = [];
        if ($isShift) {
            $jadwal = ShiftSchedule::with('pattern')
                ->where('employee_id', $me->id)
                ->whereDate('tanggal', '>=', $start->toDateString())
                ->whereDate('tanggal', '<=', $end->toDateString())
                ->get()
                ->keyBy(fn (ShiftSchedule $s) => $s->tanggal->format('Y-m-d'))
                ->all();
        }

        $hari = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $iso = (int) $d->isoWeekday();
            $libur = $holidays[$key] ?? null;
            $bekerja = in_array((string) $iso, $hariKerja, true) && $libur === null;

            if ($isShift) {
                /** @var ShiftSchedule|null $s */
                $s = $jadwal[$key] ?? null;
                $polaHari = $s?->pattern ?: $pola;
                $shiftKe = $s?->shift_ke;

                $jamMulai = null;
                $jamSelesai = null;
                $lewatTengahMalam = false;
                if ($shiftKe !== null && $polaHari !== null) {
                    $window = $polaHari->windowFor($d->copy(), (int) $shiftKe);
                    $jamMulai = $window['mulai']->format('H:i');
                    $jamSelesai = $window['selesai']->format('H:i');
                    $lewatTengahMalam = $window['selesai']->format('Y-m-d') !== $key;
                }

                $hari[] = [
                    'tanggal' => $key,
                    'hari' => self::HARI[$iso],
                    'hariKe' => $iso,
                    'label' => $shiftKe === null
                        ? ($s !== null ? 'Libur' : 'Belum dijadwalkan')
                        : ($polaHari?->labelShift((int) $shiftKe) ?? 'Shift '.$shiftKe),
                    'jamMulai' => $jamMulai,
                    'jamSelesai' => $jamSelesai,
                    'lewatTengahMalam' => $lewatTengahMalam,
                    'libur' => $shiftKe === null,
                    'belumDijadwalkan' => $s === null,
                    'liburNasional' => $libur['nama'] ?? null,
                    'catatan' => $s?->catatan,
                    'hariIni' => $key === $now->toDateString(),
                ];
            } else {
                $hari[] = [
                    'tanggal' => $key,
                    'hari' => self::HARI[$iso],
                    'hariKe' => $iso,
                    'label' => $bekerja ? 'Hari kerja' : 'Libur',
                    'jamMulai' => $bekerja ? substr((string) ($settings?->jam_masuk ?? '08:00'), 0, 5) : null,
                    // Jumat bisa punya jam pulang sendiri; lihat
                    // AttendanceSetting::jamPulangPada().
                    'jamSelesai' => $bekerja ? ($settings?->jamPulangPada($d->copy()) ?? '17:00') : null,
                    'lewatTengahMalam' => false,
                    'libur' => ! $bekerja,
                    'belumDijadwalkan' => false,
                    'liburNasional' => $libur['nama'] ?? null,
                    'catatan' => null,
                    'hariIni' => $key === $now->toDateString(),
                ];
            }
        }

        // Dikelompokkan per minggu supaya mudah dibaca di ponsel.
        $minggu = [];
        foreach (array_chunk($hari, 7) as $i => $pekan) {
            $minggu[] = [
                'nomor' => $i + 1,
                'mulai' => $pekan[0]['tanggal'],
                'selesai' => $pekan[count($pekan) - 1]['tanggal'],
                'hari' => $pekan,
            ];
        }

        return Inertia::render('Karyawan/Jadwal', [
            'me' => [
                'nama' => $me->name,
                'region' => $me->region?->name ?? '',
                'kantor' => $me->site?->nama_lokasi ?? '',
            ],
            'isShift' => $isShift,
            'polaRingkasan' => $pola?->ringkasan(),
            'hariKerjaLabel' => collect($hariKerja)
                ->map(fn ($d) => self::HARI[(int) $d] ?? $d)
                ->implode(', '),
            'jamKerja' => $isShift
                ? null
                : [
                    'masuk' => substr((string) ($settings?->jam_masuk ?? '08:00'), 0, 5),
                    'pulang' => substr((string) ($settings?->jam_pulang ?? '17:00'), 0, 5),
                    // Kosong bila Jumat ikut jam pulang biasa.
                    'pulangJumat' => substr((string) ($settings?->jam_pulang_jumat ?? ''), 0, 5),
                ],
            'minggu' => $minggu,
            'rentang' => [
                'mulai' => $start->locale('id')->isoFormat('D MMMM YYYY'),
                'selesai' => $end->locale('id')->isoFormat('D MMMM YYYY'),
            ],
        ]);
    }
}
