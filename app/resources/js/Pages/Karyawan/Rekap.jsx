import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { Link, usePage } from '@inertiajs/react';

export default function Rekap() {
    const { props } = usePage();
    const assigned = props.assigned ?? null;
    const settings = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15, loveMax: 4, loveQuota: null };
    const monthRows = props.monthRows ?? [];
    const cutiDates = props.cutiDates ?? [];
    const cutiDays = props.cutiDays ?? 0;
    const hadir = props.hadir ?? 0;
    const terlambat = props.terlambat ?? 0;

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const monthName = now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDow = new Date(year, month, 1).getDay();
    const today = now.getDate();

    const loveMax = settings.loveMax ?? 4;
    const sisa = settings.loveQuota?.sisa ?? loveMax;

    const statusByDay = new Map();
    monthRows.forEach((r) => {
        const d = new Date(r.tgl + 'T00:00:00').getDate();
        if (new Date(r.tgl).getMonth() === month) statusByDay.set(d, r.status);
    });
    const cutiSet = new Set(cutiDates.map((s) => new Date(s + 'T00:00:00').getDate()));

    const days = Array.from({ length: daysInMonth }, (_, i) => {
        const d = i + 1;
        const dow = new Date(year, month, d).getDay();
        const isWeekend = dow === 0 || dow === 6;
        if (isWeekend) return { d, status: 'libur' };
        if (cutiSet.has(d)) return { d, status: 'cuti' };
        if (statusByDay.has(d)) {
            const s = statusByDay.get(d);
            if (s === 'late') return { d, status: 'terlambat' };
            return { d, status: 'hadir' };
        }
        if (d > today) return { d, status: 'future' };
        return { d, status: 'kosong' };
    });

    const offset = firstDow === 0 ? 6 : firstDow - 1;
    const workDays = days.filter((x) => x.status !== 'libur' && x.status !== 'future').length || 1;
    const pct = Math.min(99, Math.max(60, Math.round((hadir / workDays) * 100)));

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
            <div className="space-y-5">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Rekap kehadiran</h2>
                        <p className="text-sm text-[#64748B] capitalize">{monthName} • {assigned.regionName} • {assigned.nama_lokasi} • {assigned.radius} m • Toleransi {sisa}/{loveMax}</p>
                    </div>
                    <span className="bg-[#FCB833] text-[#0F172A] text-xs font-semibold px-3 py-1.5 rounded-full">{loveMax} Toleransi</span>
                </div>
                <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-3 flex items-center justify-between">
                    <span className="text-xs font-medium text-[#1E3A8A]">{assigned.nama_lokasi} • {assigned.lat.toFixed(4)}, {assigned.lng.toFixed(4)}</span>
                    <span className="text-xs text-[#64748B]">{assigned.radius} m • {settings.jamMasuk}–{settings.jamPulang}</span>
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
                        </div>
                    </div>
                    <div className="grid grid-cols-7 gap-1.5 text-center">
                        {['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'].map((h) => (
                            <span key={h} className="text-xs font-medium text-[#94A3B8] py-1">{h}</span>
                        ))}
                        {Array.from({ length: offset }).map((_, i) => <span key={`off-${i}`} className="py-2"></span>)}
                        {days.map((d) => (
                            <span key={d.d} className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-medium mx-auto
                                ${d.status === 'hadir' ? 'bg-[#FCB833] text-[#0F172A]' : d.status === 'terlambat' ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' : d.status === 'cuti' ? 'bg-[#ECFDF5] text-[#065F46]' : d.status === 'future' ? 'text-[#CBD5E1]' : 'bg-[#F8FAFC] text-[#94A3B8]'}`}>{d.d}</span>
                        ))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-4">Jam {settings.jamMasuk}–{settings.jamPulang} WITA • Kelonggaran {settings.toleransi}m • Di luar {assigned.radius} m titik {assigned.nama_lokasi} tidak tercatat</p>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <Link href="/karyawan/absensi" className="bg-white rounded-xl py-3 text-sm font-medium text-[#334155] text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">Lihat absensi</Link>
                    <Link href="/karyawan/rekap/detail" className="bg-[#0F172A] rounded-xl py-3 text-sm font-semibold text-white text-center">Rekap Detail &amp; PDF</Link>
                </div>
            </div>
        </KaryawanLayout>
    );
}
