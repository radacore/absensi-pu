{{--
    Rekap presensi perorangan — berkas PDF (A4 landscape).

    Dipakai oleh EmployeeRekapController::pdf() dan juga oleh karyawan untuk
    rekap dirinya sendiri. Seluruh gaya ditulis inline karena dompdf hanya
    mendukung CSS 2.1 dasar (tanpa Tailwind): tata letak memakai <table>.

    Semua angka berasal dari App\Support\RekapPresenter supaya rekap PDF,
    rekap CSV, dan rekap di layar tidak pernah berbeda angka.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi {{ $k['nama'] }} — {{ $p['label'] }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 10mm 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0F172A; }
        h1 { font-size: 14px; margin: 0; letter-spacing: 0.3px; }
        h2 { font-size: 10px; margin: 0 0 5px; text-transform: uppercase; letter-spacing: 0.6px; color: #334155; }
        p { margin: 0; }
        .muted { color: #64748B; }
        .small { font-size: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .nowrap { white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid > tbody > tr > td { vertical-align: top; }
        .page-break { page-break-before: always; }
        .keep { page-break-inside: avoid; }
        .section { margin-bottom: 12px; }
        .box { border: 1px solid #E2E8F0; border-radius: 4px; padding: 7px 9px; }
        .head { border-bottom: 2px solid #0F172A; padding-bottom: 8px; margin-bottom: 12px; }
        .logo { width: 42px; height: 42px; }
        .ident td { padding: 1.5px 0; vertical-align: top; }
        .ident .k { color: #64748B; width: 88px; }
        .stat { border: 1px solid #E2E8F0; padding: 6px 8px; }
        .stat .n { font-size: 16px; font-weight: bold; }
        .stat .l { font-size: 8px; color: #64748B; }
        .kv td { padding: 2.5px 0; border-bottom: 1px solid #F1F5F9; }
        .kv .v { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.data th { background: #F8FAFC; border: 1px solid #E2E8F0; padding: 4px 5px; text-align: left; font-size: 8px; color: #475569; }
        table.data td { border: 1px solid #E2E8F0; padding: 3.5px 5px; }
        /* Tabel rekap harian punya 31 baris; padding dirapatkan supaya
           sebulan penuh muat dalam satu halaman (tidak menyisakan satu baris
           yatim di halaman berikutnya). */
        table.data-harian td { padding: 2.2px 5px; }
        table.data tbody tr:nth-child(even) td { background: #FCFDFE; }
        .row-off td { background: #F8FAFC !important; color: #94A3B8; }
        .row-cut td { background: #FEF3C7 !important; }
        .row-dinas td { background: #EFF6FF !important; }
        .row-absent td { background: #FEE2E2 !important; }
        .row-shift td { background: #EFF6FF !important; color: #1E3A8A; }
        .tag { display: inline-block; padding: 1px 5px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 8px; color: #334155; }
        .footer { border-top: 1px solid #E2E8F0; padding-top: 6px; margin-top: 10px; font-size: 8px; color: #64748B; }
        .sign { margin-top: 18px; font-size: 8px; color: #334155; }
        .sign .line { margin-top: 34px; border-top: 1px solid #94A3B8; width: 150px; }

        /* Legenda diagram donat */
        table.legenda { width: 100%; border-collapse: collapse; }
        table.legenda td { padding: 2px 0; font-size: 9px; }
        table.legenda td.dot { width: 14px; padding-right: 4px; }
        table.legenda td.dot span { display: block; width: 9px; height: 9px; }
        table.legenda .v { text-align: right; font-family: DejaVu Sans Mono, monospace; font-weight: bold; }

        /* Kartu detail harian */
        table.kartu { width: 100%; border-collapse: collapse; border: 1px solid #E2E8F0; border-radius: 4px; margin-bottom: 7px; }
        table.kartu > tbody > tr > td { padding: 0; }
        .kartu-h { padding: 5px 7px; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; }
        .kartu-h .tgl { font-weight: bold; font-size: 9.5px; }
        .kartu-b { padding: 6px 7px; }
        .kartu-libur .kartu-h { background: #F1F5F9; color: #475569; }
        .kartu-cuti .kartu-h { background: #FEF3C7; color: #92400E; }
        .kartu-dinas .kartu-h { background: #EFF6FF; color: #1E3A8A; }
        .kartu-alfa .kartu-h { background: #FEE2E2; color: #991B1B; }
        .kartu-hadir .kartu-h { background: #DCFCE7; color: #065F46; }
        .chip { display: inline-block; padding: 1px 6px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 8px; background: #FFFFFF; }
        table.absen { width: 100%; border-collapse: collapse; }
        table.absen > tbody > tr > td { vertical-align: top; }
        .absen-blok { border: 1px solid #E2E8F0; padding: 5px 6px; }
        .absen-ttl { font-weight: bold; font-size: 9px; margin-bottom: 3px; }
        .foto { border: 1px solid #E2E8F0; background: #F8FAFC; }
        .foto-kosong { color: #94A3B8; font-size: 7.5px; text-align: center; padding: 16px 4px; }
        table.det { width: 100%; border-collapse: collapse; }
        table.det td { padding: 1.5px 0; vertical-align: top; font-size: 8px; }
        table.det td.dk { color: #64748B; width: 62px; }
        .dinas-grid td { padding: 1.5px 6px 1.5px 0; vertical-align: top; font-size: 8.5px; }
        .dinas-grid td.dk { color: #64748B; width: 96px; }
    </style>
</head>
<body>

@php
    // Kelompok baris harian per minggu supaya jadwal shift mudah dibaca.
    $hariKerjaShift = collect($harian)->filter(fn ($h) => $h['is_shift'] && $h['shift_ke'] !== null)->count();
    $hariLiburJadwal = collect($harian)->filter(fn ($h) => $h['is_shift'] && $h['shift_ke'] === null && $h['shift'] === null && $h['catatan'] === 'Libur (jadwal shift)')->count();
@endphp

{{-- ── Kop ──────────────────────────────────────────────────────────── --}}
<div class="head">
    <table class="grid">
        <tr>
            <td style="width: 52px;">
                @if ($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>
            <td>
                <h1>REKAP PRESENSI PERORANGAN</h1>
                <p class="small muted">Balai Besar Wilayah Sungai Pompengan Jeneberang</p>
                <p class="small muted">Periode {{ $p['label'] }} &middot; {{ $k['region'] ?: '—' }} &middot; {{ $k['site'] ?: '—' }}</p>
            </td>
            <td class="right small muted" style="width: 190px;">
                <p>Dicetak: {{ $p['diExportPada'] }} WITA</p>
                <p>Status data per {{ $p['tanggalStatusData'] }}</p>
                <p>Rekap absensi diproses H-1 tanggal berjalan</p>
            </td>
        </tr>
    </table>
</div>

{{-- ── Identitas + distribusi ───────────────────────────────────────── --}}
<div class="section keep">
    <table class="grid">
        <tr>
            <td style="width: 46%; padding-right: 8px;">
                <div class="box">
                    <h2>Identitas</h2>
                    <table class="ident">
                        <tr><td class="k">Nama</td><td class="bold">{{ $k['nama'] }}</td></tr>
                        <tr><td class="k">NIK</td><td class="mono">{{ $k['nik'] }}</td></tr>
                        <tr><td class="k">NIP</td><td class="mono">{{ $k['nip'] }}</td></tr>
                        <tr><td class="k">Jabatan</td><td>{{ $k['jabatan'] }}</td></tr>
                        <tr><td class="k">Golongan</td><td>{{ $k['golongan'] }}</td></tr>
                        <tr><td class="k">Status</td><td>{{ $k['status_kepegawaian'] }}</td></tr>
                        <tr><td class="k">Unit kerja</td><td>{{ $k['unit_kerja'] }}</td></tr>
                        <tr>
                            <td class="k">Pola kerja</td>
                            <td>{{ $k['is_shift'] ? 'Shift' : 'Jam kerja tetap' }}@if (! empty($k['pola_shift'])) · {{ $k['pola_shift'] }}@endif</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 54%;">
                <div class="box">
                    <h2>Distribusi kehadiran</h2>
                    <table class="grid">
                        <tr>
                            <td style="width: 128px; vertical-align: middle;">
                                <img src="{{ $distribusiSvg }}" width="124" height="124" alt="Distribusi kehadiran">
                            </td>
                            <td style="vertical-align: middle;">
                                <table class="legenda">
                                    <tr><td class="dot"><span style="background: #10B981;"></span></td><td>Hadir</td><td class="v">{{ $d['hadir'] }}</td></tr>
                                    <tr><td class="dot"><span style="background: #EF4444;"></span></td><td>Tanpa keterangan</td><td class="v">{{ $d['tanpa_keterangan'] }}</td></tr>
                                    <tr><td class="dot"><span style="background: #3B82F6;"></span></td><td>Dinas</td><td class="v">{{ $d['dinas'] }}</td></tr>
                                    <tr><td class="dot"><span style="background: #F59E0B;"></span></td><td>Cuti</td><td class="v">{{ $d['cuti'] }}</td></tr>
                                </table>
                                @if ($k['is_shift'])
                                    <p class="small muted" style="margin-top: 6px;">
                                        Pekerja shift dinilai dari <span class="bold">jadwal shift</span>, bukan akhir pekan:
                                        {{ $hariKerjaShift }} hari dijadwalkan masuk, {{ $hariLiburJadwal }} hari libur jadwal pada periode ini.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ── Metrik ringkas ──────────────────────────────────────────────── --}}
<div class="section keep">
    <table class="grid">
        <tr>
            <td style="width: 33.33%; padding-right: 6px;">
                <div class="box">
                    <h2>Hadir — rincian jam masuk</h2>
                    <table class="kv">
                        @if ($k['is_shift'])
                            <tr><td>Tepat waktu (sesuai jadwal shift)</td><td class="v">{{ $m['tepat_waktu'] }}</td></tr>
                            <tr><td>Toleransi ({{ $s['toleransi'] }} menit)</td><td class="v">{{ $m['toleransi_terlambat'] }}</td></tr>
                            <tr><td>Terlambat</td><td class="v">{{ $m['terlambat'] }}</td></tr>
                        @else
                            <tr><td>Tepat waktu (&le; {{ $s['jamMasuk'] }})</td><td class="v">{{ $m['tepat_waktu'] }}</td></tr>
                            <tr><td>Toleransi ({{ $s['jamMasuk'] }}–{{ $s['batasToleransi'] }})</td><td class="v">{{ $m['toleransi_terlambat'] }}</td></tr>
                            <tr><td>Terlambat (&gt; {{ $s['batasToleransi'] }})</td><td class="v">{{ $m['terlambat'] }}</td></tr>
                        @endif
                        @if (($m['tanpa_jam_masuk'] ?? 0) > 0)
                            <tr><td>Hadir tanpa jam masuk</td><td class="v">{{ $m['tanpa_jam_masuk'] }}</td></tr>
                        @endif
                        <tr class="bold"><td>Total kehadiran</td><td class="v">{{ $m['total'] }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="width: 33.33%; padding-right: 6px;">
                <div class="box">
                    <h2>Lainnya</h2>
                    <table class="kv">
                        <tr><td>Tanpa keterangan</td><td class="v">{{ $d['tanpa_keterangan'] }}</td></tr>
                        <tr><td>Cuti</td><td class="v">{{ $d['cuti'] }}</td></tr>
                        <tr><td>Dinas</td><td class="v">{{ $d['dinas'] }}</td></tr>
                        @if ($k['is_shift'])
                            <tr><td>Pulang cepat (disetujui)</td><td class="v">{{ $adaPulangCepat ? 'Ada' : '—' }}</td></tr>
                        @endif
                    </table>
                </div>
            </td>
            <td style="width: 33.33%;">
                <div class="box">
                    <h2>Dispensasi per jenis</h2>
                    @if (count($dispensasi) === 0)
                        <p class="muted">Tidak ada dispensasi pada periode ini.</p>
                    @else
                        <table class="kv">
                            @foreach ($dispensasi as $dis)
                                <tr><td>{{ $dis['jenis'] }}</td><td class="v">{{ $dis['count'] }}</td></tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ── Keterlambatan & PSW ─────────────────────────────────────────── --}}
<div class="section keep">
    <h2>Rekap keterlambatan &amp; pulang sebelum waktunya (PSW)</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 34px;">No.</th>
                <th>Tanggal</th>
                <th class="right" style="width: 110px;">Menit terlambat</th>
                <th class="right" style="width: 110px;">Menit PSW</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($keterlambatan as $r)
                <tr>
                    <td>{{ $r['no'] }}</td>
                    <td>{{ $r['tgl'] }}</td>
                    <td class="right mono">{{ $r['menit_terlambat'] ?: '—' }}</td>
                    <td class="right mono">{{ $r['menit_psw'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted">Tidak ada keterlambatan atau PSW pada periode ini.</td></tr>
            @endforelse
            @if (count($keterlambatan) > 0)
                <tr class="bold">
                    <td colspan="2" class="right">Total</td>
                    <td class="right mono">{{ $totalKeterlambatan }}</td>
                    <td class="right mono">{{ $totalPsw }}</td>
                </tr>
            @endif
        </tbody>
    </table>
    @if ($k['is_shift'])
        <p class="small muted" style="margin-top: 4px;">
            Pulang lebih awal oleh pekerja shift wajib lewat persetujuan Admin Wilayah, sehingga
            tidak dihitung sebagai PSW — pada tabel harian ditandai &ldquo;Pulang cepat (disetujui)&rdquo;.
        </p>
    @endif
</div>

{{-- ── Rekap tren jam absen ────────────────────────────────────────── --}}
@if (! empty($trenMasukImg) || ! empty($trenPulangImg))
<div class="section keep">
    <h2>Tren jam absen hari kerja</h2>
    <table class="grid">
        <tr>
            @if (! empty($trenMasukImg))
                <td style="width: 50%; padding-right: 6px;"><div class="box"><img src="{{ $trenMasukImg }}" width="500" height="139" alt="Grafik tren jam masuk"></div></td>
            @endif
            @if (! empty($trenPulangImg))
                <td style="width: 50%;"><div class="box"><img src="{{ $trenPulangImg }}" width="500" height="139" alt="Grafik tren jam pulang"></div></td>
            @endif
        </tr>
    </table>
    <p class="small muted" style="margin-top: 4px;">
        Titik pada garis = jam absen hari kerja. Titik di dasar grafik = hari kerja tanpa absen.
    </p>
</div>
@endif

{{-- ── Rekap harian ────────────────────────────────────────────────── --}}
<div class="section page-break">
    <h2>Rekap harian {{ $p['label'] }}</h2>
    @if ($k['is_shift'])
        <p class="small muted" style="margin-bottom: 4px;">
            Tanggal yang dijadwalkan masuk ditandai warna; &ldquo;Libur&rdquo; berarti libur jadwal,
            &ldquo;Belum dijadwalkan&rdquo; berarti Admin Wilayah belum menyusun jadwal untuk hari itu.
        </p>
    @endif
    <table class="data data-harian">
        <thead>
            <tr>
                <th style="width: 78px;">Tanggal</th>
                <th style="width: 34px;">Hari</th>
                @if ($k['is_shift'])
                    <th style="width: 74px;">Shift</th>
                    <th style="width: 46px;">Mulai</th>
                @endif
                <th style="width: 58px;">Masuk</th>
                <th style="width: 58px;">Pulang</th>
                <th>Keterangan</th>
                <th style="width: 168px;">Catatan pekerjaan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($harian as $h)
                <tr @class([
                    'row-off' => ! $h['is_hari_kerja'] && ! $h['is_cuti'] && ! $h['is_dinas'],
                    'row-cut' => $h['is_cuti'],
                    'row-dinas' => $h['is_dinas'],
                    'row-absent' => $h['is_tanpa_keterangan'],
                    'row-shift' => $k['is_shift'] && $h['is_hari_kerja'] && ! $h['is_cuti'] && ! $h['is_dinas'] && ! $h['is_tanpa_keterangan'],
                ])>
                    <td class="nowrap">{{ $h['tgl'] }}</td>
                    <td>{{ $h['hari'] }}</td>
                    @if ($k['is_shift'])
                        <td class="bold">
                            @if ($h['is_libur_jadwal'])
                                Libur
                            @elseif ($h['shift'])
                                {{ $h['shift'] }}
                            @else
                                Belum dijadwalkan
                            @endif
                        </td>
                        <td class="mono">{{ $h['shift_jam'] ?? '—' }}</td>
                    @endif
                    <td class="mono">{{ $h['jam_masuk'] }}</td>
                    <td class="mono">{{ $h['jam_pulang'] }}</td>
                    <td>{{ $h['catatan'] ?: '—' }}</td>
                    <td>{{ $h['catatan_pulang'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- ── Detail harian (kartu per hari + swafoto) ────────────────────── --}}
<div class="section page-break">
    <h2>Detail harian {{ $p['label'] }}</h2>
    <p class="small muted" style="margin-bottom: 6px;">
        Satu kartu per hari, mengikuti kartu audit di aplikasi: hari libur, tanpa keterangan, cuti,
        dinas, dan kehadiran lengkap dengan swafoto masuk &amp; pulang.
    </p>

    @foreach ($harian as $h)
        @php $d = $detailPerHari[$h['iso']] ?? null; @endphp

        @if ($h['is_cuti'] && $h['cuti'])
            <table class="kartu keep kartu-cuti">
                <tr><td class="kartu-h">
                    <table class="grid"><tr>
                        <td class="tgl">{{ $h['tgl'] }}</td>
                        <td class="right"><span class="chip">Cuti {{ $h['cuti']['jenis'] }}</span></td>
                    </tr></table>
                </td></tr>
                <tr><td class="kartu-b">
                    <table class="det">
                        <tr><td class="dk">Alasan</td><td>{{ $h['cuti']['alasan'] ?: '—' }}</td></tr>
                    </table>
                </td></tr>
            </table>
        @elseif ($h['is_dinas'] && $h['dinas'])
            <table class="kartu keep kartu-dinas">
                <tr><td class="kartu-h">
                    <table class="grid"><tr>
                        <td class="tgl">{{ $h['tgl'] }}</td>
                        <td class="right"><span class="chip">Dinas</span></td>
                    </tr></table>
                </td></tr>
                <tr><td class="kartu-b">
                    <table class="grid dinas-grid">
                        <tr><td class="dk">Nomor Surat</td><td>{{ $h['dinas']['nomor_surat'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Tanggal Dinas</td><td>{{ $h['dinas']['tanggal_dinas'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Tanggal Pengajuan</td><td>{{ $h['dinas']['tanggal_pengajuan'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Tujuan</td><td>{{ $h['dinas']['tujuan'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Transportasi</td><td>{{ $h['dinas']['transportasi'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Pembebanan Anggaran</td><td>{{ $h['dinas']['pembebanan_anggaran'] ?: '—' }}</td></tr>
                        <tr><td class="dk">Keterangan</td><td>{{ $h['dinas']['keterangan'] ?: '—' }}</td></tr>
                    </table>
                </td></tr>
            </table>
        @elseif ($h['is_tanpa_keterangan'])
            <table class="kartu keep kartu-alfa">
                <tr><td class="kartu-h">
                    <table class="grid"><tr>
                        <td class="tgl">{{ $h['tgl'] }}</td>
                        <td class="right"><span class="chip">Tanpa keterangan</span></td>
                    </tr></table>
                </td></tr>
                <tr><td class="kartu-b">
                    <p class="small">Tidak ada absen masuk &middot; Tidak ada absen pulang.</p>
                </td></tr>
            </table>
        @elseif ($d)
            <table class="kartu keep kartu-hadir">
                <tr><td class="kartu-h">
                    <table class="grid"><tr>
                        <td class="tgl">{{ $d['tgl'] }}</td>
                        <td class="right">
                            <span class="chip">{{ $d['status'] }}</span>
                            @if ($k['is_shift'] && ! empty($h['shift']))
                                <span class="chip">Shift {{ $h['shift'] }}@if (! empty($h['shift_jam'])) · {{ $h['shift_jam'] }}@endif</span>
                            @endif
                        </td>
                    </tr></table>
                </td></tr>
                <tr><td class="kartu-b">
                    <table class="absen">
                        <tr>
                            @foreach (['masuk' => 'Absen Masuk', 'pulang' => 'Absen Pulang'] as $arah => $judul)
                                <td style="width: 50%; @if ($arah === 'masuk') padding-right: 5px; @endif">
                                    <div class="absen-blok">
                                        <p class="absen-ttl">{{ $judul }}</p>
                                        @php $b = $d[$arah]; @endphp
                                        <table class="grid">
                                            <tr>
                                                <td style="width: 100px;">
                                                    @if ($b && ! empty($b['foto']))
                                                        <img src="{{ $b['foto']['src'] }}" width="{{ $b['foto']['width'] }}" height="{{ $b['foto']['height'] }}" alt="Swafoto {{ $judul }}">
                                                    @else
                                                        <div class="foto-kosong" style="width: 92px; height: 122px;">Tidak ada foto</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($b)
                                                        <table class="det">
                                                            <tr><td class="dk">Jenis Absen</td><td>{{ $b['jenis_absen'] }}</td></tr>
                                                            <tr><td class="dk">Jam</td><td class="mono">{{ $b['jam'] }}</td></tr>
                                                            <tr><td class="dk">Lokasi</td><td>{{ $b['lokasi'] ?: '—' }}</td></tr>
                                                            <tr><td class="dk">Timezone</td><td>{{ $b['timezone'] }}</td></tr>
                                                            <tr><td class="dk">Koordinat</td><td class="mono">{{ $b['koordinat'] ?: '—' }}</td></tr>
                                                            <tr><td class="dk">Radius</td><td>{{ $b['radius'] !== null ? $b['radius'].' m dari titik kantor' : '—' }}</td></tr>
                                                            <tr><td class="dk">Unit Kerja</td><td>{{ $b['unit_kerja'] ?: '—' }}</td></tr>
                                                            @if ($arah === 'pulang')
                                                                <tr><td class="dk">Catatan</td><td>{{ $b['catatan'] ?: '—' }}</td></tr>
                                                            @endif
                                                        </table>
                                                    @else
                                                        <p class="small muted">Tidak ada absen {{ $arah }}.</p>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    </table>

                    @if (! empty($d['dispensasi']))
                        <p class="small" style="margin-top: 4px;">
                            <span class="chip">Dispensasi · {{ $d['dispensasi']['jenis'] }} · {{ $d['dispensasi']['status'] }}</span>
                            @if (! empty($d['dispensasi']['tanggal_pengajuan']))
                                &middot; Diajukan {{ $d['dispensasi']['tanggal_pengajuan'] }}
                            @endif
                            <br>{{ $d['dispensasi']['alasan'] }}
                            @if (! empty($d['dispensasi']['note'])) &middot; Catatan admin: {{ $d['dispensasi']['note'] }} @endif
                        </p>
                    @endif
                </td></tr>
            </table>
        @else
            <table class="kartu keep kartu-libur">
                <tr><td class="kartu-h">
                    <table class="grid"><tr>
                        <td class="tgl">{{ $h['tgl'] }}</td>
                        <td class="right">
                            <span class="chip">
                                @if ($h['is_holiday'])
                                    Hari libur nasional{{ ! empty($h['holiday']['nama']) ? ' · '.$h['holiday']['nama'] : '' }}
                                @elseif ($h['is_libur_jadwal'])
                                    Libur jadwal shift
                                @else
                                    Bukan hari kerja
                                @endif
                            </span>
                        </td>
                    </tr></table>
                </td></tr>
            </table>
        @endif
    @endforeach
</div>

{{-- ── Tanda tangan + footer ───────────────────────────────────────── --}}
<div class="keep">
    <table class="grid sign">
        <tr>
            <td style="width: 60%;">
                <p class="small">Catatan: rekap ini dihasilkan otomatis oleh sistem presensi BBWS Pompengan Jeneberang.</p>
            </td>
            <td class="center" style="width: 40%;">
                <p class="small">{{ $tanggalCetak }}</p>
                <p class="small">Admin Wilayah</p>
                <div class="line" style="margin-left: auto; margin-right: auto;"></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    Rekap presensi perorangan — {{ $k['nik'] }} ({{ $p['label'] }}) &middot; Di-export: {{ $p['diExportPada'] }} WITA
</div>

</body>
</html>
