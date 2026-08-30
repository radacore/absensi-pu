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
    const [jenis, setJenis] = useState('terlambat');
    const [selected, setSelected] = useState(null);
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

    // eligible late: hari ini late = demo 1 eligible, real: dari LS_ATTENDANCES jika ada late hari ini dalam radius
    const eligible = useMemo(() => {
        const alreadyClaimedToday = myClaims.some((c) => c.jenis === 'terlambat' && c.jam === '07:52');
        if (alreadyClaimedToday) return [];
        return [{ id: 901, tgl: new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }), jam: '07:52', jarak: 42, status: 'late' }];
    }, [myClaims]);

    const handleClaim = () => {
        if (!alasan.trim()) { setToast('Isi alasan'); setTimeout(()=>setToast(null),2200); return; }
        if (!approverId) { setToast('Pilih atasan untuk di-ACC'); setTimeout(()=>setToast(null),2200); return; }
        if (sisa <= 0) { setToast('Sisa Love 0 — reset bulan depan'); setTimeout(()=>setToast(null),2200); return; }
        const approver = approvers.find((a)=>a.id===approverId);
        if (!approver) { setToast('Atasan tidak valid — pilih dari daftar'); setTimeout(()=>setToast(null),2200); return; }

        if (jenis === 'terlambat') {
            if (!selected) { setToast('Pilih keterlambatan'); setTimeout(()=>setToast(null),2200); return; }
            if (selected.jarak > assigned.site.radius) { setToast('Di luar radius — tidak bisa claim (422)'); setTimeout(()=>setToast(null),2200); return; }
            const next = {
                id: Date.now(), employee_id: MOCK_KARYAWAN_ID, nama: me?.nama || 'Andi Saputra', wilayah: me?.region || 'Kab. Gowa', kantor: assigned.region.kantor,
                office_location_id: assigned.site.id, jenis: 'terlambat', tgl: tgl || todayISO(), jam: selected.jam, jarak: selected.jarak, radius: assigned.site.radius,
                alasan: alasan.trim(), approver_id: approver.id, approver_nama: approver.nama, approver_nip: approver.nip, approver_scope: approver.scope, status: 'pending', createdAt: new Date().toISOString(),
            };
            const updated = [next, ...allLove];
            setAllLove(updated); saveLove(updated);
            setSelected(null); setAlasan('');
            setToast('Love diajukan — menunggu persetujuan'); setTimeout(()=>setToast(null),2500);
            return;
        }
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
        setToast('Love diajukan — menunggu persetujuan'); setTimeout(()=>setToast(null),2500);
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
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Love</h2>
                    <p className="text-sm text-[#64748B]">{max} Love/bulan • Sisa {sisa}/{max} • Reset 1 {new Date(new Date().getFullYear(), new Date().getMonth()+1, 1).toLocaleDateString('id-ID',{month:'short'})} 00:00 WITA • {assigned.site.nama_lokasi} • {assigned.site.radius} m</p>
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
                    <p className="text-xs text-[#94A3B8] mt-3">Pakai 1 Love untuk <span className="font-medium text-[#0F172A]">terlambat / lupa absen / lupa pulang</span> dalam radius {assigned.site.nama_lokasi} {assigned.site.radius} m (bulan sama, cukup alasan) → persetujuan 1 level Admin {assigned.region.name} • kuota {max}/bulan</p>
                    {sisa === 0 && <p className="text-xs font-medium text-[#EF4444] mt-2">Sisa 0 — pengajuan berikutnya tidak bisa di-approve</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h3 className="font-medium text-sm text-[#0F172A]">Ajukan Love</h3>
                    <p className="text-xs text-[#94A3B8] mt-1">Pilih jenis → isi alasan → pilih atasan</p>

                    <div className="mt-4 flex gap-2 flex-wrap">
                        {LOVE_JENIS.map((j) => (
                            <button key={j.value} type="button" onClick={()=>{ setJenis(j.value); setSelected(null); }} className={`rounded-xl px-3 py-2 text-xs font-semibold border ${jenis===j.value ? 'bg-[#0F172A] text-white border-[#0F172A]' : 'bg-white text-[#334155] border-[#E2E8F0] hover:bg-[#F8FAFC]'}`}>{j.label}</button>
                        ))}
                    </div>

                    {jenis === 'terlambat' ? (
                        <div className="mt-4 space-y-3">
                            <p className="text-xs font-medium text-[#334155]">Terlambat yang bisa pakai Love</p>
                            <p className="text-xs text-[#94A3B8]">Hanya late bulan sama & dalam radius {assigned.site.nama_lokasi} {assigned.site.radius} m • Di luar radius ditolak 422</p>
                            {eligible.length === 0 ? (
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
                            {selected && <p className="text-xs font-medium text-[#0F172A]">Ajukan untuk {selected.tgl} • {selected.jam} • {assigned.site.nama_lokasi}</p>}
                        </div>
                    ) : (
                        <div className="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-[#334155]">{jenis === 'lupa_absen' ? 'Tanggal lupa absen' : 'Tanggal lupa pulang'}</label>
                                <input type="date" value={tgl} max={todayISO()} onChange={(e)=>setTgl(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                                </div>
                            <div>
                                <label className="text-xs font-medium text-[#334155]">Jam {jenis === 'lupa_pulang' ? 'pulang' : 'datang'}</label>
                                <input type="time" value={jam} onChange={(e)=>setJam(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                            </div>
                        </div>
                    )}

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
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e) => setAlasan(e.target.value)} placeholder={jenis === 'terlambat' ? 'Contoh: Macet poros Gowa karena perbaikan jalan' : jenis === 'lupa_absen' ? 'Contoh: Lupa absen datang karena HP lowbat' : 'Contoh: Lupa absen pulang — rapat di lapangan'} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20"></textarea>

                        </div>
                        <button type="button" onClick={handleClaim} disabled={!alasan.trim() || !approverId || sisa <= 0 || (jenis==='terlambat' && (!selected || selected.jarak > assigned.site.radius))} title={!alasan.trim() ? 'Isi alasan dulu' : !approverId ? 'Pilih atasan' : sisa <= 0 ? 'Sisa Love 0 — reset 1 bulan depan' : jenis==='terlambat' && !selected ? 'Pilih keterlambatan' : ''} className="w-full rounded-xl py-3 text-sm font-semibold bg-[#FCB833] text-[#0F172A] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8]">Gunakan 1 Love — Kirim ke {selectedApprover ? selectedApprover.nama : 'atasan'}</button>

                    </div>
                    {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 mt-3">{toast}</p>}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat Love saya</h3>
                        <span className="text-xs text-[#94A3B8]">Bulan ini • {myClaims.length}</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {myClaims.length === 0 ? <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada Love — ajukan saat terlambat/lupa absen</p> : myClaims.map((c) => (
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
