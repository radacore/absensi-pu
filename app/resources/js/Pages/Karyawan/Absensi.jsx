import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadAttendances, saveAttendances, loadSettings } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

export default function Absensi() {
    const [captured, setCaptured] = useState(false);
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [attendances, setAttendances] = useState(() => loadAttendances());
    const [settings, setSettings] = useState(() => loadSettings());
    const [photoPreview, setPhotoPreview] = useState(null);
    const [toast, setToast] = useState(null);
    useEffect(() => {
        const sync = () => {
            setRegionsData(loadRegions()); setEmployees(loadEmployees()); setAttendances(loadAttendances()); setSettings(loadSettings());
            try { const p = localStorage.getItem('bbws_mock_photo_v3'); if (p) setPhotoPreview(p); } catch {}
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

    const demoJarak = 42;
    const inRadius = assigned ? demoJarak <= assigned.site.radius : false;
    const tanpaTitik = !assigned;

    const myHistory = useMemo(() => attendances.filter((a) => a.employee_id === MOCK_KARYAWAN_ID).slice().sort((a,b)=> (b.tgl+b.datang).localeCompare(a.tgl+a.datang)), [attendances]);

    const nowStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const todayISO = new Date().toISOString().slice(0,10);
    const alreadyToday = myHistory.some((h) => h.tgl === todayISO && h.datang);

    const handleKirim = () => {
        if (tanpaTitik) { setToast('Tanpa titik — tidak bisa absen (422)'); setTimeout(()=>setToast(null),2200); return; }
        if (!inRadius) { setToast(`${demoJarak} m / ${assigned.site.radius} m — di luar radius`); setTimeout(()=>setToast(null),2200); return; }
        if (alreadyToday) { setToast('Sudah absen hari ini'); setTimeout(()=>setToast(null),2200); return; }
        const jamMasuk = settings.jamMasuk || '07:30';
        const tol = settings.toleransi ?? 15;
        const [h,m] = jamMasuk.split(':').map(Number);
        const cutoffMin = h*60+m+tol;
        const now = new Date();
        const curMin = now.getHours()*60+now.getMinutes();
        const isLate = curMin > cutoffMin;
        const datang = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const selfie = photoPreview || me?.foto || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=face&auto=format';
        const foto = selfie;
        const next = {
            id: Date.now(), employee_id: MOCK_KARYAWAN_ID, nama: me.nama, email: me.email, wilayah: me.region, kantor: assigned.region.kantor,
            office_location_id: assigned.site.id, tgl: todayISO, datang, pulang: '', status: isLate ? 'late' : 'on_time', love: null,
            jarak: demoJarak, lat: assigned.site.lat, lng: assigned.site.lng, foto, selfie,
        };
        const updated = [next, ...attendances];
        setAttendances(updated); saveAttendances(updated);
        setCaptured(false);
        setToast(isLate ? 'Absen tercatat — Terlambat (bisa pakai Love)' : 'Absen tercatat — Tepat waktu'); setTimeout(()=>setToast(null),2500);
    };

    const handlePulang = () => {
        const rec = myHistory.find((h)=> h.tgl===todayISO && !h.pulang);
        if (!rec) { setToast('Belum absen masuk hari ini'); setTimeout(()=>setToast(null),2200); return; }
        if (tanpaTitik || !inRadius) { setToast('Di luar radius — tidak bisa pulang'); setTimeout(()=>setToast(null),2200); return; }
        const pulang = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const updated = attendances.map((a)=> a.id===rec.id ? { ...a, pulang } : a);
        setAttendances(updated); saveAttendances(updated);
        setToast('Absen pulang tercatat'); setTimeout(()=>setToast(null),2200);
    };

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Absensi</h2>
                    {assigned ? (
                        <p className="text-sm text-[#64748B] mt-1">{assigned.region.name} • {assigned.site.nama_lokasi} • Radius {assigned.site.radius} m • 1 karyawan = 1 titik</p>
                    ) : (
                        <p className="text-sm font-medium text-[#991B1B] mt-1">Belum punya titik — hubungi Admin {me?.region || ''} untuk assign titik</p>
                    )}
                    <p className="text-xs text-[#94A3B8] mt-1">Valid hanya di titik assigned dalam radius titiknya — di luar / titik lain ditolak 422 • Jam {settings.jamMasuk}–{settings.jamPulang} WITA toleransi {settings.toleransi}m</p>
                </div>

                {tanpaTitik && (
                    <div className="bg-[#FEF2F2] border border-[#FECACA] rounded-2xl p-5">
                        <p className="text-sm font-semibold text-[#991B1B]">Tidak bisa absen</p>
                        <p className="text-xs text-[#991B1B]/80 mt-1">Akun {me?.nama} belum di-assign ke titik proyek (office_location_id null). Absen akan ditolak 422. Minta Admin {me?.region} assign di halaman titik.</p>
                    </div>
                )}

                {assigned && (
                    <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-4">
                        <p className="text-xs font-medium text-[#94A3B8]">Titik assigned kamu</p>
                        <p className="text-sm font-semibold text-[#0F172A] mt-1">{assigned.site.nama_lokasi}</p>
                        <p className="text-xs font-mono text-[#64748B]">{assigned.site.lat.toFixed(4)}, {assigned.site.lng.toFixed(4)} • {assigned.site.radius} m</p>
                        {assigned.site.address && <p className="text-xs text-[#94A3B8] mt-1">{assigned.site.address}</p>}
                    </div>
                )}

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="rounded-2xl bg-[#F8FAFC] h-[240px] flex flex-col items-center justify-center p-6">
                        {tanpaTitik ? (
                            <p className="text-sm text-[#991B1B] text-center font-medium">Absen terkunci — tanpa titik assigned<br/><span className="text-xs font-normal text-[#94A3B8]">422 office_location_id null</span></p>
                        ) : !captured ? (
                            <>
                                <span className="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><path d="M14 4a2 2 0 012 2v1h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2V6a2 2 0 012-2h4z"/><circle cx="12" cy="13" r="3.5"/><path d="M16 6h1"/></svg>
                                </span>
                                <p className="text-sm font-medium text-[#0F172A] mt-3">Siap absen</p>
                                <p className="text-xs text-[#64748B] text-center mt-1">Kamera + lokasi untuk pratinjau jarak ke <span className="font-medium text-[#0F172A]">{assigned.site.nama_lokasi}</span></p>
                                <button type="button" onClick={() => setCaptured(true)} className="mt-4 bg-[#0F172A] text-white rounded-xl px-5 py-2.5 text-sm font-semibold">Buka kamera &amp; lokasi</button>
                            </>
                        ) : (
                            <div className="w-full text-center">
                                <div className="mx-auto w-20 h-20 rounded-full bg-white shadow-sm flex items-center justify-center overflow-hidden">
                                    {photoPreview ? <img src={photoPreview} alt="selfie" className="w-full h-full object-cover" /> : <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/><circle cx="12" cy="8" r="4"/><path d="M8 12h8"/></svg>}
                                </div>
                                <p className="text-sm font-semibold text-[#0F172A] mt-3">Pratinjau selfie</p>
                                <p className={`text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 ${inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{demoJarak} m / {assigned.site.radius} m • {inRadius ? 'Dalam radius titik assigned' : 'Di luar radius — ditolak 422'}</p>
                                <p className="text-xs text-[#94A3B8] mt-1">{nowStr} WITA • {assigned.region.name} • {assigned.site.nama_lokasi}</p>
                                <div className="flex gap-2 justify-center mt-4">
                                    <button type="button" onClick={() => setCaptured(false)} className="rounded-xl bg-white shadow-sm px-4 py-2 text-sm font-medium text-[#334155]">Ulangi</button>
                                    <button type="button" onClick={handleKirim} disabled={!inRadius || alreadyToday} title={!inRadius ? `${demoJarak} m / ${assigned.site.radius} m — di luar radius` : alreadyToday ? 'Sudah absen hari ini' : ''} className={`rounded-xl px-5 py-2 text-sm font-semibold ${inRadius && !alreadyToday ? 'bg-[#0D9488] text-white' : 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed'}`}>Kirim absen masuk</button>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" onClick={handlePulang} disabled={tanpaTitik} title={tanpaTitik ? 'Tanpa titik — hubungi Admin (422)' : ''} className={`rounded-xl py-3 text-sm font-medium ${tanpaTitik ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#F8FAFC] text-[#334155] hover:bg-[#EFF6FF]'}`}>Absen pulang</button>
                        <button type="button" onClick={() => setCaptured(true)} disabled={tanpaTitik || (captured && !inRadius)} title={tanpaTitik ? 'Tanpa titik — tidak bisa absen (422)' : captured && !inRadius ? `${demoJarak} m / ${assigned.site.radius} m — di luar radius` : ''} className={`rounded-xl py-3 text-sm font-semibold ${tanpaTitik || (captured && !inRadius) ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>Absen masuk</button>
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3 text-center">{tanpaTitik ? 'Tanpa titik tidak dapat absen (422)' : captured && !inRadius ? `${demoJarak} m / ${assigned.site.radius} m — di luar radius ${assigned.site.nama_lokasi}` : `Di luar radius ${assigned.site.radius} m titik assigned tidak dapat absen — CRUD lokal sinkron Admin`}</p>
                    {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 mt-3">{toast}</p>}
                    {alreadyToday && <p className="text-xs text-center text-[#92400E] mt-2">Sudah absen hari ini ({todayISO}) — lihat riwayat</p>}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat</h3>
                        <span className="text-xs text-[#64748B]">{myHistory.length} entri • {assigned ? assigned.site.nama_lokasi : 'Tanpa titik'} • sinkron Admin</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {myHistory.length===0 ? <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada absen — kirim absen masuk di atas (CRUD lokal)</p> : myHistory.map((r) => {
                            const hit = assigned && r.office_location_id === assigned.site.id;
                            const ok = assigned ? r.jarak <= assigned.site.radius : false;
                            return (
                                <div key={`${r.tgl}-${r.datang}-${r.id}`} className="px-5 py-3.5">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-[#0F172A]">{r.tgl} • {r.datang}{r.pulang ? ` → ${r.pulang}` : ''} • {r.status==='late' ? 'Terlambat' : r.status==='on_time' ? 'Tepat waktu' : r.status}</p>
                                            <p className="text-xs text-[#64748B]">{r.jarak} m / {assigned ? `${assigned.site.radius} m • ${ok ? 'Dalam' : 'Di luar'} • ${assigned.site.nama_lokasi}` : 'Tanpa titik'} {hit ? '' : '• titik lain'}</p>
                                        </div>
                                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${r.status==='on_time' ? 'bg-[#ECFDF5] text-[#065F46]' : r.status==='excused_love' ? 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30' : 'bg-[#FFFBEB] text-[#92400E]'}`}>{r.status==='on_time' ? 'Tepat waktu' : r.status==='late' ? 'Terlambat' : r.status}</span>
                                    </div>
                                    {r.status === 'late' && (
                                        <div className="mt-3 bg-[#FFF7E6] rounded-xl p-3 flex items-center justify-between">
                                            <p className="text-xs text-[#92400E]">Terlambat • bisa pakai 1 Love {assigned ? `• ${assigned.site.nama_lokasi}` : ''}</p>
                                            <a href="/karyawan/love" className="bg-[#FCB833] text-[#0F172A] rounded-lg px-3 py-1.5 text-xs font-semibold">Gunakan Love</a>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
