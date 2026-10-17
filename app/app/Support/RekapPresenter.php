<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\ShiftSchedule;
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
        // Hari kerja mengikuti setelan (bisa Sabtu/Minggu ikut kerja).
        // Jangan hardcode Sabtu+Minggu — rekap akan salah untuk kantor
        // dengan pola hari kerja berbeda. B3: karyawan boleh punya hari
        // kerja sendiri (libur bergeser); bila diisi, itu yang menang.
        $hariKerja = $emp->hariKerjaList($settings);
        $jamMasuk = $settings ? substr((string) $settings->jam_masuk, 0, 5) : '07:30';
        $jamPulang = $settings ? substr((string) $settings->jam_pulang, 0, 5) : '16:00';
        $toleransi = $settings ? (int) $settings->toleransi_late_menit : 15;

        [$hM, $mM] = array_map('intval', explode(':', $jamMasuk));
        [$hP, $mP] = array_map('intval', explode(':', $jamPulang));
        $jamMasukMin = $hM * 60 + $mM;
        $jamPulangMin = $hP * 60 + $mP;
        $batasToleransiMin = $jamMasukMin + $toleransi;

        // Attendance bulan itu. Relasi titik ikut dimuat: satu hari bisa
        // absen masuk di satu titik dan pulang di titik lain.
        $attendances = Attendance::with(['toleranceClaim', 'site', 'siteOut'])
            ->where('employee_id', $emp->id)
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

        // Jadwal shift (K3/A7). Hanya dimuat untuk pekerja shift; selain itu
        // jalur lama dipakai apa adanya sehingga rekap non-shift tidak berubah.
        $isShift = (bool) $emp->is_shift;
        $polaShift = $isShift ? ShiftResolver::patternFor($emp, $settings) : null;
        $jadwalShift = [];
        if ($isShift) {
            // whereDate, bukan whereBetween: kolom bertipe date dikirim
            // sebagai "Y-m-d 00:00:00" sehingga hari terakhir bulan bisa
            // terbuang dari rentang.
            $jadwalShift = ShiftSchedule::with('pattern')
                ->where('employee_id', $emp->id)
                ->whereDate('tanggal', '>=', $start->toDateString())
                ->whereDate('tanggal', '<=', $end->toDateString())
                ->get()
                ->keyBy(fn (ShiftSchedule $s) => $s->tanggal->format('Y-m-d'))
                ->all();
        }

        // Metrik counters
        $hadirCount = 0;
        $tanpaKeteranganCount = 0;
        $dinasCount = 0;
        $cutiCount = 0;
        $tepatWaktu = 0;
        $toleransiCount = 0;
        $terlambat = 0;
        // Baris hadir tanpa jam masuk (mis. hanya absen pulang yang tercatat).
        // Dihitung terpisah supaya rincian zona selalu berjumlah sama dengan
        // "Total kehadiran" — tanpa ini selisihnya terlihat seperti salah hitung.
        $tanpaJamMasuk = 0;

        $harian = [];
        $keterlambatan = [];
        $tren = [];
        $detailHari = [];
        $noKeterlambatan = 0;

        for ($day = 1; $day <= $lastDay; $day++) {
            $date = Carbon::create($year, $month, $day, 0, 0, 0, 'Asia/Makassar');
            $dateKey = $date->format('Y-m-d');
            $dow = $date->dayOfWeek; // 0 Sunday, 6 Saturday
            $isWeekend = ! in_array((string) $date->dayOfWeekIso, $hariKerja, true);
            $isHoliday = isset($holidays[$dateKey]);
            $isCuti = isset($cutiDates[$dateKey]);
            $isDinas = isset($dinasDates[$dateKey]);
            $att = $attendances[$dateKey] ?? null;
            $hariLabel = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][$dow];
            $hariFull = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$dow];

            // ── Acuan hari ini ───────────────────────────────────────────
            // Pekerja non-shift memakai kalender (akhir pekan + libur nasional).
            // Pekerja shift memakai JADWAL: hari yang dijadwalkan masuk tetap
            // dihitung hari kerja walau jatuh pada akhir pekan atau libur
            // nasional (P4 — di hari libur shift tetap "Absen Biasa").
            $sched = $jadwalShift[$dateKey] ?? null;
            $shiftKe = $sched?->shift_ke;
            $shiftLabel = $sched?->label();
            // Libur eksplisit dari jadwal (baris ada, shift_ke null) vs hari
            // yang belum dijadwalkan sama sekali — dua hal berbeda.
            $liburJadwal = $isShift && $sched !== null && $shiftKe === null;
            $masukHariIni = $isShift
                ? ($sched !== null && $shiftKe !== null)
                : (! $isWeekend && ! $isHoliday);

            $shiftMulaiMin = null;
            $shiftSelesaiMin = null;
            if ($isShift && $shiftKe !== null) {
                $polaRow = $sched->pattern ?: $polaShift;
                if ($polaRow) {
                    $window = $polaRow->windowFor($date, (int) $shiftKe);
                    $shiftMulaiMin = ((int) $window['mulai']->format('G') * 60) + (int) $window['mulai']->format('i');
                    $shiftSelesaiMin = ((int) $window['selesai']->format('G') * 60) + (int) $window['selesai']->format('i');
                }
            }

            // Jam acuan penilaian masuk: jadwal shift bila ada, kalau tidak
            // setelan global (perilaku lama untuk pekerja non-shift).
            $acuanMasukMin = $shiftMulaiMin ?? $jamMasukMin;

            $jamMasukStr = '—';
            $jamPulangStr = '—';
            $catatan = '';
            // D8: catatan pekerjaan yang ditulis karyawan saat absen pulang,
            // dibawa ikut ke rekap bulanan (layar, PDF, dan CSV).
            $catatanPulang = null;
            $trenMasukMin = null;
            $trenPulangMin = null;
            $zonaMasuk = null;
            // Titik yang benar-benar dipakai hari itu (bisa berbeda masuk/pulang).
            $pindahLokasi = false;
            $lokasiMasuk = null;
            $lokasiPulang = null;

            if ($isShift) {
                if ($shiftKe !== null) {
                    $catatan = 'Shift '.$shiftLabel;
                    if ($isHoliday) {
                        $catatan .= ' · Libur nasional: '.$holidays[$dateKey]['nama'];
                    }
                } elseif ($sched !== null) {
                    $catatan = 'Libur (jadwal shift)';
                } else {
                    $catatan = 'Belum ada jadwal shift';
                }
            } else {
                if ($isWeekend && ! $isHoliday) {
                    $catatan = "Bukan hari kerja ({$hariFull})";
                }
                if ($isHoliday) {
                    $catatan = ($isWeekend ? 'Bukan hari kerja & ' : '').'Libur nasional: '.$holidays[$dateKey]['nama'];
                }
            }
            if ($isCuti) {
                $catatan = 'Cuti '.$cutiDates[$dateKey]['jenis'];
                $cutiCount++;
            } elseif ($isDinas) {
                // Dinas TIDAK menambah "hadir". Kalau ditambahkan, hari itu
                // terhitung dua kali (sekali sebagai dinas, sekali sebagai
                // kehadiran) sehingga donat distribusi bisa berjumlah lebih
                // banyak daripada jumlah hari kerja, dan baris "Total
                // kehadiran" tidak sama dengan penjumlahan zona jam masuk.
                $catatan = 'Dinas: '.$dinasDates[$dateKey]['tujuan'];
                $dinasCount++;
            } elseif ($att) {
                $jamMasukStr = $att->clock_in_at ? Carbon::parse($att->clock_in_at)->format('H:i') : '—';
                $jamPulangStr = $att->clock_out_at ? Carbon::parse($att->clock_out_at)->format('H:i') : '—';
                $catatanPulang = $att->catatan_pulang ?: null;
                $hadirCount++;

                // Titik masuk dan pulang dibaca dari baris absensi, bukan dari
                // titik utama karyawan — titik utama bisa saja berbeda.
                $siteMasukRow = $att->site ?: $emp->site;
                $sitePulangRow = $att->siteOut ?: $siteMasukRow;
                $lokasiMasuk = $siteMasukRow?->nama_lokasi;
                $lokasiPulang = $sitePulangRow?->nama_lokasi;
                $pindahLokasi = $att->site_id !== null
                    && $att->site_out_id !== null
                    && (int) $att->site_id !== (int) $att->site_out_id;

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
                    if ($inMin <= $acuanMasukMin) {
                        $tepatWaktu++;
                        $zonaMasuk = 'tepat';
                        $catatan = 'Hadir tepat waktu';
                    } elseif ($inMin <= $acuanMasukMin + $toleransi) {
                        $toleransiCount++;
                        $zonaMasuk = 'toleransi';
                        $catatan = 'Masuk toleransi terlambat';
                    } else {
                        $terlambat++;
                        $zonaMasuk = 'terlambat';
                        $menitTerlambat = $inMin - $acuanMasukMin;
                        $noKeterlambatan++;
                        $keterlambatan[] = [
                            'no' => $noKeterlambatan,
                            'tgl' => $date->locale('id')->isoFormat('D MMM YYYY'),
                            'menit_terlambat' => $menitTerlambat,
                            'menit_psw' => 0,
                        ];
                        $catatan = "Terlambat {$menitTerlambat} menit";
                    }
                } else {
                    // Hadir tapi jam masuknya tidak tercatat (mis. hanya absen
                    // pulang). Tetap masuk hitungan kehadiran, tapi tidak bisa
                    // ditempatkan di zona tepat/toleransi/terlambat.
                    $tanpaJamMasuk++;
                }

                if ($isShift && $shiftKe !== null) {
                    // Pekerja shift: pulang cepat sudah lewat gerbang
                    // persetujuan Admin Wilayah di halaman absensi, jadi di
                    // rekap hanya ditandai — tidak dihitung sebagai
                    // kekurangan jam (PSW) secara sepihak.
                    if ($att->clock_out_at) {
                        $p = Carbon::parse($att->clock_out_at);
                        $outMin = (int) $p->format('G') * 60 + (int) $p->format('i');
                        $trenPulangMin = $outMin;
                        if ($shiftSelesaiMin !== null && $outMin < $shiftSelesaiMin) {
                            $catatan .= ($catatan ? ' · ' : '').'Pulang cepat (disetujui)';
                        }
                    } else {
                        $catatan .= ($catatan ? ' · ' : '').'Tidak ada absen pulang';
                    }
                } else {
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
                }

                if ($pindahLokasi) {
                    // Jawaban klien: satu kehadiran, tetapi hari itu ditandai
                    // berpindah lokasi supaya terbaca di rekap.
                    $catatan .= ($catatan ? ' · ' : '').'Berpindah lokasi: '.$lokasiMasuk.' ke '.$lokasiPulang;
                }

                // Detail per hari untuk kartu detail
                $detailHari[] = self::buildDetailHari($att, $date, $hariFull, $zonaMasuk, $emp);
            } else {
                // Hari kerja tanpa absen dan tanpa cuti/dinas. Pekerja shift
                // hanya dihitung "tanpa keterangan" pada hari yang benar-benar
                // dijadwalkan masuk (libur jadwal bukan pelanggaran).
                if ($isShift) {
                    if ($shiftKe !== null) {
                        $tanpaKeteranganCount++;
                        $catatan = 'Tidak ada absen masuk · Tidak ada absen pulang';
                    }
                } elseif ($masukHariIni) {
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
                'catatan_pulang' => $catatanPulang,
                // Titik absen hari itu (null bila tidak ada absensi).
                'berpindah_lokasi' => $pindahLokasi,
                'lokasi_masuk' => $lokasiMasuk,
                'lokasi_pulang' => $lokasiPulang,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_cuti' => $isCuti,
                'is_dinas' => $isDinas,
                'is_tanpa_keterangan' => (! $att && ! $isCuti && ! $isDinas && ($isShift ? $shiftKe !== null : $masukHariIni)),
                'is_shift' => $isShift,
                'shift' => $shiftLabel,
                'shift_ke' => $shiftKe,
                'shift_jam' => $shiftMulaiMin !== null ? sprintf('%02d:%02d', intdiv($shiftMulaiMin, 60), $shiftMulaiMin % 60) : null,
                'is_libur_jadwal' => $liburJadwal,
                'is_hari_kerja' => $masukHariIni,
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
                // Acuan "hari kerja" yang sudah sadar-shift — dipakai grafik
                // (React & PDF) untuk menandai hari tanpa absen.
                'is_hari_kerja' => $masukHariIni,
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
                'is_shift' => $isShift,
                'pola_shift' => $polaShift?->ringkasan(),
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
                'isShift' => $isShift,
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
                // Hadir tanpa jam masuk (hanya absen pulang). Ditampilkan
                // supaya zona + baris ini tepat berjumlah 'total'.
                'tanpa_jam_masuk' => $tanpaJamMasuk,
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
            'ada_pulang_cepat' => collect($harian)->contains(fn ($h) => str_contains((string) $h['catatan'], 'Pulang cepat')),
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

        // Titik masuk dan titik pulang bisa berbeda (absen di titik tambahan
        // atau berpindah titik di tengah hari). Keduanya dibaca dari baris
        // absensi; titik utama karyawan hanya cadangan untuk data lama yang
        // belum menyimpan site_id/site_out_id.
        $siteMasuk = $att->site ?: $emp->site;
        $sitePulang = $att->siteOut ?: $siteMasuk;

        $siteName = $siteMasuk?->nama_lokasi ?? '';
        $siteLat = $siteMasuk?->lat ?? 0;
        $siteLng = $siteMasuk?->lng ?? 0;

        $namaPulang = $sitePulang?->nama_lokasi ?? $siteName;
        $latPulang = $sitePulang?->lat ?? 0;
        $lngPulang = $sitePulang?->lng ?? 0;

        // Ditandai di rekap supaya hari yang berpindah titik tidak terbaca
        // sebagai hari biasa (jawaban klien: tetap satu kehadiran, ditandai).
        $pindahLokasi = $att->site_id !== null
            && $att->site_out_id !== null
            && (int) $att->site_id !== (int) $att->site_out_id;

        $unitKerja = $emp->unit_kerja;

        $masuk = null;
        if ($att->clock_in_at) {
            // Baris sintetis dari klaim toleransi tidak punya koordinat
            // (lat/lng null). Menampilkan "0.000000, 0.000000" terbaca seperti
            // absen dari Teluk Guinea, jadi ditandai sebagai isian manual.
            $adaKoordinat = $att->lat_in !== null && $att->lng_in !== null;
            $masuk = [
                'jenis_absen' => $adaKoordinat ? 'WFO' : 'Isian manual',
                'jam' => Carbon::parse($att->clock_in_at)->format('H:i:s'),
                'lokasi' => $siteName,
                'timezone' => 'Asia/Makassar',
                'koordinat' => $adaKoordinat
                    ? sprintf('%.6f, %.6f', (float) $att->lat_in, (float) $att->lng_in)
                    : null,
                'radius' => $adaKoordinat ? (int) ($att->distance_in_m ?? 0) : null,
                'nama_lokasi_aktif' => $siteName,
                'koordinat_lokasi_aktif' => sprintf('%.6f, %.6f', $siteLat, $siteLng),
                'unit_kerja' => $unitKerja,
                'selfie_url' => $att->selfieRouteUrl('masuk'),
            ];
        }

        $pulang = null;
        if ($att->clock_out_at) {
            $adaKoordinat = $att->lat_out !== null && $att->lng_out !== null;
            // Jarak pulang dihitung dari koordinat pulang ke titik tempat
            // karyawan benar-benar absen pulang. Memakai titik masuk membuat
            // absensi sah di titik lain terbaca seperti di luar radius.
            $radiusPulang = null;
            if ($adaKoordinat && $sitePulang) {
                $radiusPulang = AdminPresenter::haversineM(
                    (float) $att->lat_out,
                    (float) $att->lng_out,
                    (float) $latPulang,
                    (float) $lngPulang
                );
            }
            $pulang = [
                'jenis_absen' => $adaKoordinat ? 'WFO' : 'Isian manual',
                'jam' => Carbon::parse($att->clock_out_at)->format('H:i:s'),
                'lokasi' => $namaPulang,
                'timezone' => 'Asia/Makassar',
                'koordinat' => $adaKoordinat
                    ? sprintf('%.6f, %.6f', (float) $att->lat_out, (float) $att->lng_out)
                    : null,
                'radius' => $radiusPulang,
                'nama_lokasi_aktif' => $namaPulang,
                'koordinat_lokasi_aktif' => sprintf('%.6f, %.6f', $latPulang, $lngPulang),
                'unit_kerja' => $unitKerja,
                'catatan' => $att->catatan_pulang,
                'selfie_url' => $att->selfieRouteUrl('pulang'),
            ];
        }

        // Dispensasi (lupa absen) yang menempel pada hari ini — ditampilkan di
        // kartu detail supaya alasannya terbaca berdampingan dengan absennya.
        $claim = $att->toleranceClaim;
        $dispensasi = $claim ? [
            'jenis' => $claim->jenis === 'lupa_absen' ? 'Lupa Absen Datang' : 'Lupa Absen Pulang',
            'status' => match ($claim->status) {
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => 'Menunggu',
            },
            'alasan' => $claim->alasan,
            'note' => $claim->note,
            'tanggal_pengajuan' => $claim->created_at?->locale('id')->isoFormat('dddd, D MMMM YYYY'),
        ] : null;

        return [
            // id absensi: dipakai berkas PDF untuk membaca swafoto dari disk
            // privat (path-nya tidak ikut dikirim ke peramban).
            'attendance_id' => $att->id,
            'iso' => $date->format('Y-m-d'),
            'tgl' => $date->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'hari' => $hariFull,
            'status' => 'Hadir · '.$zonaLabel,
            // Satu kehadiran walau masuk dan pulang di titik berbeda.
            'berpindah_lokasi' => $pindahLokasi,
            'lokasi_masuk' => $siteName,
            'lokasi_pulang' => $namaPulang,
            'masuk' => $masuk,
            'pulang' => $pulang,
            'dispensasi' => $dispensasi,
        ];
    }
}
