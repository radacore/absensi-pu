import KaryawanLayout from '@/Layouts/KaryawanLayout';
import RekapActions from '@/Components/RekapActions';
import { router, usePage } from '@inertiajs/react';

const namaBulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

export default function Rekap() {
    const { url, props } = usePage();
    const assigned = props.assigned ?? null;
    const settings = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15, loveMax: 4, loveQuota: null };
    const monthRows = props.monthRows ?? [];
    const jadwalBulan = props.jadwalBulan ?? [];
    const isShift = !!props.isShift;
    const cutiDates = props.cutiDates ?? [];
    const cutiDays = props.cutiDays ?? 0;
    const hadir = props.hadir ?? 0;
    const terlambat = props.terlambat ?? 0;

    // Bulan aktif dikirim server (bawaan: bulan berjalan) supaya bulan yang
    // sedang dilihat tidak bergantung pada jam perangkat karyawan.
    const bulanAktif = props.bulan ?? new Date().toISOString().slice(0, 7);
    const [tahunAktif, bulanKeAktif] = bulanAktif.split('-').map((v) => parseInt(v, 10));
    const year = tahunAktif;
    const month = bulanKeAktif - 1;
    const monthName = `${namaBulan[month]} ${year}`;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDow = new Date(year, month, 1).getDay();
    const isBulanBerjalan = bulanAktif === new Date().toISOString().slice(0, 7);
    const today = isBulanBerjalan ? new Date().getDate() : daysInMonth;

    // Daftar bulan untuk pemilih: 24 bulan terakhir.
    const bulanOptions = [];
    const sekarang = new Date();
    for (let i = 0; i < 24; i++) {
        const dt = new Date(sekarang.getFullYear(), sekarang.getMonth() - i, 1);
        bulanOptions.push(`${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`);
    }

    const gantiBulan = (bulan) => {
        router.get('/karyawan/rekap', { bulan }, { preserveState: false, preserveScroll: true });
    };

    const loveMax = settings.loveMax ?? 4;
    const sisa = settings.loveQuota?.sisa ?? loveMax;

    const statusByDay = new Map();
    monthRows.forEach((r) => {
        const d = new Date(r.tgl + 'T00:00:00').getDate();
        if (new Date(r.tgl + 'T00:00:00').getMonth() === month) statusByDay.set(d, { status: r.status, shift: r.shift });
    });
    const cutiSet = new Set(cutiDates.map((s) => new Date(s + 'T00:00:00').getDate()));

    // Pekerja shift: acuan hari kerja bukan akhir pekan, tapi jadwal giliran.
    const jadwalByDay = new Map();
    jadwalBulan.forEach((j) => {
        const d = new Date(j.tgl + 'T00:00:00').getDate();
        if (new Date(j.tgl + 'T00:00:00').getMonth() === month) jadwalByDay.set(d, j);
    });

    const days = Array.from({ length: daysInMonth }, (_, i) => {
        const d = i + 1;
        const dow = new Date(year, month, d).getDay();
        const isWeekend = dow === 0 || dow === 6;
        const j = jadwalByDay.get(d) ?? null;
        if (cutiSet.has(d)) return { d, status: 'cuti', shift: j?.shift ?? null };
        if (statusByDay.has(d)) {
            const s = statusByDay.get(d);
            return { d, status: s.status === 'late' ? 'terlambat' : 'hadir', shift: s.shift ?? j?.shift ?? null };
        }
        if (isShift) {
            if (j && j.shift === 'Libur') return { d, status: 'libur', shift: 'Libur' };
            if (j) return { d, status: d > today ? 'rencana' : 'kosong', shift: j.shift, jamMulai: j.jamMulai };
            return { d, status: d > today ? 'future' : 'kosong', shift: null };
        }
        if (isWeekend) return { d, status: 'libur', shift: null };
        if (d > today) return { d, status: 'future', shift: null };
        return { d, status: 'kosong', shift: null };
    });

    const offset = firstDow === 0 ? 6 : firstDow - 1;
    const workDays = (isShift
        ? days.filter((x) => x.shift && x.shift !== 'Libur').length
        : days.filter((x) => x.status !== 'libur' && x.status !== 'future').length) || 1;
    const pct = Math.min(99, Math.round((hadir / workDays) * 100));

    const pdfHref = `/karyawan/rekap/pdf?bulan=${bulanAktif}`;
    const cetakNamaBerkas = `Rekap presensi - ${props.me?.nama ?? ''} - ${monthName}`;

    if (!assigned) {
        return (
            <KaryawanLayout>
                <div className="bg-white rounded-2xl p-6 text-center">
                    <p className="font-medium text-[#0F172A]">Titik belum di-assign</p>
                    <p className="text-sm text-[#64748B] mt-1">Rekap menunggu penugasan titik.</p>
                </div>
            </KaryawanLayout>
        );
    }

    return (
        <KaryawanLayout>
            <div className="space-y-5 rekap-karyawan">
                <div className="flex items-start justify-between print-hide">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Rekap kehadiran</h2>
                        <p className="text-sm text-[#64748B] capitalize">{monthName} • {assigned.regionName} • {assigned.nama_lokasi} (titik utama) • {assigned.radius} m • Toleransi {sisa}/{loveMax}</p>
                    </div>
                    <span className="bg-[#FCB833] text-[#0F172A] text-xs font-semibold px-3 py-1.5 rounded-full">{loveMax} Toleransi</span>
                </div>

                {/* Pemilih bulan + ekspor (tidak ikut tercetak) */}
                <div className="bg-white rounded-2xl p-3 shadow-[0_2px_16px_rgba(15,23,42,0.04)] print-hide">
                    <label className="block text-xs font-medium text-[#334155] mb-2">
                        <span className="block mb-1 text-[#64748B]">Periode</span>
                        <select
                            value={bulanAktif}
                            onChange={(e) => gantiBulan(e.target.value)}
                            className="w-full rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none min-h-[44px]"
                        >
                            {bulanOptions.map((b) => {
                                const [y, m] = b.split('-').map((v) => parseInt(v, 10));
                                return <option key={b} value={b}>{namaBulan[m - 1]} {y}</option>;
                            })}
                        </select>
                    </label>
                    <RekapActions pdfHref={pdfHref} namaBerkas={cetakNamaBerkas} besar />
                </div>

                <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-3 flex items-center justify-between">
                    <span className="text-xs font-medium text-[#1E3A8A]">Titik utama: {assigned.nama_lokasi} • {assigned.lat.toFixed(4)}, {assigned.lng.toFixed(4)}</span>
                    <span className="text-xs text-[#64748B]">{assigned.radius} m • {isShift ? 'jadwal shift' : `${settings.jamMasuk}–${settings.jamPulang}`}</span>
                </div>
                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex items-center justify-between">
                    <div className="flex gap-1.5">
                        {Array.from({ length: loveMax }, (_, i) => (<span key={`love-${i}`} className={`w-8 h-2 rounded-full ${i < sisa ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                    </div>
                    <span className="text-xs font-medium text-[#92400E] bg-[#FFF7E6] px-2.5 py-1 rounded-full border border-[#FCB833]/20">Sisa {sisa} • Reset 1 {new Date(year, month + 1, 1).toLocaleDateString('id-ID', { month: 'short' })}</span>
                </div>

                <div className="grid grid-cols-4 gap-2">
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">{hadir}</p>
                        <p className="text-xs text-[#64748B]">Hadir</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span>
                    </div>
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">{terlambat}</p>
                        <p className="text-xs text-[#64748B]">Terlambat</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#F59E0B]"></span>
                    </div>
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">{cutiDays}</p>
                        <p className="text-xs text-[#64748B]">Cuti</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#0D9488]"></span>
                    </div>
                    <div className="bg-[#0F172A] rounded-2xl p-3 text-center">
                        <p className="text-lg font-semibold text-white">{pct}%</p>
                        <p className="text-xs text-white/60">Kehadiran</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-medium text-sm text-[#0F172A] capitalize">{monthName}</h3>
                        <div className="flex items-center gap-2 text-xs">
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#FCB833]"></span> Hadir</span>
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#F59E0B]"></span> Terlambat</span>
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#0D9488]"></span> Cuti</span>
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#E2E8F0]"></span> Libur</span>
                            {isShift && <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#1E3A8A]"></span> Rencana shift</span>}
                        </div>
                    </div>
                    <div className="grid grid-cols-7 gap-1.5 text-center">
                        {['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'].map((h) => (
                            <span key={h} className="text-xs font-medium text-[#64748B] py-1">{h}</span>
                        ))}
                        {Array.from({ length: offset }).map((_, i) => <span key={`off-${i}`} className="py-2"></span>)}
                        {days.map((d) => {
                            const kotak = d.status === 'hadir' ? 'bg-[#FCB833] text-[#0F172A]'
                                : d.status === 'terlambat' ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]'
                                    : d.status === 'cuti' ? 'bg-[#ECFDF5] text-[#065F46]'
                                        : d.status === 'future' ? 'text-[#CBD5E1]'
                                            : d.status === 'rencana' ? 'bg-[#EFF6FF] text-[#1E3A8A] border border-[#DBEAFE]'
                                                : 'bg-[#F8FAFC] text-[#64748B]';
                            return (
                                <span key={d.d} className={`w-9 h-11 rounded-lg flex flex-col items-center justify-center text-xs font-medium mx-auto ${kotak}`}>
                                    <span>{d.d}</span>
                                    {isShift && d.shift && (
                                        <span className="text-[9px] font-normal leading-tight">{d.shift === 'Libur' ? 'L' : d.shift.slice(0, 4)}</span>
                                    )}
                                </span>
                            );
                        })}
                    </div>
                    <p className="text-xs text-[#64748B] mt-4">
                        {isShift
                            ? `Jam mengikuti jadwal shift • Kelonggaran ${settings.toleransi}m • Absen tercatat di titik mana pun yang ditugaskan, selama masih dalam radiusnya`
                            : `Jam ${settings.jamMasuk}–${settings.jamPulang} WITA • Kelonggaran ${settings.toleransi}m • Absen tercatat di titik mana pun yang ditugaskan, selama masih dalam radiusnya`}
                    </p>
                    {isShift && (
                        <p className="text-xs text-[#64748B] mt-1">
                            Jadwal shift bulan ini disusun Admin Wilayah. Belum ada jadwal untuk hari yang kosong — hubungi Admin Wilayah bila ada yang belum sesuai.
                        </p>
                    )}
                    <p className="text-xs text-[#64748B] mt-1">
                        Butuh rincian resmi (keterlambatan, dinas, cuti, tanda tangan)? Unduh berkas PDF lewat tombol di atas.
                    </p>
                </div>
            </div>
        </KaryawanLayout>
    );
}
