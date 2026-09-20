<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ToleranceClaim;
use Illuminate\Support\Carbon;

/**
 * RekapPresenter — bangun data lengkap rekap presensi perorangan
 * per karyawan per bulan. Data ini dipakai halaman rekap detail
 * di sisi Admin dan Karyawan.
 */
class RekapPresenter
{
    public static function buildRekap(Employee $emp, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Makassar');
        $end = $start->copy()->endOfMonth();
        $lastDay = (int) $end->format('d');

        $settings = AttendanceSetting::first();
        $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
        $jamPulang = $settings ? substr((string) $settings->jam_pulang, 0, 5) : '16:00';
        $toleransi = $settings ? (int) $settings->toleransi_late_menit : 15;

        [$hM, $mM] = array_map('intval', explode(':', $jamMasuk));
        [$hP, $mP] = array_map('intval', explode(':', $jamPulang));
        $jamMasukMin = $hM * 60 + $mM;
        $jamPulangMin = $hP * 60 + $mP;
        $batasToleransiMin = $jamMasukMin + $toleransi;

        // Attendance bulan itu
        $attendances = Attendance::where('employee_id', $emp->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->keyBy(fn (Attendance $a) => Carbon::parse($a->work_date)->format('Y-m-d'));

        // Leaves disetujui overlap
        $leaves = Leave::where('employee_id', $emp->id)
            ->where('status', 'Disetujui')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('mulai', [$start, $end])
                    ->orWhereBetween('selesai', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->where('mulai', '<', $start)->where('selesai', '>', $end);
                    });
            })->get();
        $cutiDates = [];
        foreach ($leaves as $l) {
            $s = Carbon::parse($l->mulai)->max($start);
            $e = Carbon::parse($l->selesai)->min($end);
            for ($d = $s->copy(); $d->lte($e); $d->addDay()) {
                $cutiDates[$d->format('Y-m-d')] = ['jenis' => $l->jenis, 'alasan' => $l->alasan, 'status' => $l->status];
            }
        }

        // Dinas disetujui overlap
        $dinasClaims = DinasClaim::where('employee_id', $emp->id)
            ->where('status', 'Disetujui')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_mulai', [$start, $end])
                    ->orWhereBetween('tanggal_selesai', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->where('tanggal_mulai', '<', $start)->where('tanggal_selesai', '>', $end);
                    });
            })->get();
        $dinasDates = [];
        foreach ($dinasClaims as $d) {
            $s = Carbon::parse($d->tanggal_mulai)->max($start);
            $e = Carbon::parse($d->tanggal_selesai)->min($end);
            for ($cur = $s->copy(); $cur->lte($e); $cur->addDay()) {
                $dinasDates[$cur->format('Y-m-d')] = [
                    'nomor_surat' => $d->nomor_surat,
                    'tanggal_dinas' => Carbon::parse($d->tanggal_mulai)->locale('id')->isoFormat('dddd, D MMMM YYYY').' – '.Carbon::parse($d->tanggal_selesai)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                    'tanggal_pengajuan' => Carbon::parse($d->tanggal_pengajuan)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                    'keterangan' => $d->keterangan,
                    'tujuan' => $d->tujuan,
                    'transportasi' => $d->transportasi,
                    'pembebanan_anggaran' => $d->pembebanan_anggaran,
                ];
            }
        }

        // Toleransi (Lupa Absen) approved bulan itu
        $toleranceClaims = ToleranceClaim::where('employee_id', $emp->id)
            ->where('status', 'approved')
            ->whereYear('claim_date', $year)->whereMonth('claim_date', $month)
            ->get();
        $dispensasiCounts = [];
        foreach ($toleranceClaims as $c) {
            $label = $c->jenis === 'lupa_absen' ? 'Lupa Absen Datang' : 'Lupa Absen Pulang';
            $dispensasiCounts[$label] = ($dispensasiCounts[$label] ?? 0) + 1;
        }

        // Libur nasional bulan itu
        $holidays = Holiday::whereBetween('tanggal', [$start, $end])->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->tanggal->format('Y-m-d') => ['nama' => $h->nama, 'cuti_bersama' => $h->cuti_bersama]])
            ->all();

        // Metrik counters
        $hadirCount = 0;
        $tanpaKeteranganCount = 0;
        $dinasCount = 0;
        $cutiCount = 0;
        $tepatWaktu = 0;
        $toleransiCount = 0;
        $terlambat = 0;

        $harian = [];
        $keterlambatan = [];
        $tren = [];
        $detailHari = [];
        $noKeterlambatan = 0;

        for ($day = 1; $day <= $lastDay; $day++) {
            $date = Carbon::create($year, $month, $day, 0, 0, 0, 'Asia/Makassar');
            $dateKey = $date->format('Y-m-d');
            $dow = $date->dayOfWeek; // 0 Sunday, 6 Saturday
            $isWeekend = $dow === 0 || $dow === 6;
            $isHoliday = isset($holidays[$dateKey]);
            $isCuti = isset($cutiDates[$dateKey]);
            $isDinas = isset($dinasDates[$dateKey]);
            $att = $attendances[$dateKey] ?? null;
            $hariLabel = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][$dow];
            $hariFull = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$dow];

            $jamMasukStr = '—';
            $jamPulangStr = '—';
            $catatan = '';
            $trenMasukMin = null;
            $trenPulangMin = null;
            $zonaMasuk = null;

            if ($isWeekend && ! $isHoliday) {
                $catatan = "Akhir pekan ({$hariFull})";
            }
            if ($isHoliday) {
                $catatan = ($isWeekend ? 'Akhir pekan & ' : '').'Libur nasional: '.$holidays[$dateKey]['nama'];
            }
            if ($isCuti) {
                $catatan = 'Cuti '.$cutiDates[$dateKey]['jenis'];
                $cutiCount++;
            } elseif ($isDinas) {
                $catatan = 'Dinas: '.$dinasDates[$dateKey]['tujuan'];
                $dinasCount++;
                $hadirCount++;
            } elseif ($att) {
                $jamMasukStr = $att->clock_in_at ? Carbon::parse($att->clock_in_at)->format('H:i') : '—';
                $jamPulangStr = $att->clock_out_at ? Carbon::parse($att->clock_out_at)->format('H:i') : '—';
                $hadirCount++;

                if ($att->status === 'excused_love') {
                    // Keterlambatan sudah dimaafkan oleh klaim toleransi yang
                    // disetujui (lupa absen) → jangan dihitung sebagai terlambat.
                    $toleransiCount++;
                    $zonaMasuk = 'toleransi';
                    $catatan = 'Toleransi disetujui — Lupa Absen';
                    if ($att->clock_in_at) {
                        $p = Carbon::parse($att->clock_in_at);
                        $trenMasukMin = (int) $p->format('G') * 60 + (int) $p->format('i');
                    }
                } elseif ($att->clock_in_at) {
                    $p = Carbon::parse($att->clock_in_at);
                    $inMin = (int) $p->format('G') * 60 + (int) $p->format('i');
                    $trenMasukMin = $inMin;
                    if ($inMin <= $jamMasukMin) {
                        $tepatWaktu++;
                        $zonaMasuk = 'tepat';
                        $catatan = 'Hadir tepat waktu';
                    } elseif ($inMin <= $batasToleransiMin) {
                        $toleransiCount++;
                        $zonaMasuk = 'toleransi';
                        $catatan = 'Masuk toleransi terlambat';
                    } else {
                        $terlambat++;
                        $zonaMasuk = 'terlambat';
                        $menitTerlambat = $inMin - $jamMasukMin;
                        $noKeterlambatan++;
                        $keterlambatan[] = [
                            'no' => $noKeterlambatan,
                            'tgl' => $date->locale('id')->isoFormat('D MMM YYYY'),
                            'menit_terlambat' => $menitTerlambat,
                            'menit_psw' => 0,
                        ];
                        $catatan = "Terlambat {$menitTerlambat} menit";
                    }
                }

                if ($att->clock_out_at) {
                    $p = Carbon::parse($att->clock_out_at);
                    $outMin = (int) $p->format('G') * 60 + (int) $p->format('i');
                    $trenPulangMin = $outMin;
                    if (! $isWeekend && ! $isHoliday && $outMin < $jamPulangMin) {
                        $menitPSW = $jamPulangMin - $outMin;
                        // gabung PSW ke keterlambatan yang sama kalau ada; kalau tidak, tambah row baru
                        $found = false;
                        foreach ($keterlambatan as &$row) {
                            if ($row['tgl'] === $date->locale('id')->isoFormat('D MMM YYYY')) {
                                $row['menit_psw'] = $menitPSW;
                                $found = true;
                                break;
                            }
                        }
                        unset($row);
                        if (! $found) {
                            $noKeterlambatan++;
                            $keterlambatan[] = [
                                'no' => $noKeterlambatan,
                                'tgl' => $date->locale('id')->isoFormat('D MMM YYYY'),
                                'menit_terlambat' => 0,
                                'menit_psw' => $menitPSW,
                            ];
                        }
                    }
                } elseif (! $isWeekend && ! $isHoliday) {
                    $catatan .= ($catatan ? ' · ' : '').'Tidak ada absen pulang';
                }

                // Detail per hari untuk kartu detail
                $detailHari[] = self::buildDetailHari($att, $date, $hariFull, $zonaMasuk, $emp);
            } else {
                // Hari kerja tanpa absen dan tanpa cuti/dinas
                if (! $isWeekend && ! $isHoliday) {
                    $tanpaKeteranganCount++;
                    $catatan = 'Tidak ada absen masuk · Tidak ada absen pulang';
                }
            }

            $harian[] = [
                'tgl' => $date->locale('id')->isoFormat('D MMM YYYY'),
                'iso' => $dateKey,
                'hari' => $hariLabel,
                'jam_masuk' => $jamMasukStr,
                'jam_pulang' => $jamPulangStr,
                'catatan' => $catatan,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_cuti' => $isCuti,
                'is_dinas' => $isDinas,
                'is_tanpa_keterangan' => (! $att && ! $isWeekend && ! $isHoliday && ! $isCuti && ! $isDinas),
                'dinas' => $isDinas ? $dinasDates[$dateKey] : null,
                'cuti' => $isCuti ? $cutiDates[$dateKey] : null,
                'holiday' => $isHoliday ? $holidays[$dateKey] : null,
            ];

            $tren[] = [
                'day' => $day,
                'jam_masuk_min' => $trenMasukMin,
                'jam_pulang_min' => $trenPulangMin,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_cuti' => $isCuti,
                'is_dinas' => $isDinas,
                'zona' => $zonaMasuk,
            ];
        }

        $totalKehadiran = $hadirCount;

        return [
            'karyawan' => [
                'id' => $emp->id,
                'nama' => $emp->name,
                'nik' => $emp->nik,
                'nip' => $emp->nip ?? '—',
                'jabatan' => $emp->jabatan,
                'golongan' => $emp->golongan ?: '—',
                'status_kepegawaian' => $emp->status_kepegawaian,
                'unit_kerja' => $emp->unit_kerja,
                'region' => $emp->region?->name ?? '',
                'site' => $emp->site?->nama_lokasi ?? '',
            ],
            'periode' => [
                'year' => $year,
                'month' => $month,
                'namaBulan' => $start->locale('id')->isoFormat('MMMM'),
                'label' => $start->locale('id')->isoFormat('MMMM YYYY'),
                'startISO' => $start->format('Y-m-d'),
                'endISO' => $end->format('Y-m-d'),
                'lastDay' => $lastDay,
                'firstDow' => $start->dayOfWeek,
                'tanggalStatusData' => $end->locale('id')->isoFormat('D MMMM YYYY'),
                'diExportPada' => now('Asia/Makassar')->format('Y-m-d H:i:s'),
            ],
            'settings' => [
                'jamMasuk' => $jamMasuk,
                'jamPulang' => $jamPulang,
                'toleransi' => $toleransi,
                'batasToleransi' => sprintf('%02d:%02d', intdiv($batasToleransiMin, 60), $batasToleransiMin % 60),
                'jamMasukMin' => $jamMasukMin,
                'jamPulangMin' => $jamPulangMin,
                'batasToleransiMin' => $batasToleransiMin,
            ],
            'distribusi' => [
                'hadir' => $hadirCount,
                'tanpa_keterangan' => $tanpaKeteranganCount,
                'dinas' => $dinasCount,
                'cuti' => $cutiCount,
            ],
            'metrikHadir' => [
                'tepat_waktu' => $tepatWaktu,
                'toleransi_terlambat' => $toleransiCount,
                'terlambat' => $terlambat,
                'total' => $totalKehadiran,
            ],
            'dispensasi' => array_map(
                fn ($jenis, $count) => ['jenis' => $jenis, 'count' => $count],
                array_keys($dispensasiCounts),
                array_values($dispensasiCounts)
            ),
            'keterlambatan' => $keterlambatan,
            'total_keterlambatan' => array_sum(array_column($keterlambatan, 'menit_terlambat')),
            'total_psw' => array_sum(array_column($keterlambatan, 'menit_psw')),
            'harian' => $harian,
            'tren' => $tren,
            'detailHari' => $detailHari,
        ];
    }

    private static function buildDetailHari(Attendance $att, Carbon $date, string $hariFull, ?string $zonaMasuk, Employee $emp): array
    {
        $zonaLabel = match ($zonaMasuk) {
            'tepat' => 'Tepat waktu',
            'toleransi' => 'Toleransi terlambat',
            'terlambat' => 'Terlambat',
            default => 'Hadir',
        };

        $siteName = $emp->site?->nama_lokasi ?? '';
        $siteLat = $emp->site?->lat ?? 0;
        $siteLng = $emp->site?->lng ?? 0;
        $unitKerja = $emp->unit_kerja;

        $masuk = null;
        if ($att->clock_in_at) {
            $masuk = [
                'jenis_absen' => 'WFO',
                'jam' => Carbon::parse($att->clock_in_at)->format('H:i:s'),
                'lokasi' => $siteName,
                'timezone' => 'Asia/Makassar',
                'koordinat' => sprintf('%.6f, %.6f', (float) $att->lat_in, (float) $att->lng_in),
                'radius' => (int) ($att->distance_in_m ?? 0),
                'nama_lokasi_aktif' => $siteName,
                'koordinat_lokasi_aktif' => sprintf('%.6f, %.6f', $siteLat, $siteLng),
                'unit_kerja' => $unitKerja,
                'selfie_url' => $att->selfie_url,
            ];
        }

        $pulang = null;
        if ($att->clock_out_at) {
            $pulang = [
                'jenis_absen' => 'WFO',
                'jam' => Carbon::parse($att->clock_out_at)->format('H:i:s'),
                'lokasi' => $siteName,
                'timezone' => 'Asia/Makassar',
                'koordinat' => sprintf('%.6f, %.6f', (float) ($att->lat_out ?? 0), (float) ($att->lng_out ?? 0)),
                'radius' => (int) ($att->distance_in_m ?? 0),
                'nama_lokasi_aktif' => $siteName,
                'koordinat_lokasi_aktif' => sprintf('%.6f, %.6f', $siteLat, $siteLng),
                'unit_kerja' => $unitKerja,
            ];
        }

        return [
            'tgl' => $date->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'hari' => $hariFull,
            'status' => 'Hadir · '.$zonaLabel,
            'masuk' => $masuk,
            'pulang' => $pulang,
        ];
    }
}
