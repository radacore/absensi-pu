import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function Pengumuman(){
    const { props } = usePage();
    const me = props.me ?? { nama:'—', region:'' };
    const raw = props.list ?? [];
    const readIds = new Set(props.readIds ?? []);
    const [tab,setTab]=useState('Semua');
    const [toast,setToast]=useState(null);
    const flash = props.flash;
    useEffect(()=>{ if(flash?.success) { setToast(flash.success); setTimeout(()=>setToast(null),2000); } },[flash?.success]);

    const visible = useMemo(()=>{
        let list = raw;
        if(tab==='Pusat') list = list.filter((p)=>p.scope==='Global');
        if(tab==='Wilayah') list = list.filter((p)=>p.scope==='Wilayah');
        return [...list].sort((a,b)=>(b.pin?1:0)-(a.pin?1:0));
    },[raw,tab]);

    const markRead=(id)=>{
        router.post(`/karyawan/pengumuman/${id}/read`, {}, { preserveScroll:true, onError:()=>{} });
    };
    const markAll=()=>{
        router.post('/karyawan/pengumuman/read-all', {}, { preserveScroll:true, onError:()=>{} });
    };
    const unreadCount = visible.filter((p)=> !readIds.has(p.id)).length;

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Pengumuman</h2>
                        <p className="text-sm text-[#64748B]">Dari pusat & wilayah {me.region || ''} {unreadCount>0 ? `• ${unreadCount} baru` : '• semua dibaca'}</p>
                    </div>
                    <button type="button" onClick={markAll} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-full shrink-0">Tandai semua dibaca</button>
                </div>
                <div className="flex gap-2">
                    {['Semua','Pusat','Wilayah'].map((t)=>(
                        <button key={t} type="button" onClick={()=>setTab(t)} className={`text-xs font-medium px-3 py-1.5 rounded-full ${tab===t ? 'bg-[#0F172A] text-white' : 'bg-white text-[#64748B] shadow-sm'}`}>{t}</button>
                    ))}
                    <span className="ml-auto text-xs text-[#94A3B8] self-center">{visible.length} pengumuman</span>
                </div>
                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}
                {visible.length===0 ? (
                    <p className="text-sm text-[#94A3B8] bg-white rounded-2xl p-6 text-center">Belum ada pengumuman{me ? ` untuk ${me.region}` : ''}</p>
                ) : (
                    <div className="space-y-3">
                        {visible.map((p)=>{
                            const unread = !readIds.has(p.id);
                            return (
                                <div key={p.id} className={`bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] ${unread ? 'ring-1 ring-[#E0F2FE]' : ''}`}>
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-medium text-sm leading-snug text-[#0F172A]">{p.pin ? '📌 ' : ''}{p.judul}</h3>
                                        {unread && <span className="mt-1 w-2 h-2 rounded-full bg-[#1E3A8A] shrink-0"></span>}
                                    </div>
                                    <p className="text-xs text-[#94A3B8] mt-1">{p.scope==='Global' ? `Pusat • ${p.tgl}` : `${p.region} • ${p.tgl}`} {p.pin ? '• Disematkan' : ''} {unread ? '• Belum dibaca' : '• Sudah dibaca'}</p>
                                    <p className="text-sm text-[#475569] leading-relaxed mt-3">{p.konten}</p>
                                    <div className="mt-3 flex gap-2">
                                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${p.scope==='Global' ? 'bg-[#0F172A] text-white' : 'bg-[#F1F5F9] text-[#334155]'}`}>{p.scope==='Global' ? 'Kantor Pusat' : p.region}</span>
                                        {unread && <button type="button" onClick={()=>markRead(p.id)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2.5 py-1 rounded-full">Tandai dibaca</button>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </KaryawanLayout>
    );
}
