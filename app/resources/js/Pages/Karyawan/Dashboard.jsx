import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function Dashboard() {
    const [now, setNow] = useState(new Date());
    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);
    const hour = now.getHours();
    const greeting = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 19 ? 'Selamat sore' : 'Selamat malam';
    const dateStr = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                {/* Greeting — with 4 Love + photo overlap */}
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] relative pt-10 pb-5 px-5 mt-8">
                    <div className="absolute -top-8 left-1/2 -translate-x-1/2 w-16 h-16 rounded-full border-[3px] border-white shadow-[0_4px_16px_rgba(15,23,42,0.12)] overflow-hidden bg-[#F1F5F9]">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format" alt="Foto profil Andi Saputra" className="w-full h-full object-cover" />
                    </div>
                    <h2 className="font-semibold text-[18px] tracking-tight text-[#0F172A] mt-1 text-center">{greeting}, Andi Saputra</h2>
                    <p className="text-sm text-[#64748B] text-center mt-1">{dateStr} • {timeStr} WITA</p>
                    <Link href="/karyawan/love" className="mt-4 flex items-center justify-center gap-2">
                        {[1,2,3,4].map((i) => (
                            <span key={i} className={`w-8 h-8 rounded-xl flex items-center justify-center ${i <= 3 ? 'bg-[#FFF7E6] border border-[#FCB833]/20' : 'bg-[#F1F5F9]'}`}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill={i <= 3 ? '#FCB833' : 'none'} stroke={i <= 3 ? '#FCB833' : '#CBD5E1'} strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
                            </span>
                        ))}
                    </Link>
                </div>

                {/* Primary actions — no outline */}
                <div className="grid grid-cols-2 gap-3">
                    <Link href="/karyawan/absensi" className="group bg-[#0F172A] text-white rounded-2xl p-5 flex flex-col justify-between min-h-[110px] hover:bg-[#1E3A8A] transition">
                        <span className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm leading-tight">Absensi</p>
                            <p className="text-xs text-white/60 leading-tight">Masuk &amp; pulang</p>
                        </div>
                    </Link>
                    <Link href="/karyawan/cuti" className="bg-white rounded-2xl p-5 flex flex-col justify-between min-h-[110px] shadow-[0_2px_16px_rgba(15,23,42,0.04)] hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition">
                        <span className="w-8 h-8 rounded-lg bg-[#F1F5F9] flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm text-[#0F172A] leading-tight">Cuti</p>
                            <p className="text-xs text-[#64748B] leading-tight">Ajukan &amp; lacak</p>
                        </div>
                    </Link>
                </div>

                {/* Stats — with gold accent */}
                <div className="grid grid-cols-3 gap-3">
                    <Link href="/karyawan/rekap" className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition">
                        <p className="text-xl font-semibold tracking-tight text-[#0F172A]">18</p>
                        <p className="text-xs text-[#64748B] mt-0.5">Hadir</p>
                        <span className="mt-1.5 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span>
                    </Link>
                    <Link href="/karyawan/lembur" className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition">
                        <p className="text-xl font-semibold tracking-tight text-[#0F172A]">6,5</p>
                        <p className="text-xs text-[#64748B] mt-0.5">Jam lembur</p>
                        <span className="mt-1.5 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span>
                    </Link>
                    <Link href="/karyawan/pengumuman" className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center">
                        <p className="text-xl font-semibold tracking-tight text-[#0F172A]">3</p>
                        <p className="text-xs text-[#64748B] mt-0.5">Info baru</p>
                    </Link>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Link href="/karyawan/rekap" className="bg-[#FFF7E6] rounded-2xl p-4 flex items-center gap-3 hover:bg-[#FFEDCC] transition">
                        <span className="w-9 h-9 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.6"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm text-[#0F172A] leading-tight">Rekap bulanan</p>
                            <p className="text-xs text-[#92400E]">Kalender • 90% hadir</p>
                        </div>
                    </Link>
                    <Link href="/karyawan/lembur" className="bg-white rounded-2xl p-4 flex items-center gap-3 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <span className="w-9 h-9 rounded-xl bg-[#F1F5F9] flex items-center justify-center shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm text-[#0F172A] leading-tight">Lembur</p>
                            <p className="text-xs text-[#64748B]">6,5 jam • Dinas luar</p>
                        </div>
                    </Link>
                </div>


            </div>
        </KaryawanLayout>
    );
}
