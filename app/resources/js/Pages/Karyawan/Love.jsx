import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const LOVE_JENIS = [{ value:'lupa_absen', label:'Lupa absen datang'},{ value:'lupa_pulang', label:'Lupa absen pulang'}];
function loveJenisLabel(v){ return LOVE_JENIS.find((x)=>x.value===v)?.label || v; }
function todayISO(){ return new Date().toISOString().slice(0,10); }
function isWeekend(iso){ const d=new Date(iso+'T12:00:00'); const w=d.getDay(); return w===0||w===6; }

export default function Love(){
    const { props } = usePage();
    const me = props.me ?? { nama:'—', region:'' };
    const assigned = props.assigned ?? null;
    const settings = props.settings ?? { loveMax:4, loveQuota:{ sisa:4, max:4, approved:0, pending:0 } };
    const claims0 = props.claims ?? [];
    const approvers = props.approvers ?? [];
    const flash = props.flash;
    const errors = props.errors;

    const max = settings.loveMax ?? 4;
    const quota = settings.loveQuota ?? { sisa: max, max, approved:0, pending:0 };
    const sisa = quota.sisa ?? max;

    const [jenis,setJenis]=useState('lupa_absen');
    const [tgl,setTgl]=useState(todayISO());
    const [jam,setJam]=useState('07:35');
    const [alasan,setAlasan]=useState('');
    const [qApprover,setQApprover]=useState('');
    const [approverId,setApproverId]=useState(approvers[0]?.id ?? null);
    const [openApprover,setOpenApprover]=useState(false);
    const [toast,setToast]=useState(null);
    const show=(msg,ok=true)=>{ setToast({msg,ok}); setTimeout(()=>setToast(null),2500); };
    useEffect(()=>{ if(flash?.success) show(flash.success,true); if(flash?.error) show(flash.error,false); },[flash?.success, flash?.error]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(()=>{ if(errors && Object.keys(errors).length) show(Object.values(errors).flat().join(' '),false); },[errors]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(()=>{ if(approvers.length && approverId==null) setApproverId(approvers[0].id); },[approvers,approverId]);

    const filteredApprovers=useMemo(()=>{
        const q=qApprover.trim().toLowerCase();
        if(!q) return approvers;
        return approvers.filter((a)=> String(a.nama).toLowerCase().includes(q) || String(a.nip).includes(q));
    },[approvers,qApprover]);
    const selectedApprover=useMemo(()=> approvers.find((a)=>a.id===approverId)||null,[approvers,approverId]);

    const handleClaim=()=>{
        if(!alasan.trim()){ show('Isi alasan',false); return; }
        if(!approverId){ show('Pilih atasan untuk di-ACC',false); return; }
        if(sisa<=0){ show('Sisa Toleransi 0 — reset bulan depan',false); return; }
        if(!tgl){ show('Pilih tanggal',false); return; }
        if(tgl>todayISO()){ show('Tanggal tidak boleh melebihi hari ini',false); return; }
        if(isWeekend(tgl)){ show('Tanggal tidak boleh weekend',false); return; }
        if(!jam || !/^\d{2}:\d{2}$/.test(jam)){ show('Jam wajib format HH:MM',false); return; }
        router.post('/karyawan/love',{ jenis, tgl, jam, alasan: alasan.trim(), approver_id: approverId },{
            preserveScroll:true,
            onSuccess:()=> setAlasan(''),
            onError:(e)=> show(Object.values(e).flat().join(' ') || 'Gagal',false),
        });
    };

    if(!assigned){
        return (<KaryawanLayout><div className="bg-white rounded-2xl p-6 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-sm text-[#64748B]">Titik belum di-assign — hubungi Admin (1 karyawan = 1 titik)</p></div></KaryawanLayout>);
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
                        {Array.from({length:max},(_,i)=>(<span key={i} className={`flex-1 h-2.5 rounded-full ${i < sisa ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3">Pakai 1 toleransi untuk <span className="font-medium text-[#0F172A]">lupa absen datang / lupa absen pulang</span> di titik {assigned.site.nama_lokasi} (bulan sama, cukup alasan) → persetujuan 1 level Admin • kuota {max}/bulan</p>
                    {sisa===0 && <p className="text-xs font-medium text-[#EF4444] mt-2">Sisa 0 — pengajuan berikutnya tidak bisa di-approve</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h3 className="font-medium text-sm text-[#0F172A]">Ajukan Toleransi</h3>
                    <p className="text-xs text-[#94A3B8] mt-1">Pilih jenis → isi alasan → pilih atasan</p>
                    <div className="mt-4 flex gap-2 flex-wrap">
                        {LOVE_JENIS.map((j)=>(
                            <button key={j.value} type="button" onClick={()=>setJenis(j.value)} className={`rounded-xl px-3 py-2 text-xs font-semibold border ${jenis===j.value ? 'bg-[#0F172A] text-white border-[#0F172A]' : 'bg-white text-[#334155] border-[#E2E8F0] hover:bg-[#F8FAFC]'}`}>{j.label}</button>
                        ))}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-[#334155]">{jenis==='lupa_absen' ? 'Tanggal lupa absen datang' : 'Tanggal lupa absen pulang'}</label>
                            <input type="date" value={tgl} max={todayISO()} onChange={(e)=>setTgl(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Jam {jenis==='lupa_pulang' ? 'pulang' : 'datang'}</label>
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
                                        {filteredApprovers.length===0 ? (
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
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e)=>setAlasan(e.target.value)} placeholder={jenis==='lupa_absen' ? 'Contoh: Lupa absen datang karena HP lowbat' : 'Contoh: Lupa absen pulang — rapat di lapangan'} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20"></textarea>
                        </div>
                        <button type="button" onClick={handleClaim} disabled={!alasan.trim() || !approverId || sisa<=0} title={!alasan.trim() ? 'Isi alasan dulu' : !approverId ? 'Pilih atasan' : sisa<=0 ? 'Sisa Toleransi 0 — reset 1 bulan depan' : ''} className="w-full rounded-xl py-3 text-sm font-semibold bg-[#FCB833] text-[#0F172A] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8]">Gunakan 1 toleransi — Kirim ke {selectedApprover ? selectedApprover.nama : 'atasan'}</button>
                    </div>
                    {toast && <p className={`text-xs text-center rounded-xl py-2 mt-3 ${toast.ok ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{toast.msg}</p>}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat Toleransi saya</h3>
                        <span className="text-xs text-[#94A3B8]">Bulan ini • {claims0.length}</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {claims0.length===0 ? <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada Toleransi — ajukan saat lupa absen datang / pulang</p> : claims0.map((c)=>(
                            <div key={c.id} className="px-5 py-3.5 flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-[#0F172A]">{loveJenisLabel(c.jenis)} • {c.tgl} • {c.jam} {c.approver_scope ? `• ${c.approver_scope}` : ''}</p>
                                    <p className="text-xs text-[#64748B] mt-0.5">{c.alasan} {c.note ? `• ${c.note}` : ''}</p>
                                    <p className="text-xs text-[#94A3B8]">Atasan: {c.approver_nama || '—'} {c.approver_nip ? `• ${c.approver_nip}` : ''} {c.approver_scope ? `• ${c.approver_scope}` : ''}</p>
                                </div>
                                <span className={`text-xs font-medium px-2.5 py-1 rounded-full shrink-0 ${c.status==='approved' ? 'bg-[#ECFDF5] text-[#065F46]' : c.status==='pending' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
