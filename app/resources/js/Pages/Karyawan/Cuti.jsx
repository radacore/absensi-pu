import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadCuti, saveCuti } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

export default function Cuti() {
    const [showForm, setShowForm] = useState(false);
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [allCuti, setAllCuti] = useState(() => loadCuti());
    const [jenis, setJenis] = useState('Tahunan');
    const [mulai, setMulai] = useState('');
    const [selesai, setSelesai] = useState('');
    const [alasan, setAlasan] = useState('');
    const [toast, setToast] = useState(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); setAllCuti(loadCuti()); };
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
    const myList = useMemo(() => allCuti.filter((c) => c.employee_id === MOCK_KARYAWAN_ID), [allCuti]);

    const handleSubmit = () => {
        if (!mulai || !selesai) { setToast('Tanggal mulai & selesai wajib'); setTimeout(()=>setToast(null),2500); return; }
        if (mulai > selesai) { setToast('Tanggal mulai tidak boleh setelah selesai'); setTimeout(()=>setToast(null),2500); return; }
        if (!alasan.trim()) { setToast('Alasan wajib diisi'); setTimeout(()=>setToast(null),2500); return; }
        const tgl = mulai === selesai ? new Date(mulai).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) : `${new Date(mulai).toLocaleDateString('id-ID', { day:'2-digit', month:'short' })}–${new Date(selesai).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })}`;
        const next = {
            id: Date.now(), employee_id: MOCK_KARYAWAN_ID, nama: me?.nama || 'Andi Saputra', email: me?.email || 'andi@bbws-pj.go.id',
            wilayah: me?.region || 'Kab. Gowa', regionId: me?.regionId ?? 2, office_location_id: me?.office_location_id ?? 201,
            jenis, tgl, mulai, selesai, alasan: alasan.trim(), dokumen: null, status: 'Menunggu', level: 0, createdAt: new Date().toISOString(),
        };
        const updated = [next, ...allCuti];
        setAllCuti(updated); saveCuti(updated);
        setToast('Pengajuan cuti dikirim — menunggu persetujuan berjenjang (sinkron ke Admin)'); setTimeout(()=>setToast(null),2500);
        setJenis('Tahunan'); setMulai(''); setSelesai(''); setAlasan(''); setShowForm(false);
    };
    const handleCancel = (id) => {
        const updated = allCuti.filter((c) => c.id !== id);
        setAllCuti(updated); saveCuti(updated); setConfirmDeleteId(null);
    };

    const tone = (s) => s === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : s === 'Menunggu' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]';

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Cuti</h2>
                        <p className="text-sm text-[#64748B]">Berjenjang 3 tahap • {assigned.site.nama_lokasi} • {assigned.site.radius}m</p>
                        <p className="text-xs text-[#94A3B8] mt-1">CRUD lokal — ajukan di sini, Admin setujui/tolak sinkron</p>
                    </div>
                    <button type="button" onClick={() => setShowForm(!showForm)} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">{showForm ? 'Tutup' : 'Ajukan cuti'}</button>
                </div>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="jenis" className="text-xs font-medium text-[#334155]">Jenis</label>
                                <select id="jenis" value={jenis} onChange={(e)=>setJenis(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white outline-none"><option>Tahunan</option><option>Sakit</option><option>Besar</option><option>Melahirkan</option></select>
                            </div>
                            <div>
                                <label htmlFor="mulai" className="text-xs font-medium text-[#334155]">Mulai</label>
                                <input id="mulai" type="date" value={mulai} onChange={(e)=>setMulai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="selesai" className="text-xs font-medium text-[#334155]">Selesai</label>
                                <input id="selesai" type="date" value={selesai} onChange={(e)=>setSelesai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>

                        </div>
                        <div>
                            <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e)=>setAlasan(e.target.value)} placeholder="Tuliskan alasan cuti" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none"></textarea>
                        </div>
                        <button type="button" onClick={handleSubmit} className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#1E3A8A] transition">Kirim pengajuan</button>
                        {toast && <p className="text-xs text-center bg-[#FEF3C7] text-[#92400E] rounded-xl py-2">{toast}</p>}
                    </div>
                )}

                {!showForm && toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                <div className="space-y-3">
                    {myList.length === 0 ? (
                        <p className="text-sm text-[#94A3B8] bg-white rounded-2xl p-6 text-center">Belum ada pengajuan — ajukan cuti pertama</p>
                    ) : myList.map((r) => (
                        <div key={r.id} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-sm text-[#0F172A]">{r.jenis} • {r.tgl}</p>
                                    <p className="text-sm text-[#475569] mt-1">{r.alasan}</p>
                                    <p className="text-xs text-[#94A3B8] mt-1">{r.wilayah} • titik {r.office_location_id}</p>
                                </div>
                                <span className={`shrink-0 text-xs font-medium px-2.5 py-1 rounded-full ${tone(r.status)}`}>{r.status}</span>
                            </div>
                            <div className="mt-4 flex items-center gap-1.5">
                                {['Atasan', 'Admin Wilayah', 'Kantor Pusat'].map((s, i) => (
                                    <div key={s} className="flex items-center gap-1.5">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${i < r.level ? 'bg-[#0F172A] text-white' : i === r.level && r.status === 'Menunggu' ? 'bg-[#FEF3C7] text-[#92400E]' : i === r.level && r.status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F1F5F9] text-[#64748B]'}`}>{s}</span>
                                        {i < 2 && <span className="text-[#CBD5E1]">—</span>}
                                    </div>
                                ))}
                            </div>
                            {r.status === 'Menunggu' && r.level === 0 && (
                                <button type="button" onClick={()=>setConfirmDeleteId(r.id)} className="mt-3 text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Batalkan pengajuan</button>
                            )}
                            {r.status === 'Ditolak' && r.note && <p className="text-xs text-[#991B1B] bg-[#FEF2F2] rounded-lg px-3 py-1.5 mt-2">Catatan: {r.note}</p>}
                        </div>
                    ))}
                </div>
                {confirmDeleteId != null && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-2xl p-5 w-full max-w-sm">
                            <p className="font-medium text-[#0F172A]">Batalkan cuti?</p>
                            <p className="text-sm text-[#64748B] mt-1">Pengajuan yang dibatalkan tidak bisa dikembalikan.</p>
                            <div className="flex gap-2 mt-4">
                                <button type="button" onClick={()=>setConfirmDeleteId(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-2.5 text-sm font-medium">Batal</button>
                                <button type="button" onClick={()=>handleCancel(confirmDeleteId)} className="flex-1 rounded-xl bg-[#EF4444] text-white py-2.5 text-sm font-semibold">Batalkan</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </KaryawanLayout>
    );
}
