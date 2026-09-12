import TrenJamChart from './TrenJamChart';

const namaBulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function formatTimestamp(iso) {
    const d = new Date(iso.replace(' ', 'T'));
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
}

/**
 * RekapView — komponen halaman rekap presensi perorangan.
 * Dipakai oleh Admin/EmployeeRekap dan Karyawan/RekapDetail.
 *
 * Props:
 *  - rekap: hasil RekapPresenter::buildRekap()
 *  - onChangeBulan: (bulan YYYY-MM) => void
 *  - backHref: string
 */
export default function RekapView({ rekap, onChangeBulan, backHref }) {
    const k = rekap.karyawan;
    const p = rekap.periode;
    const d = rekap.distribusi;
    const m = rekap.metrikHadir;
    const s = rekap.settings;

    const cetakPDF = () => {
        const ts = formatTimestamp(p.diExportPada);
        const original = document.title;
        document.title = `Rekap presensi perorangan - ${k.nik} (${p.namaBulan} ${p.year})-${ts}`;
        setTimeout(() => {
            window.print();
            setTimeout(() => { document.title = original; }, 1000);
        }, 50);
    };

    // Bulan option: 12 bulan terakhir
    const bulanOptions = [];
    const now = new Date();
    for (let i = 0; i < 24; i++) {
        const dt = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const val = `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`;
        bulanOptions.push({ val, label: `${namaBulan[dt.getMonth()]} ${dt.getFullYear()}` });
    }

    return (
        <div className="rekap-print bg-white text-[#0F172A]">
            {/* Toolbar (tidak di-print) */}
            <div className="no-print bg-white border-b border-[#E2E8F0] px-4 py-3 flex flex-wrap items-center justify-between gap-3 sticky top-0 z-10">
                <div className="flex items-center gap-3">
                    {backHref && (
                        <a href={backHref} className="text-xs font-medium text-[#334155] bg-[#F1F5F9] px-3 py-2 rounded-lg hover:bg-[#E2E8F0]">← Kembali</a>
                    )}
                    <div>
                        <p className="text-xs text-[#64748B]">Rekap Presensi Perorangan</p>
                        <h1 className="text-sm font-semibold text-[#0F172A]">{k.nama} · {p.label}</h1>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-[#334155]">Bulan
                        <select
                            value={`${p.year}-${String(p.month).padStart(2, '0')}`}
                            onChange={(e) => onChangeBulan(e.target.value)}
                            className="ml-2 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] px-3 py-1.5 text-sm outline-none"
                        >
                            {bulanOptions.map((b) => <option key={b.val} value={b.val}>{b.label}</option>)}
                        </select>
                    </label>
                    <button type="button" onClick={cetakPDF} className="bg-[#0F172A] hover:bg-[#1E3A8A] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">⬇ Cetak PDF</button>
                </div>
            </div>

            <div className="rekap-content px-6 py-6 max-w-[1200px] mx-auto space-y-6">
                {/* Header karyawan */}
                <div className="rekap-header pb-4 border-b border-[#E2E8F0]">
                    <p className="text-xs text-[#64748B]">Rekap presensi perorangan - {k.nik} ({p.namaBulan} {p.year})</p>
                    <h1 className="text-xl font-bold text-[#0F172A] mt-1">{k.nama} <span className="text-sm font-normal text-[#64748B]">({k.nik})</span></h1>
                    <p className="text-sm text-[#334155] mt-1">Jabatan: {k.jabatan}</p>
                    <p className="text-sm text-[#334155]">Golongan: {k.golongan}</p>
                    <p className="text-sm text-[#334155]">Status pegawai: {k.status_kepegawaian}</p>
                    <p className="text-sm text-[#334155]">Unit: {k.unit_kerja}</p>
                    <p className="text-xs text-[#94A3B8] mt-2 italic">Rekap absensi diproses H-1 tanggal berjalan. Status data tanggal: {p.tanggalStatusData}.</p>
                </div>

                {/* Distribusi kehadiran */}
                <section>
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Distribusi kehadiran</h2>
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <Kartu label="Hadir" value={d.hadir} accent="bg-[#DCFCE7] text-[#065F46] border-[#86EFAC]" />
                        <Kartu label="Tanpa keterangan" value={d.tanpa_keterangan} accent="bg-[#FEE2E2] text-[#991B1B] border-[#FCA5A5]" />
                        <Kartu label="Dinas" value={d.dinas} accent="bg-[#EFF6FF] text-[#1E3A8A] border-[#BFDBFE]" />
                        <Kartu label="Cuti" value={d.cuti} accent="bg-[#FEF3C7] text-[#92400E] border-[#FCD34D]" />
                    </div>
                </section>

                {/* Metrik ringkas */}
                <section>
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Metrik ringkas · hadir & lainnya</h2>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <MetrikGroup title="Hadir (rincian jam masuk)">
                            <MetrikRow label={`Tepat waktu (≤${s.jamMasuk})`} value={m.tepat_waktu} tone="ok" />
                            <MetrikRow label={`Toleransi terlambat (${s.jamMasuk}–${s.batasToleransi})`} value={m.toleransi_terlambat} tone="warn" />
                            <MetrikRow label={`Terlambat (>${s.batasToleransi})`} value={m.terlambat} tone="err" />
                            <MetrikRow label="Total kehadiran" value={m.total} tone="total" />
                        </MetrikGroup>
                        <MetrikGroup title="Lainnya">
                            <MetrikRow label="Tanpa keterangan" value={d.tanpa_keterangan} tone="err" />
                            <MetrikRow label="Cuti" value={d.cuti} tone="warn" />
                            <MetrikRow label="Dinas" value={d.dinas} tone="info" />
                        </MetrikGroup>
                        <MetrikGroup title="Dispensasi per jenis">
                            {rekap.dispensasi.length === 0
                                ? <p className="text-sm text-[#94A3B8]">Tidak ada dispensasi bulan ini.</p>
                                : rekap.dispensasi.map((r) => <MetrikRow key={r.jenis} label={r.jenis} value={r.count} tone="info" />)}
                        </MetrikGroup>
                    </div>
                </section>

                {/* Rekap keterlambatan & PSW */}
                <section>
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Rekap keterlambatan &amp; PSW</h2>
                    <table className="w-full text-sm border border-[#E2E8F0] rounded-lg overflow-hidden">
                        <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                            <tr>
                                <th className="text-left px-3 py-2 w-12">No.</th>
                                <th className="text-left px-3 py-2">Tanggal</th>
                                <th className="text-right px-3 py-2">Menit Terlambat</th>
                                <th className="text-right px-3 py-2">Menit PSW</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F1F5F9]">
                            {rekap.keterlambatan.length === 0 && (
                                <tr><td colSpan="4" className="px-3 py-3 text-center text-[#94A3B8] italic">Tidak ada keterlambatan atau PSW pada periode ini.</td></tr>
                            )}
                            {rekap.keterlambatan.map((r) => (
                                <tr key={r.no}>
                                    <td className="px-3 py-2 text-[#0F172A]">{r.no}</td>
                                    <td className="px-3 py-2 text-[#0F172A]">{r.tgl}</td>
                                    <td className="px-3 py-2 text-right font-mono text-[#991B1B]">{r.menit_terlambat || '—'}</td>
                                    <td className="px-3 py-2 text-right font-mono text-[#9A3412]">{r.menit_psw || '—'}</td>
                                </tr>
                            ))}
                            {rekap.keterlambatan.length > 0 && (
                                <tr className="bg-[#F8FAFC] font-semibold">
                                    <td colSpan="2" className="px-3 py-2 text-right text-[#334155]">Total</td>
                                    <td className="px-3 py-2 text-right font-mono">{rekap.total_keterlambatan}</td>
                                    <td className="px-3 py-2 text-right font-mono">{rekap.total_psw}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>

                {/* Tren jam absen hari kerja */}
                <section>
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Tren jam absen hari kerja</h2>
                    <p className="text-xs text-[#64748B] mb-3">Ringkasan per hari dalam bulan. Zona masuk: tepat ≤{s.jamMasuk}, toleransi {s.jamMasuk}–{s.batasToleransi}, terlambat &gt;{s.batasToleransi}. Pulang: jam riil ditampilkan.</p>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 chart-grid">
                        <TrenJamChart tren={rekap.tren} mode="masuk" settings={s} />
                        <TrenJamChart tren={rekap.tren} mode="pulang" settings={s} />
                    </div>
                </section>

                {/* Rekap harian kompak */}
                <section className="page-break-before">
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Rekap harian</h2>
                    <table className="w-full text-sm border border-[#E2E8F0] rounded-lg overflow-hidden">
                        <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                            <tr>
                                <th className="text-left px-3 py-2">Tanggal</th>
                                <th className="text-left px-3 py-2 w-16">Hari</th>
                                <th className="text-left px-3 py-2 w-24">Jam masuk</th>
                                <th className="text-left px-3 py-2 w-24">Jam pulang</th>
                                <th className="text-left px-3 py-2">Catatan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F1F5F9]">
                            {rekap.harian.map((h) => (
                                <tr key={h.iso} className={h.is_weekend || h.is_holiday ? 'bg-[#F8FAFC]/60' : ''}>
                                    <td className="px-3 py-2 text-[#0F172A]">{h.tgl}</td>
                                    <td className="px-3 py-2 text-[#334155]">{h.hari}</td>
                                    <td className="px-3 py-2 font-mono text-[#0F172A]">{h.jam_masuk}</td>
                                    <td className="px-3 py-2 font-mono text-[#0F172A]">{h.jam_pulang}</td>
                                    <td className="px-3 py-2 text-xs text-[#475569]">{h.catatan || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                {/* Kartu detail per hari (hari libur / cuti / dinas / hadir) */}
                <section className="page-break-before">
                    <h2 className="text-sm font-semibold text-[#0F172A] mb-3">Detail Harian</h2>
                    <p className="text-xs text-[#64748B] mb-3">Tampilan mengikuti kartu di halaman audit (hari libur, tanpa keterangan, hadir dengan detail masuk/pulang, dinas, cuti).</p>
                    <div className="space-y-3">
                        {rekap.harian.map((h) => {
                            const detail = rekap.detailHari.find((d2) => d2.tgl.includes(h.tgl.replace(/ Agt /, ' Agustus ')));
                            return <KartuHari key={h.iso} h={h} detail={detail} />;
                        })}
                    </div>
                </section>

                <div className="text-xs text-[#94A3B8] pt-4 border-t border-[#E2E8F0] mt-6">
                    Rekap presensi perorangan - {k.nik} ({p.label}) · Di-export: {p.diExportPada} WITA
                </div>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                @media print {
                    @page { size: A4 landscape; margin: 8mm; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: white !important; }
                    .no-print { display: none !important; }
                    .rekap-content { padding: 0 !important; max-width: 100% !important; }
                    .page-break-before { page-break-before: always; }
                    section { page-break-inside: avoid; }
                    .chart-grid { grid-template-columns: 1fr 1fr !important; }
                    table { font-size: 10.5px; }
                }
            ` }} />
        </div>
    );
}

function Kartu({ label, value, accent }) {
    return (
        <div className={`rounded-lg border p-3 ${accent}`}>
            <p className="text-xs font-medium opacity-80">{label}</p>
            <p className="text-2xl font-bold mt-1">{value}</p>
        </div>
    );
}

function MetrikGroup({ title, children }) {
    return (
        <div className="bg-[#F8FAFC] rounded-lg border border-[#E2E8F0] p-4">
            <p className="text-xs font-semibold text-[#334155] mb-3 uppercase tracking-wider">{title}</p>
            <div className="space-y-2">{children}</div>
        </div>
    );
}

function MetrikRow({ label, value, tone = 'default' }) {
    const toneClass = {
        ok: 'text-[#065F46]',
        warn: 'text-[#92400E]',
        err: 'text-[#991B1B]',
        info: 'text-[#1E3A8A]',
        total: 'text-[#0F172A] font-bold',
        default: 'text-[#334155]',
    }[tone] || 'text-[#334155]';
    return (
        <div className="flex items-center justify-between text-sm">
            <span className="text-[#475569]">{label}</span>
            <span className={`font-mono ${toneClass}`}>{value}</span>
        </div>
    );
}

function KartuHari({ h, detail }) {
    // Hari libur / weekend
    if (h.is_holiday || h.is_weekend) {
        const label = h.is_holiday ? (h.is_weekend ? 'Akhir pekan · ' : '') + 'Libur Nasional' : `Akhir pekan (${h.hari === 'Sab' ? 'Sabtu' : 'Minggu'})`;
        return (
            <div className="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[#334155]">{h.tgl}</p>
                    <span className="text-xs text-[#64748B]">{label}{h.holiday ? ` · ${h.holiday.nama}` : ''}</span>
                </div>
            </div>
        );
    }
    // Cuti
    if (h.is_cuti && h.cuti) {
        return (
            <div className="rounded-lg border border-[#FCD34D] bg-[#FEF3C7]/50 p-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[#92400E]">{h.tgl}</p>
                    <span className="text-xs font-medium text-[#92400E]">Cuti {h.cuti.jenis}</span>
                </div>
                <p className="text-xs text-[#78350F] mt-1">{h.cuti.alasan}</p>
            </div>
        );
    }
    // Dinas
    if (h.is_dinas && h.dinas) {
        return (
            <div className="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF]/60 p-3 space-y-1">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[#1E3A8A]">{h.tgl}</p>
                    <span className="text-xs font-medium text-[#1E3A8A]">Dinas</span>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-xs text-[#334155] mt-2">
                    <p><strong>Nomor Surat:</strong> {h.dinas.nomor_surat}</p>
                    <p><strong>Tanggal Dinas:</strong> {h.dinas.tanggal_dinas}</p>
                    <p><strong>Tanggal Pengajuan:</strong> {h.dinas.tanggal_pengajuan}</p>
                    <p><strong>Tujuan:</strong> {h.dinas.tujuan}</p>
                    {h.dinas.transportasi && <p><strong>Transportasi:</strong> {h.dinas.transportasi}</p>}
                    {h.dinas.pembebanan_anggaran && <p><strong>Pembebanan Anggaran:</strong> {h.dinas.pembebanan_anggaran}</p>}
                    <p className="md:col-span-2"><strong>Keterangan:</strong> {h.dinas.keterangan}</p>
                </div>
            </div>
        );
    }
    // Tanpa keterangan
    if (h.is_tanpa_keterangan) {
        return (
            <div className="rounded-lg border border-[#FCA5A5] bg-[#FEE2E2]/40 p-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[#991B1B]">{h.tgl}</p>
                    <span className="text-xs font-medium text-[#991B1B]">Tanpa keterangan</span>
                </div>
                <p className="text-xs text-[#7F1D1D] mt-1">Tidak ada absen masuk · Tidak ada absen pulang</p>
            </div>
        );
    }
    // Hadir dengan detail
    if (!detail) {
        return (
            <div className="rounded-lg border border-[#E2E8F0] bg-white p-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-[#334155]">{h.tgl}</p>
                    <span className="text-xs text-[#64748B]">{h.catatan}</span>
                </div>
                <p className="text-xs text-[#475569] mt-1">Masuk: {h.jam_masuk} · Pulang: {h.jam_pulang}</p>
            </div>
        );
    }
    return (
        <div className="rounded-lg border border-[#86EFAC] bg-white p-3 space-y-2">
            <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-[#065F46]">{detail.tgl}</p>
                <span className="text-xs font-medium text-[#065F46]">{detail.status}</span>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                {detail.masuk && (
                    <div className="rounded border border-[#E2E8F0] p-2 bg-[#F8FAFC]">
                        <p className="font-semibold text-[#0F172A] mb-1">Absen Masuk</p>
                        <p><strong>Jenis:</strong> {detail.masuk.jenis_absen}</p>
                        <p><strong>Jam:</strong> <span className="font-mono">{detail.masuk.jam}</span></p>
                        <p><strong>Timezone:</strong> {detail.masuk.timezone}</p>
                        <p><strong>Koordinat:</strong> <span className="font-mono">{detail.masuk.koordinat}</span></p>
                        <p><strong>Lokasi:</strong> {detail.masuk.lokasi}</p>
                        <p className="text-[#64748B] mt-1"><strong>Radius:</strong> {detail.masuk.radius} m dari titik</p>
                        <p className="text-[#64748B]"><strong>Unit Kerja:</strong> {detail.masuk.unit_kerja}</p>
                    </div>
                )}
                {detail.pulang && (
                    <div className="rounded border border-[#E2E8F0] p-2 bg-[#F8FAFC]">
                        <p className="font-semibold text-[#0F172A] mb-1">Absen Pulang</p>
                        <p><strong>Jenis:</strong> {detail.pulang.jenis_absen}</p>
                        <p><strong>Jam:</strong> <span className="font-mono">{detail.pulang.jam}</span></p>
                        <p><strong>Timezone:</strong> {detail.pulang.timezone}</p>
                        <p><strong>Koordinat:</strong> <span className="font-mono">{detail.pulang.koordinat}</span></p>
                        <p><strong>Lokasi:</strong> {detail.pulang.lokasi}</p>
                        <p className="text-[#64748B] mt-1"><strong>Radius:</strong> {detail.pulang.radius} m dari titik</p>
                        <p className="text-[#64748B]"><strong>Unit Kerja:</strong> {detail.pulang.unit_kerja}</p>
                    </div>
                )}
            </div>
        </div>
    );
}
