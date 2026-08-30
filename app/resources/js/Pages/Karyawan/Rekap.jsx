import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadCuti, loadAttendances, loadSettings, loadLove } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

export default function Rekap() {
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [cutiList, setCutiList] = useState(() => loadCuti());
    const [attendances, setAttendances] = useState(() => loadAttendances());
    const [settings, setSettings] = useState(() => loadSettings());
    const [love, setLove] = useState(() => loadLove());
    useEffect(() => {
        const sync = () => {
            setRegionsData(loadRegions()); setEmployees(loadEmployees()); setCutiList(loadCuti()); setAttendances(loadAttendances()); setSettings(loadSettings()); setLove(loadLove());
        };
        sync();
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = () => sync();
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
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

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const monthName = now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDow = new Date(year, month, 1).getDay();

    const myCuti = useMemo(() => cutiList.filter((c) => c.employee_id === MOCK_KARYAWAN_ID && c.status !== 'Ditolak'), [cutiList]);
    const myAttendDays = useMemo(() => new Set(attendances.filter((a)=>a.employee_id===MOCK_KARYAWAN_ID).map((a)=>a.tgl)), [attendances]);
    const hadir = myAttendDays.size || Math.min(daysInMonth - 10, 16);
    const terlambat = attendances.filter((a)=>a.employee_id===MOCK_KARYAWAN_ID && a.status==='late').length || 2;
    const cutiDays = myCuti.length || 1;
    const loveMax = settings.loveMax ?? 4;
    const loveUsed = love.filter((c)=>c.employee_id===MOCK_KARYAWAN_ID && (c.status==='approved' || c.status==='pending')).length;
    const sisa = Math.max(0, loveMax - loveUsed);

    const today = now.getDate();
    const cutiDaySet = new Set(myCuti.flatMap((c)=>{
        // parse mulai/selesai ISO if available, else fallback day 8
        if (c.mulai && c.selesai) {
            const s = new Date(c.mulai).getDate();
            const e = new Date(c.selesai).getDate();
            const m = new Date(c.mulai).getMonth();
            if (m===month) {
                const arr=[]; for(let d=s; d<=Math.min(e, daysInMonth); d++) arr.push(d); return arr;
            }
        }
        return [8];
    }));
    const terlambatSet = new Set([3, 12].filter(()=> terlambat>0));

    const days = Array.from({ length: daysInMonth }, (_, i) => {
        const d = i + 1;
        const dow = new Date(year, month, d).getDay();
        const isWeekend = dow === 0 || dow === 6;
        if (isWeekend) return { d, status: 'libur' };
        if (d > today) return { d, status: 'future' };
        if (cutiDaySet.has(d)) return { d, status: 'cuti' };
        if (terlambatSet.has(d)) return { d, status: 'terlambat' };
        return { d, status: 'hadir' };
    });

    const offset = firstDow === 0 ? 6 : firstDow - 1;
    const workDays = days.filter((x)=>x.status!=='libur' && x.status!=='future').length || 1;
    const pct = Math.min(99, Math.max(60, Math.round((hadir / workDays) * 100)));

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Rekap kehadiran</h2>
                        <p className="text-sm text-[#64748B] capitalize">{monthName} • {assigned.region.name} • {assigned.site.nama_lokasi} • {assigned.site.radius} m • Love {sisa}/{loveMax} • live LS</p>
                    </div>
                    <span className="bg-[#FCB833] text-[#0F172A] text-xs font-semibold px-3 py-1.5 rounded-full">{loveMax} Love</span>
                </div>
                <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-3 flex items-center justify-between">
                    <span className="text-xs font-medium text-[#1E3A8A]">{assigned.site.nama_lokasi} • {assigned.site.lat.toFixed(4)}, {assigned.site.lng.toFixed(4)}</span>
                    <span className="text-xs text-[#64748B]">{assigned.site.radius} m • {settings.jamMasuk}–{settings.jamPulang}</span>
                </div>
                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex items-center justify-between">
                    <div className="flex gap-1.5">
                        {Array.from({length: loveMax}, (_,i)=>(<span key={i} className={`w-8 h-2 rounded-full ${i < sisa ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
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
                        <h3 className="font-medium text-sm text-[#0F172A] capitalize">{monthName} • live LS</h3>
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
                        {Array.from({ length: offset }).map((_, i) => <span key={`off-${i}-${month}`} className="py-2"></span>)}
                        {days.map((d) => (
                            <span key={d.d} className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-medium mx-auto
                                ${d.status === 'hadir' ? 'bg-[#FCB833] text-[#0F172A]' : d.status === 'terlambat' ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' : d.status === 'cuti' ? 'bg-[#ECFDF5] text-[#065F46]' : d.status === 'future' ? 'text-[#CBD5E1]' : 'bg-[#F8FAFC] text-[#94A3B8]'}`}>{d.d}</span>
                        ))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-4">Jam global {settings.jamMasuk}–{settings.jamPulang} WITA • Toleransi {settings.toleransi}m • Di luar {assigned.site.radius} m titik {assigned.site.nama_lokasi} tidak tercatat (422) • live LS</p>
                </div>

                <div className="flex gap-2">
                    <Link href="/karyawan/absensi" className="flex-1 bg-white rounded-xl py-3 text-sm font-medium text-[#334155] text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">Lihat absensi</Link>
                    <button type="button" onClick={() => alert('Unduh rekap PDF — frontend only (akan generate S3 /rekap/... saat backend ready)')} className="flex-1 bg-[#FCB833] text-[#0F172A] rounded-xl py-3 text-sm font-semibold">Unduh rekap PDF</button>
                </div>
            </div>
        </KaryawanLayout>
    );
}
