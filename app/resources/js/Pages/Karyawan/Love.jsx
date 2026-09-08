import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadSettings, loadLove, saveLove, getApproversForSite, LOVE_JENIS, loveJenisLabel } from '@/Pages/Admin/_shared';

const MOCK_KARYAWAN_ID = 1;

function todayISO() { return new Date().toISOString().slice(0,10); }
function isWeekend(iso) { const d = new Date(iso + 'T12:00:00'); const w = d.getDay(); return w === 0 || w === 6; }

export default function Love() {
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [allLove, setAllLove] = useState(() => loadLove());
    const [settings, setSettings] = useState(() => loadSettings());
    const [jenis, setJenis] = useState('lupa_absen');
    const [tgl, setTgl] = useState(todayISO());
    const [jam, setJam] = useState('07:35');
    const [alasan, setAlasan] = useState('');
    const [qApprover, setQApprover] = useState('');
    const [approverId, setApproverId] = useState(null);
    const [openApprover, setOpenApprover] = useState(false);
    const [toast, setToast] = useState(null);
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); setAllLove(loadLove()); setSettings(loadSettings()); };
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

    const max = settings.loveMax ?? 4;
    const myClaims = useMemo(() => allLove.filter((c) => c.employee_id === MOCK_KARYAWAN_ID), [allLove]);
    const approvedCount = myClaims.filter((c) => c.status === 'approved').length;
    const pendingCount = myClaims.filter((c) => c.status === 'pending').length;
    const sisa = Math.max(0, max - approvedCount - pendingCount);

    const approvers = useMemo(() => assigned ? getApproversForSite(assigned.site.id) : [], [assigned]);
    const filteredApprovers = useMemo(() => {
        const q = qApprover.trim().toLowerCase();
        if (!q) return approvers;
        return approvers.filter((a) => a.nama.toLowerCase().includes(q) || a.nip.includes(q));
    }, [approvers, qApprover]);
    const selectedApprover = useMemo(() => approvers.find((a)=>a.id===approverId) || null, [approvers, approverId]);

    useEffect(() => {
        // default approver: first option when assigned changes
        if (approvers.length && approverId == null) setApproverId(approvers[0].id);
    }, [approvers, approverId]);


    const handleClaim = () => {
        if (!alasan.trim()) { setToast('Isi alasan'); setTimeout(()=>setToast(null),2200); return; }
        if (!approverId) { setToast('Pilih atasan untuk di-ACC'); setTimeout(()=>setToast(null),2200); return; }
        if (sisa <= 0) { setToast('Sisa Toleransi 0 — reset bulan depan'); setTimeout(()=>setToast(null),2200); return; }
        const approver = approvers.find((a)=>a.id===approverId);
        if (!approver) { setToast('Atasan tidak valid — pilih dari daftar'); setTimeout(()=>setToast(null),2200); return; }

        if (!tgl) { setToast('Pilih tanggal'); setTimeout(()=>setToast(null),2200); return; }
        if (tgl > todayISO()) { setToast('Tanggal tidak boleh melebihi hari ini'); setTimeout(()=>setToast(null),2200); return; }
        if (isWeekend(tgl)) { setToast('Tanggal tidak boleh weekend'); setTimeout(()=>setToast(null),2200); return; }
        if (!jam || !/^\d{2}:\d{2}$/.test(jam)) { setToast('Jam wajib format HH:MM'); setTimeout(()=>setToast(null),2200); return; }
        const next = {
            id: Date.now(), employee_id: MOCK_KARYAWAN_ID, nama: me?.nama || 'Andi Saputra', wilayah: me?.region || 'Kab. Gowa', kantor: assigned.region.kantor,
            office_location_id: assigned.site.id, jenis, tgl, jam, jarak: null, radius: assigned.site.radius,
            alasan: alasan.trim(), approver_id: approver.id, approver_nama: approver.nama, approver_nip: approver.nip, approver_scope: approver.scope, status: 'pending', createdAt: new Date().toISOString(),
        };
        const updated = [next, ...allLove];
        setAllLove(updated); saveLove(updated);
        setAlasan('');
        setToast('Toleransi diajukan — menunggu persetujuan'); setTimeout(()=>setToast(null),2500);
    };

    if (!assigned) {
        return (
            <KaryawanLayout>
                <div className="bg-white rounded-2xl p-6 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <p className="text-sm text-[#64748B]">Titik belum di-assign — hubungi Admin (1 karyawan = 1 titik)</p>
                </div>
            </KaryawanLayout>
        );
    }

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Toleransi</h2>
                    <p className="text-sm text-[#64748B]">{max} toleransi/bulan • Sisa {sisa}/{max} • Reset 1 {new Date(new Date().getFullYear(), new Date().getMonth()+1, 1).toLocaleDateString('id-ID',{month:'short'})} 00:00 WITA • {assigned.site.nama_lokasi} • {assigned.site.radius} m</p>
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
                    <p className="text-xs text-[#94A3B8] mt-3">Pakai 1 toleransi untuk <span className="font-medium text-[#0F172A]">lupa absen datang / lupa absen pulang</span> di titik {assigned.site.nama_lokasi} (bulan sama, cukup alasan) → persetujuan 1 level Admin {assigned.region.name} • kuota {max}/bulan</p>
                    {sisa === 0 && <p className="text-xs font-medium text-[#EF4444] mt-2">Sisa 0 — pengajuan berikutnya tidak bisa di-approve</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h3 className="font-medium text-sm text-[#0F172A]">Ajukan Toleransi</h3>
                    <p className="text-xs text-[#94A3B8] mt-1">Pilih jenis → isi alasan → pilih atasan</p>

                    <div className="mt-4 flex gap-2 flex-wrap">
                        {LOVE_JENIS.map((j) => (
                            <button key={j.value} type="button" onClick={()=>setJenis(j.value)} className={`rounded-xl px-3 py-2 text-xs font-semibold border ${jenis===j.value ? 'bg-[#0F172A] text-white border-[#0F172A]' : 'bg-white text-[#334155] border-[#E2E8F0] hover:bg-[#F8FAFC]'}`}>{j.label}</button>
                        ))}
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-[#334155]">{jenis === 'lupa_absen' ? 'Tanggal lupa absen datang' : 'Tanggal lupa absen pulang'}</label>
                            <input type="date" value={tgl} max={todayISO()} onChange={(e)=>setTgl(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Jam {jenis === 'lupa_pulang' ? 'pulang' : 'datang'}</label>
                            <input type="time" value={jam} onChange={(e)=>setJam(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                        </div>
                    </div>

                    <div className="mt-4 space-y-3 border-t border-[#F1F5F9] pt-4">
                        <div className="relative">
                            <label className="text-xs font-medium text-[#334155]">Pilih atasan untuk di-ACC <span className="text-[#EF4444]">*</span></label>
                            <p className="text-xs text-[#94A3B8]">Cari nama atau NIP atasan</p>
                            <button type="button" onClick={()=>setOpenApprover((v)=>!v)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] px-3 py-2.5 text-sm text-left flex items-center justify-between outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20">
                                <span className={selectedApprover ? 'text-[#0F172A] font-medium' : 'text-[#94A3B8]'}>{selectedApprover ? `${selectedApprover.nama} • ${selectedApprover.nip} • ${selectedApprover.scope}` : 'Pilih atasan...'}</span>
                                <span className="text-[#94A3B8]">▾</span>
                            </button>
                            {openApprover && (
                                <div className="absolute z-20 mt-2 w-full bg-white rounded-xl border border-[#E2E8F0] shadow-[0_8px_24px_rgba(15,23,42,0.12)] overflow-hidden">
                                    <div className="p-2 border-b border-[#F1F5F9]">
                                        <input autoFocus value={qApprover} onChange={(e)=>setQApprover(e.target.value)} placeholder="Cari nama atau NIP..." className="w-full rounded-lg bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                                    </div>
                                    <div className="max-h-44 overflow-auto">
                                        {filteredApprovers.length === 0 ? (
                                            <p className="text-xs text-[#94A3B8] text-center py-4">Tidak ada atasan untuk kata kunci ini</p>
                                        ) : filteredApprovers.map((a)=>(
                                            <button key={a.id} type="button" onClick={()=>{ setApproverId(a.id); setOpenApprover(false); setQApprover(''); }} className={`w-full text-left px-3 py-2.5 text-sm hover:bg-[#F8FAFC] ${approverId===a.id ? 'bg-[#FFF7E6]' : ''}`}>
                                                <p className="font-medium text-[#0F172A]">{a.nama} <span className="font-normal text-[#64748B]">• {a.jabatan}</span></p>
                                                <p className="text-xs text-[#94A3B8] font-mono">{a.nip} • {a.scope}</p>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div>
                            <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan (wajib)</label>
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e) => setAlasan(e.target.value)} placeholder={jenis === 'lupa_absen' ? 'Contoh: Lupa absen datang karena HP lowbat' : 'Contoh: Lupa absen pulang — rapat di lapangan'} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20"></textarea>

                        </div>
                        <button type="button" onClick={handleClaim} disabled={!alasan.trim() || !approverId || sisa <= 0} title={!alasan.trim() ? 'Isi alasan dulu' : !approverId ? 'Pilih atasan' : sisa <= 0 ? 'Sisa Toleransi 0 — reset 1 bulan depan' : ''} className="w-full rounded-xl py-3 text-sm font-semibold bg-[#FCB833] text-[#0F172A] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8]">Gunakan 1 toleransi — Kirim ke {selectedApprover ? selectedApprover.nama : 'atasan'}</button>

                    </div>
                    {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 mt-3">{toast}</p>}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat Toleransi saya</h3>
                        <span className="text-xs text-[#94A3B8]">Bulan ini • {myClaims.length}</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {myClaims.length === 0 ? <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada Toleransi — ajukan saat lupa absen datang / pulang</p> : myClaims.map((c) => (
                            <div key={c.id} className="px-5 py-3.5 flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-[#0F172A]">{loveJenisLabel(c.jenis)} • {c.tgl || new Date(c.createdAt).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})} • {c.jam} {c.jarak != null ? `• ${c.jarak}m/${c.radius ?? '?'}m` : `• ${c.approver_scope || ''}`}</p>
                                    <p className="text-xs text-[#64748B] mt-0.5">{c.alasan} {c.note ? `• ${c.note}` : ''}</p>
                                    <p className="text-xs text-[#94A3B8]">Atasan: {c.approver_nama || '—'} {c.approver_nip ? `• ${c.approver_nip}` : ''} {c.approver_scope ? `• ${c.approver_scope}` : ''}</p>
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
