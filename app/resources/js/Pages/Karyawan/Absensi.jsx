import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1; // Andi Saputra — 1 karyawan = 1 titik

export default function Absensi() {
    const [captured, setCaptured] = useState(false);
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); };
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
    const history = [
        { tgl: '24 Agu 2026', jam: '07:38', type: 'Masuk', status: 'Tepat waktu', jarak: 38, tone: 'emerald' },
        { tgl: '23 Agu 2026', jam: '16:05', type: 'Pulang', status: 'Tepat waktu', jarak: 21, tone: 'emerald' },
        { tgl: '23 Agu 2026', jam: '07:52', type: 'Masuk', status: 'Terlambat', jarak: 42, tone: 'amber' },
        { tgl: '22 Agu 2026', jam: '07:31', type: 'Masuk', status: 'Tepat waktu', jarak: 18, tone: 'emerald' },
    ];
    const demoJarak = 42;
    const inRadius = assigned ? demoJarak <= assigned.site.radius : false;
    const tanpaTitik = !assigned;
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
                    <p className="text-xs text-[#94A3B8] mt-1">Valid hanya di titik assigned dalam radius titiknya — di luar / titik lain ditolak 422</p>
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
                                <button type="button" onClick={() => setCaptured(true)} className="mt-4 bg-[#0F172A] text-white rounded-xl px-5 py-2.5 text-sm font-semibold">Buka kamera & lokasi</button>
                            </>
                        ) : (
                            <div className="w-full text-center">
                                <div className="mx-auto w-20 h-20 rounded-full bg-white shadow-sm flex items-center justify-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/><circle cx="12" cy="8" r="4"/><path d="M8 12h8"/></svg>
                                </div>
                                <p className="text-sm font-semibold text-[#0F172A] mt-3">Pratinjau selfie</p>
                                <p className={`text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 ${inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{demoJarak} m / {assigned.site.radius} m • {inRadius ? 'Dalam radius titik assigned' : 'Di luar radius — ditolak 422'}</p>
                                <p className="text-xs text-[#94A3B8] mt-1">07:42 WITA • {assigned.region.name} • {assigned.site.nama_lokasi}</p>
                                <div className="flex gap-2 justify-center mt-4">
                                    <button type="button" onClick={() => setCaptured(false)} className="rounded-xl bg-white shadow-sm px-4 py-2 text-sm font-medium text-[#334155]">Ulangi</button>
                                    <button type="button" disabled={!inRadius} className={`rounded-xl px-5 py-2 text-sm font-semibold ${inRadius ? 'bg-[#0D9488] text-white' : 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed'}`}>Kirim absen masuk</button>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" disabled={tanpaTitik} className={`rounded-xl py-3 text-sm font-medium ${tanpaTitik ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#F8FAFC] text-[#334155]'}`}>Absen pulang</button>
                        <button type="button" disabled={tanpaTitik || (captured && !inRadius)} className={`rounded-xl py-3 text-sm font-semibold ${tanpaTitik || (captured && !inRadius) ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>Absen masuk</button>
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3 text-center">{tanpaTitik ? 'Tanpa titik tidak dapat absen (422)' : `Di luar radius ${assigned.site.radius} m titik assigned tidak dapat absen — tombol terkunci otomatis`}</p>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat</h3>
                        <span className="text-xs text-[#64748B]">7 hari • Love 3/4 • {assigned ? assigned.site.nama_lokasi : 'Tanpa titik'}</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {history.map((r) => {
                            const ok = assigned ? r.jarak <= assigned.site.radius : false;
                            return (
                                <div key={`${r.tgl}-${r.jam}`} className="px-5 py-3.5">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-[#0F172A]">{r.tgl} • {r.jam} • {r.type}</p>
                                            <p className="text-xs text-[#64748B]">{r.jarak} m / {assigned ? `${assigned.site.radius} m • ${ok ? 'Dalam' : 'Di luar'} • ${assigned.site.nama_lokasi}` : 'Tanpa titik'}</p>
                                        </div>
                                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${r.tone === 'emerald' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FFFBEB] text-[#92400E]'}`}>{r.status}</span>
                                    </div>
                                    {r.status === 'Terlambat' && (
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
