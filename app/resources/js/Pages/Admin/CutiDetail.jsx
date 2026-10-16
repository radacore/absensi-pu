import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { IconClose } from '@/Components/Icons';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function getBase(url){ if(url.startsWith('/super-admin')) return '/super-admin'; if(url.startsWith('/admin')) return '/admin'; if(url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

export default function CutiDetail(){
    const { props, url } = usePage();
    const base=getBase(url);
    const id = props.id || props.item?.id || 1;
    const item = props.item ?? null;
    const list = props.list ?? [];
    const fallback = useMemo(()=> list.find((c)=>String(c.id)===String(id)) || list[0] || item || null,[list,id,item]);
    const data = item || fallback;
    const confirm = useConfirm();
    const [showDoc,setShowDoc]=useState(false);
    const [note,setNote]=useState('');
    // Escape menutup pratinjau dokumen — jalur keyboard selain klik backdrop.
    // Ditaruh sebelum early-return di bawah supaya urutan hook tetap sama.
    useEffect(() => {
        if (!showDoc) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') setShowDoc(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [showDoc]);
    if(!data) return <AdminLayout><p className="text-sm text-[#64748B]">Cuti tidak ditemukan</p></AdminLayout>;
    const approve=async ()=>{
        const ok = await confirm({
            title: `Setujui cuti ${data.nama}?`,
            description: `${data.jenis} • ${data.tgl} • ${data.wilayah}. Level akan naik ke ${Math.min(3, data.level+1)}/3.`,
            confirmLabel: 'Ya, setujui',
            cancelLabel: 'Batal',
            tone: 'warning',
        });
        if (!ok) return;
        router.put(`${base}/cuti/${data.id}/approve`, {}, { preserveScroll:true });
    };
    const reject=()=>{
        router.put(`${base}/cuti/${data.id}/reject`, { note: note.trim() || undefined }, { preserveScroll:true });
    };
    return (
        <AdminLayout>
            <div className="space-y-5 max-w-[880px]">
                <Link href={`${base}/cuti`} className="inline-flex items-center gap-2 text-sm font-medium text-[#64748B] hover:text-[#0F172A]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M15 18l-6-6 6-6"/></svg>
                    Kembali ke daftar cuti
                </Link>
                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-full bg-[#F1F5F9] flex items-center justify-center text-sm font-semibold text-[#334155]">{String(data.nama||'—').split(' ').map((w)=>w[0]).join('').slice(0,2)}</div>
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">{data.nama}</h1>
                            <p className="text-sm text-[#64748B]">{data.wilayah} • {data.jenis} • {data.tgl}</p>
                            <p className="text-xs text-[#64748B]">{data.email} • titik {data.office_location_id}</p>
                            <span className={`inline-block mt-2 text-xs font-medium px-2.5 py-1 rounded-full ${data.status==='Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : data.status==='Menunggu' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{data.status} • Level {data.level}/3</span>
                            {props.kuota && (
                                <p className="text-xs text-[#64748B] mt-1.5">
                                    Kuota cuti tahunan {props.kuota.tahun}: terpakai <span className="font-medium text-[#334155]">{props.kuota.terpakai}</span> dari {props.kuota.max} hari kerja •
                                    <span className={`ml-1 font-semibold ${props.kuota.sisa > 0 ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>sisa {props.kuota.sisa} hari kerja</span>
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="mt-6 grid lg:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div>
                                <p className="text-xs font-medium text-[#64748B]">Alasan</p>
                                <p className="text-sm text-[#0F172A] mt-1 leading-relaxed">{data.alasan}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-[#64748B]">Dokumen pendukung</p>
                                {data.dokumen ? (
                                    <button type="button" onClick={()=>setShowDoc(true)} className="mt-2 inline-flex items-center gap-2 text-sm font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-2 rounded-xl hover:bg-[#DBEAFE]">{data.dokumen} — Lihat</button>
                                ) : <p className="text-sm text-[#64748B] mt-1">Tidak ada dokumen</p>}
                            </div>
                        </div>
                        <div className="bg-[#F8FAFC] rounded-2xl p-4">
                            <p className="text-xs font-medium text-[#64748B]">Timeline berjenjang</p>
                            <div className="mt-3 space-y-3">
                                {[
                                    { l:1, name:'Atasan Langsung' },
                                    { l:2, name:`Admin Wilayah ${data.wilayah}` },
                                    { l:3, name:'Kantor Pusat' },
                                ].map((s)=>{
                                    const state = data.status==='Ditolak' ? (data.level>=s.l ? 'approved' : 'pending') : data.level>=s.l ? 'approved' : data.level+1===s.l && data.status==='Menunggu' ? 'current' : 'pending';
                                    return (
                                        <div key={s.l} className="flex items-center gap-3">
                                            <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold ${state==='approved' ? 'bg-[#0F172A] text-white' : state==='current' ? 'bg-[#FCB833] text-[#0F172A]' : 'bg-white border border-[#E2E8F0] text-[#64748B]'}`}>{s.l}</span>
                                            <div className="flex-1">
                                                <p className="text-sm font-medium text-[#0F172A]">{s.name}</p>
                                                <p className="text-xs text-[#64748B]">{state==='approved' ? 'Disetujui' : state==='current' ? 'Menunggu Anda' : 'Menunggu'}</p>
                                            </div>
                                            {state==='approved' && <span className="w-2 h-2 rounded-full bg-[#10B981]"></span>}
                                        </div>
                                    );
                                })}
                            </div>
                            {data.note && data.status==='Ditolak' && <p className="text-xs text-[#991B1B] bg-[#FEF2F2] rounded-lg px-3 py-1.5 mt-3">Catatan: {data.note}</p>}
                        </div>
                    </div>
                    {data.status==='Menunggu' && (
                        <div className="mt-6 space-y-3">
                            <input value={note} onChange={(e)=>setNote(e.target.value)} placeholder="Catatan penolakan (opsional, untuk Tolak)" className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            <div className="flex gap-3">
                                <button type="button" onClick={reject} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Tolak</button>
                                <button type="button" onClick={approve} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold hover:bg-[#1E3A8A]">Setujui → Level {Math.min(3, data.level+1)}</button>
                            </div>
                        </div>
                    )}
                    {data.status!=='Menunggu' && (
                        <p className="mt-6 text-center text-sm font-medium text-[#64748B]">Status final: <span className={data.status==='Disetujui' ? 'text-[#065F46]' : 'text-[#991B1B]'}>{data.status}</span></p>
                    )}
                </div>
                {showDoc && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={()=>setShowDoc(false)}>
                        <div role="dialog" aria-modal="true" aria-label="Pratinjau dokumen pendukung" className="bg-white rounded-2xl max-w-[640px] w-full overflow-hidden" onClick={(e)=>e.stopPropagation()}>
                            <div className="px-5 py-4 flex items-center justify-between border-b">
                                <h3 className="font-medium text-sm text-[#0F172A]">{data.dokumen}</h3>
                                <button type="button" onClick={()=>setShowDoc(false)} aria-label="Tutup pratinjau dokumen" autoFocus className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center"><IconClose size={15} /></button>
                            </div>
                            <div className="p-6">
                                {data.dokumen ? (
                                    <div className="bg-[#F8FAFC] rounded-xl p-8 text-center">
                                        <p className="text-sm font-medium text-[#0F172A]">Dokumen pendukung</p>
                                        <p className="text-xs text-[#64748B] mt-1 break-all">{data.dokumen}</p>
                                        <div className="mt-4 h-32 bg-white rounded-xl border-2 border-dashed border-[#E2E8F0] flex items-center justify-center">
                                            <span className="text-xs text-[#64748B]">Pratinjau dokumen tidak tersedia di halaman ini</span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-center text-[#64748B]">Tidak ada dokumen pendukung.</p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
