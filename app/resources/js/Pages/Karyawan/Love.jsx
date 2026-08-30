import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadSettings, loadLove, saveLove } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

export default function Love() {
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [allLove, setAllLove] = useState(() => loadLove());
    const [settings, setSettings] = useState(() => loadSettings());
    const [selected, setSelected] = useState(null);
    const [alasan, setAlasan] = useState('');
    const [dokumen, setDokumen] = useState(null);
    const [toast, setToast] = useState(null);
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); setAllLove(loadLove()); setSettings(loadSettings()); };
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

    const max = settings.loveMax ?? 4;
    const myClaims = useMemo(() => allLove.filter((c) => c.employee_id === MOCK_KARYAWAN_ID), [allLove]);
    const approvedCount = myClaims.filter((c) => c.status === 'approved').length;
    const pendingCount = myClaims.filter((c) => c.status === 'pending').length;
    const sisa = Math.max(0, max - approvedCount - pendingCount);

    // eligible late: hari ini late = demo 1 eligible, real: dari LS_ATTENDANCES jika ada late hari ini dalam radius
    const eligible = useMemo(() => {
        if (!assigned) return [];
        // demo: 1 late hari ini yang belum di-claim
        const alreadyClaimedToday = myClaims.some((c) => c.jam === '07:52');
        if (alreadyClaimedToday) return [];
        return [{ id: 901, tgl: new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }), jam: '07:52', jarak: 42, status: 'late' }];
    }, [assigned, myClaims]);

    const handleClaim = () => {
        if (!selected || !alasan.trim()) { setToast('Pilih late & isi alasan'); setTimeout(()=>setToast(null),2200); return; }
        if (sisa <= 0) { setToast('Sisa Love 0 — reset bulan depan'); setTimeout(()=>setToast(null),2200); return; }
        if (!assigned) { setToast('Tanpa titik — tidak bisa claim'); setTimeout(()=>setToast(null),2200); return; }
        if (selected.jarak > assigned.site.radius) { setToast('Di luar radius — tidak bisa claim (422)'); setTimeout(()=>setToast(null),2200); return; }
        const next = {
            id: Date.now(), employee_id: MOCK_KARYAWAN_ID, nama: me?.nama || 'Andi Saputra', wilayah: me?.region || 'Kab. Gowa', kantor: assigned.region.kantor,
            office_location_id: assigned.site.id, jam: selected.jam, jarak: selected.jarak, radius: assigned.site.radius,
            alasan: alasan.trim(), dokumen: dokumen?.name || 'dokumen.pdf', status: 'pending', createdAt: new Date().toISOString(),
        };
        const updated = [next, ...allLove];
        setAllLove(updated); saveLove(updated);
        setSelected(null); setAlasan(''); setDokumen(null);
        setToast('Love diajukan — menunggu Admin (sinkron)'); setTimeout(()=>setToast(null),2500);
    };

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Love</h2>
                    <p className="text-sm text-[#64748B]">{max} Love/bulan • Sisa {sisa}/{max} • Reset 1 {new Date(new Date().getFullYear(), new Date().getMonth()+1, 1).toLocaleDateString('id-ID',{month:'short'})} 00:00 WITA {assigned ? `• ${assigned.site.nama_lokasi} • ${assigned.site.radius} m` : '• Tanpa titik'}</p>
                    {!assigned && <p className="text-xs font-medium text-[#991B1B] mt-1">Tanpa titik — late di luar titik assigned tidak bisa di-excuse (melebihi radius)</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                            <span className="w-7 h-7 rounded-lg bg-[#FFF7E6] flex items-center justify-center">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB833" stroke="#FCB833" strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
                            </span>
                            Sisa toleransi
                        </h3>
                        <span className="text-xs font-semibold text-[#0F172A] bg-[#FFF7E6] px-3 py-1 rounded-full border border-[#FCB833]/20">{sisa} / {max}</span>
                    </div>
                    <div className="mt-4 flex gap-2">
                        {Array.from({length: max}, (_,i) => (<span key={i} className={`flex-1 h-2.5 rounded-full ${i < sisa ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3">Pakai 1 Love untuk 1 keterlambatan <span className="font-medium text-[#0F172A]">dalam radius {assigned ? `${assigned.site.nama_lokasi} ${assigned.site.radius} m` : 'titik assigned'}</span> + dokumen (bulan sama) → approval 1 level {assigned ? `Admin ${assigned.region.name}` : 'Admin Wilayah'} • CRUD lokal</p>
                    {sisa === 0 && <p className="text-xs font-medium text-[#EF4444] mt-2">Sisa 0 — keterlambatan berikutnya tidak bisa di-excuse</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h3 className="font-medium text-sm text-[#0F172A]">Terlambat yang bisa pakai Love</h3>
                    <p className="text-xs text-[#94A3B8] mt-1">Hanya late bulan sama & dalam radius {assigned ? `${assigned.site.nama_lokasi} ${assigned.site.radius} m` : 'titik assigned'} • Di luar radius ditolak 422</p>
                    <div className="mt-4 space-y-3">
                        {!assigned ? (
                            <p className="text-sm text-[#991B1B] bg-[#FEF2F2] rounded-xl p-3 text-center">Tanpa titik assigned — tidak ada late yang bisa pakai Love. Hubungi Admin untuk assign titik.</p>
                        ) : eligible.length === 0 ? (
                            <p className="text-sm text-[#64748B] text-center py-4">Tidak ada keterlambatan yang bisa pakai Love (sudah diajukan atau tidak ada late hari ini)</p>
                        ) : (
                            eligible.map((l) => {
                                const ok = l.jarak <= assigned.site.radius;
                                return (
                                    <div key={l.id} className={`rounded-xl p-3 flex items-center justify-between ${selected?.id === l.id ? 'bg-[#FFF7E6] border border-[#FCB833]/30' : 'bg-[#F8FAFC]'}`}>
                                        <div>
                                            <p className="text-sm font-medium text-[#0F172A]">{l.tgl} • {l.jam}</p>
                                            <p className={`text-xs ${ok ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>{l.jarak} m / {assigned.site.radius} m • {assigned.site.nama_lokasi} • {ok ? 'dalam radius' : 'di luar radius — tidak bisa'}</p>
                                        </div>
                                        <button type="button" onClick={() => ok && setSelected(l)} disabled={!ok} title={!ok ? `${l.jarak} m / ${assigned.site.radius} m — di luar radius titik assigned, tidak bisa pakai Love` : ''} className={`rounded-xl px-3 py-2 text-xs font-semibold shrink-0 ${!ok ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : selected?.id === l.id ? 'bg-[#0F172A] text-white' : 'bg-[#FCB833] text-[#0F172A]'}`}>
                                            {!ok ? 'Di luar' : selected?.id === l.id ? 'Dipilih' : 'Pilih'}
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>
                    {selected && assigned && (
                        <div className="mt-4 space-y-3 border-t border-[#F1F5F9] pt-4">
                            <p className="text-xs font-medium text-[#0F172A]">Ajukan Love untuk {selected.tgl} • {selected.jam} • {assigned.site.nama_lokasi}</p>
                            <div>
                                <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                                <textarea id="alasan" rows={2} value={alasan} onChange={(e) => setAlasan(e.target.value)} placeholder="Contoh: Macet poros Gowa karena perbaikan jalan" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20"></textarea>
                            </div>
                            <div>
                                <label htmlFor="dok" className="text-xs font-medium text-[#334155]">Dokumen pendukung</label>
                                <input id="dok" type="file" onChange={(e) => setDokumen(e.target.files?.[0] || null)} className="mt-1.5 w-full text-xs text-[#64748B] file:mr-3 file:rounded-lg file:border-0 file:bg-[#0F172A] file:text-white file:px-3 file:py-1.5 file:text-xs file:font-medium" />
                                {dokumen && <p className="text-xs text-[#10B981] mt-1">Terpilih: {dokumen.name}</p>}
                            </div>
                            <button type="button" onClick={handleClaim} disabled={!alasan.trim() || sisa <= 0 || !assigned || selected.jarak > assigned.site.radius} title={!alasan.trim() ? 'Isi alasan dulu' : sisa <= 0 ? 'Sisa Love 0 — reset 1 bulan depan' : !assigned ? 'Tanpa titik — tidak bisa claim' : selected.jarak > assigned.site.radius ? `${selected.jarak} m / ${assigned.site.radius} m — di luar radius` : ''} className="w-full rounded-xl py-3 text-sm font-semibold bg-[#FCB833] text-[#0F172A] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8]">Gunakan 1 Love — Kirim ke {assigned ? `Admin ${assigned.region.name}` : 'Admin Wilayah'}</button>
                            <p className="text-xs text-[#94A3B8] text-center">Mocking API — langsung sinkron ke Admin/Love (localStorage)</p>
                        </div>
                    )}
                    {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 mt-3">{toast}</p>}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat Love saya</h3>
                        <span className="text-xs text-[#94A3B8]">Bulan ini • {myClaims.length} • sinkron Admin</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {myClaims.length === 0 ? <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada Love — ajukan saat terlambat dalam radius</p> : myClaims.map((c) => (
                            <div key={c.id} className="px-5 py-3.5 flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-[#0F172A]">{new Date(c.createdAt).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})} • {c.jam} • {c.jarak}m/{c.radius ?? '?'}m</p>
                                    <p className="text-xs text-[#64748B] mt-0.5">{c.alasan} {c.note ? `• ${c.note}` : ''}</p>
                                    <p className="text-xs text-[#94A3B8]">{c.dokumen}</p>
                                </div>
                                <span className={`text-xs font-medium px-2.5 py-1 rounded-full shrink-0 ${c.status === 'approved' ? 'bg-[#ECFDF5] text-[#065F46]' : c.status === 'pending' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
