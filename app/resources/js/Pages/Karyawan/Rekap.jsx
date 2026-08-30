import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { Link } from '@inertiajs/react';

export default function Rekap() {
    const days = Array.from({ length: 30 }, (_, i) => {
        const d = i + 1;
        if ([6, 7, 13, 14, 20, 21, 27, 28].includes(d)) return { d, status: 'libur' };
        if ([3, 12].includes(d)) return { d, status: 'terlambat' };
        if ([8].includes(d)) return { d, status: 'cuti' };
        if (d > 24) return { d, status: 'future' };
        return { d, status: 'hadir' };
    });
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Rekap kehadiran</h2>
                        <p className="text-sm text-[#64748B]">Agustus 2026 • Kantor Wilayah Gowa • Love 3/4</p>
                    </div>
                    <span className="bg-[#FCB833] text-[#0F172A] text-xs font-semibold px-3 py-1.5 rounded-full">4 Love</span>
                </div>
                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex items-center justify-between">
                    <div className="flex gap-1.5">
                        {[1,2,3,4].map((i) => (<span key={i} className={`w-8 h-2 rounded-full ${i <= 3 ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                    </div>
                    <span className="text-xs font-medium text-[#92400E] bg-[#FFF7E6] px-2.5 py-1 rounded-full border border-[#FCB833]/20">Sisa 3 • Reset 1 Sep</span>
                </div>

                {/* Summary — no outline */}
                <div className="grid grid-cols-4 gap-2">
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">18</p>
                        <p className="text-xs text-[#64748B]">Hadir</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span>
                    </div>
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">2</p>
                        <p className="text-xs text-[#64748B]">Terlambat</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#F59E0B]"></span>
                    </div>
                    <div className="bg-white rounded-2xl p-3 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <p className="text-lg font-semibold text-[#0F172A]">1</p>
                        <p className="text-xs text-[#64748B]">Cuti</p>
                        <span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#0D9488]"></span>
                    </div>
                    <div className="bg-[#0F172A] rounded-2xl p-3 text-center">
                        <p className="text-lg font-semibold text-white">90%</p>
                        <p className="text-xs text-white/60">Kehadiran</p>
                    </div>
                </div>

                {/* Calendar — no outline card */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-medium text-sm text-[#0F172A]">Agustus 2026</h3>
                        <div className="flex items-center gap-2 text-xs">
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#FCB833]"></span> Hadir</span>
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#F59E0B]"></span> Terlambat</span>
                            <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-[#E2E8F0]"></span> Libur</span>
                        </div>
                    </div>
                    <div className="grid grid-cols-7 gap-1.5 text-center">
                        {['Sn','Sl','Rb','Km','Jm','Sb','Mg'].map((h) => (
                            <span key={h} className="text-xs font-medium text-[#94A3B8] py-1">{h}</span>
                        ))}
                        {/* offset for Aug 1 = Sat */}
                        <span className="py-2"></span><span className="py-2"></span><span className="py-2"></span><span className="py-2"></span><span className="py-2"></span>
                        {days.map((d) => (
                            <span key={d.d} className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-medium mx-auto
                                ${d.status === 'hadir' ? 'bg-[#FCB833] text-[#0F172A]' : d.status === 'terlambat' ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' : d.status === 'cuti' ? 'bg-[#ECFDF5] text-[#065F46]' : d.status === 'future' ? 'text-[#CBD5E1]' : 'bg-[#F8FAFC] text-[#94A3B8]'}`}>{d.d}</span>
                        ))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-4">Jam global 07:30–16:00 WITA • Tepat waktu ≤07:45 • Di luar radius tidak tercatat</p>
                </div>

                <div className="flex gap-2">
                    <Link href="/karyawan/absensi" className="flex-1 bg-white rounded-xl py-3 text-sm font-medium text-[#334155] text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">Lihat absensi</Link>
                    <button type="button" onClick={() => alert('Unduh rekap PDF — frontend only (akan generate S3 /rekap/... saat backend ready)')} className="flex-1 bg-[#FCB833] text-[#0F172A] rounded-xl py-3 text-sm font-semibold">Unduh rekap PDF</button>
                </div>
            </div>
        </KaryawanLayout>
    );
}
