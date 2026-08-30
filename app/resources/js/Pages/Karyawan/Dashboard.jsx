import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { Link } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { loadRegions, loadEmployees, loadAttendances, loadCuti, loadLove, loadSettings } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

export default function Dashboard() {
    const [now, setNow] = useState(new Date());
    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [attendances, setAttendances] = useState(() => loadAttendances());
    const [cuti, setCuti] = useState(() => loadCuti());
    const [love, setLove] = useState(() => loadLove());
    const [settings, setSettings] = useState(() => loadSettings());
    const [photo, setPhoto] = useState(null);
    useEffect(() => {
        const sync = () => {
            setRegionsData(loadRegions()); setEmployees(loadEmployees()); setAttendances(loadAttendances()); setCuti(loadCuti()); setLove(loadLove()); setSettings(loadSettings());
            try { const p = localStorage.getItem('bbws_mock_photo_v3'); if (p) setPhoto(p); } catch {}
        };
        sync();
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);
    const me = useMemo(() => employees.find((e) => e.id === MOCK_KARYAWAN_ID) || employees[0], [employees]);
    const assigned = useMemo(() => {
        if (!me || me.office_location_id == null) return null;
        for (const r of regionsData) {
            const s = r.locations.find((x) => x.id === Number(me.office_location_id));
            if (s) return { site: s, region: r };
        }
        return null;
    }, [me, regionsData]);
    const hour = now.getHours();
    const greeting = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 19 ? 'Selamat sore' : 'Selamat malam';
    const dateStr = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    const myAttend = useMemo(() => attendances.filter((a) => a.employee_id === MOCK_KARYAWAN_ID), [attendances]);
    const myCuti = useMemo(() => cuti.filter((c) => c.employee_id === MOCK_KARYAWAN_ID), [cuti]);
    const myLove = useMemo(() => love.filter((c) => c.employee_id === MOCK_KARYAWAN_ID), [love]);
    const hadir = myAttend.filter((a)=>a.status==='on_time' || a.status==='late' || a.status==='excused_love').length || 18;
    const loveMax = settings.loveMax ?? 4;
    const loveUsed = myLove.filter((c)=>c.status==='approved' || c.status==='pending').length;
    const sisaLove = Math.max(0, loveMax - loveUsed);
    const unread = (() => {
        try {
            const raw = localStorage.getItem('bbws_mock_pengumuman_read_v3'); const readSet = raw ? new Set(JSON.parse(raw)) : new Set();
            const peng = (()=>{ try{ const r=localStorage.getItem('bbws_mock_pengumuman_v3'); return r?JSON.parse(r):[];}catch{return [];} })();
            const regionName = me?.region || 'Kab. Gowa';
            return peng.filter((p)=> (p.wilayah==='Global' || p.wilayah===regionName) && !readSet.has(p.id)).length;
        } catch { return 0; }
    })();

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] relative pt-10 pb-5 px-5 mt-8">
                    <div className="absolute -top-8 left-1/2 -translate-x-1/2 w-16 h-16 rounded-full border-[3px] border-white shadow-[0_4px_16px_rgba(15,23,42,0.12)] overflow-hidden bg-[#F1F5F9]">
                        <img src={photo || me?.foto || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format'} alt="Foto profil" className="w-full h-full object-cover" />
                    </div>
                    <h2 className="font-semibold text-[18px] tracking-tight text-[#0F172A] mt-1 text-center">{greeting}, {me?.nama || 'Andi Saputra'}</h2>
                    <p className="text-sm text-[#64748B] text-center mt-1">{dateStr} • {timeStr} WITA</p>
                    {assigned ? (
                        <p className="text-xs text-center mt-2 bg-[#EFF6FF] text-[#1E3A8A] px-2.5 py-1 rounded-full inline-flex mx-auto">{assigned.site.nama_lokasi} • {assigned.site.radius} m • {assigned.region.name} • {settings.jamMasuk}–{settings.jamPulang} WITA</p>
                    ) : (
                        <p className="text-xs text-center mt-2 bg-[#FEF2F2] text-[#991B1B] px-2.5 py-1 rounded-full inline-flex mx-auto">Tanpa titik — tidak bisa absen (hubungi Admin {me?.region})</p>
                    )}
                    <Link href="/karyawan/love" className="mt-4 flex items-center justify-center gap-2">
                        {Array.from({length: loveMax}, (_,i) => (
                            <span key={i} className={`w-8 h-8 rounded-xl flex items-center justify-center ${i < sisaLove ? 'bg-[#FFF7E6] border border-[#FCB833]/20' : 'bg-[#F1F5F9]'}`}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill={i < sisaLove ? '#FCB833' : 'none'} stroke={i < sisaLove ? '#FCB833' : '#CBD5E1'} strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
                            </span>
                        ))}
                    </Link>
                    <p className="text-xs text-center text-[#64748B] mt-1">Sisa {sisaLove}/{loveMax} Love • live LS</p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Link href="/karyawan/absensi" className="group bg-[#0F172A] text-white rounded-2xl p-5 flex flex-col justify-between min-h-[110px] hover:bg-[#1E3A8A] transition">
                        <span className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm leading-tight">Absensi</p>
                            <p className="text-xs text-white/60 leading-tight">{assigned ? `${assigned.site.nama_lokasi} • ${assigned.site.radius}m` : 'Tanpa titik'}</p>
                        </div>
                    </Link>
                    <Link href="/karyawan/cuti" className="bg-white rounded-2xl p-5 flex flex-col justify-between min-h-[110px] shadow-[0_2px_16px_rgba(15,23,42,0.04)] hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition">
                        <span className="w-8 h-8 rounded-lg bg-[#F1F5F9] flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <div>
                            <p className="font-semibold text-sm text-[#0F172A] leading-tight">Cuti</p>
                            <p className="text-xs text-[#64748B] leading-tight">{myCuti.length ? `${myCuti.length} pengajuan • live` : 'Ajukan & lacak'}</p>
                        </div>
                    </Link>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Link href="/karyawan/rekap" className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition">
                        <p className="text-xl font-semibold tracking-tight text-[#0F172A]">{hadir}</p>
                        <p className="text-xs text-[#64748B] mt-0.5">Hadir (live LS)</p>
                        <span className="mt-1.5 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span>
                    </Link>
                    <Link href="/karyawan/pengumuman" className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center">
                        <p className="text-xl font-semibold tracking-tight text-[#0F172A]">{unread}</p>
                        <p className="text-xs text-[#64748B] mt-0.5">Info baru (live)</p>
                    </Link>
                </div>
                <Link href="/karyawan/rekap" className="bg-[#FFF7E6] rounded-2xl p-4 flex items-center gap-3 hover:bg-[#FFEDCC] transition flex">
                    <span className="w-9 h-9 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.6"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                    </span>
                    <div>
                        <p className="font-semibold text-sm text-[#0F172A] leading-tight">Rekap bulanan</p>
                        <p className="text-xs text-[#92400E]">Kalender • {hadir} hadir {assigned ? `• ${assigned.site.nama_lokasi}` : ''} • live LS</p>
                    </div>
                </Link>

            </div>
        </KaryawanLayout>
    );
}
