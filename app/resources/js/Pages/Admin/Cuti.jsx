import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function getBase(url){ if(url.startsWith('/super-admin')) return '/super-admin'; if(url.startsWith('/admin')) return '/admin'; if(url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

export default function CutiAdmin(){
    const { url, props } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const regionsData = props.regions ?? [];
    const list = props.list ?? [];
    const flash = props.flash;
    const [q,setQ]=useState('');
    const ownRegion = regionsData[0]?.name || 'Kab. Gowa';
    const wilayahNames = useMemo(()=> regionsData.map((r)=>r.name),[regionsData]);
    const allWilayahOpts = ['Semua', ...wilayahNames];
    const [wilayah,setWilayah]=useState(isWilayah ? ownRegion : 'Semua');
    const [siteFilter,setSiteFilter]=useState('Semua');
    const [status,setStatus]=useState('Semua');
    const [toast,setToast]=useState(null);
    useEffect(()=>{ if(flash?.success) { setToast(flash.success); setTimeout(()=>setToast(null),2000); } if(flash?.error){ setToast(flash.error); setTimeout(()=>setToast(null),2000); } },[flash?.success, flash?.error]);
    useEffect(()=>{ if(isWilayah) setWilayah(ownRegion); },[ownRegion, isWilayah]);

    const sitesForWilayah = useMemo(()=>{
        if(isWilayah){
            const r = regionsData.find((x)=>x.name===ownRegion);
            return r ? r.locations : [];
        }
        if(wilayah==='Semua') return [];
        const r = regionsData.find((x)=>x.name===wilayah);
        return r ? r.locations : [];
    },[regionsData,wilayah,isWilayah,ownRegion]);

    const handleApprove=(id)=>{
        router.put(`${base}/cuti/${id}/approve`, {}, { preserveScroll:true, onError:(e)=>{ setToast(Object.values(e).flat().join(' ')||'Gagal'); setTimeout(()=>setToast(null),2000); }});
    };
    const handleReject=(id)=>{
        const note = prompt('Catatan penolakan (opsional):') || '';
        router.put(`${base}/cuti/${id}/reject`, { note }, { preserveScroll:true, onError:(e)=>{ setToast(Object.values(e).flat().join(' ')||'Gagal'); setTimeout(()=>setToast(null),2000); }});
    };
    const handleDelete=(id, nama)=>{
        if(!confirm(`Hapus cuti ${nama}?`)) return;
        router.delete(`${base}/cuti/${id}`, { preserveScroll:true });
    };

    const baseList = isWilayah ? list.filter((c)=>c.wilayah===ownRegion) : list;
    const filtered = useMemo(()=> baseList.filter((c)=>{
        if(!isWilayah && wilayah!=='Semua' && c.wilayah!==wilayah) return false;
        if(siteFilter!=='Semua' && String(c.office_location_id)!==String(siteFilter)) return false;
        if(status!=='Semua' && c.status!==status) return false;
        if(q && !c.nama.toLowerCase().includes(q.toLowerCase()) && !c.email.toLowerCase().includes(q.toLowerCase()) && !c.jenis.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }),[q,wilayah,siteFilter,status,baseList,isWilayah]);

    const counts={ pending:list.filter((c)=>c.status==='Menunggu').length, approved:list.filter((c)=>c.status==='Disetujui').length, rejected:list.filter((c)=>c.status==='Ditolak').length };
    const siteById=(id)=>{
        for(const r of regionsData){ const s=r.locations.find((x)=>String(x.id)===String(id)); if(s) return { site:s, region:r }; }
        return null;
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Cuti — ${ownRegion}` : 'Cuti — Berjenjang'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `Approve own region level sesuai kewenangan • ${ownRegion} • 1 karyawan = 1 titik` : 'Atasan → Admin Wilayah → Kantor Pusat • 3 level, reject terminal • 1 karyawan = 1 titik'}</p>
                    </div>
                    <span className="hidden lg:inline-flex bg-[#F8FAFC] border border-[#E2E8F0] text-xs font-medium px-3 py-1.5 rounded-full text-[#64748B]">{filtered.length} hasil • {counts.pending} menunggu</span>
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.pending}</p><p className="text-xs text-[#64748B]">Menunggu</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.approved}</p><p className="text-xs text-[#64748B]">Disetujui</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#10B981]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.rejected}</p><p className="text-xs text-[#64748B]">Ditolak</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#EF4444]"></span></div>
                </div>
                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex flex-wrap gap-2 items-center">
                    {isWilayah ? (
                        <span className="rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm font-medium text-[#0F172A]">{ownRegion}</span>
                    ) : (
                        <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            {allWilayahOpts.map((w)=><option key={w} value={w}>{w}</option>)}
                        </select>
                    )}
                    <select value={siteFilter} onChange={(e)=>setSiteFilter(e.target.value)} className="rounded-xl bg-[#FFF7E6] border border-[#FCB833]/20 px-3 py-2 text-sm outline-none min-w-[160px]">
                        <option value="Semua">Semua titik</option>
                        {sitesForWilayah.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                        {!isWilayah && wilayah==='Semua' && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                    </select>
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="Menunggu">Menunggu</option>
                        <option value="Disetujui">Disetujui</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari nama / email / jenis..." className="flex-1 min-w-[180px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} dari {list.length}</span>
                </div>
                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Titik Proyek</th><th className="text-left px-4 py-3">Jenis</th><th className="text-left px-4 py-3">Tanggal</th><th className="text-left px-4 py-3">Level</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((c)=>{
                                    const hit=siteById(c.office_location_id);
                                    return (
                                        <tr key={c.id} className="hover:bg-[#F8FAFC]/50">
                                            <td className="px-4 py-3"><p className="font-medium text-[#0F172A]">{c.nama}</p><p className="text-xs text-[#64748B]">{c.email}</p><p className="text-xs text-[#94A3B8] line-clamp-1">{c.alasan}</p><span className="text-xs bg-[#F1F5F9] px-2 py-0.5 rounded-full">{c.wilayah}</span></td>
                                            <td className="px-4 py-3 text-xs">{hit ? <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className="bg-[#EFF6FF] text-[#1E3A8A] px-2 py-1 rounded-full font-medium hover:bg-[#DBEAFE]">{hit.site.nama_lokasi}</Link> : <span className="bg-[#F1F5F9] text-[#64748B] px-2 py-1 rounded-full">—</span>}</td>
                                            <td className="px-4 py-3 text-xs"><span className="bg-[#EFF6FF] text-[#1E3A8A] px-2 py-1 rounded-full font-medium">{c.jenis}</span></td>
                                            <td className="px-4 py-3 text-xs text-[#334155]">{c.tgl}</td>
                                            <td className="px-4 py-3"><div className="flex gap-1">{[1,2,3].map((l)=>(<span key={l} className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium ${l < c.level ? 'bg-[#0F172A] text-white' : l===c.level ? 'bg-[#FCB833] text-[#0F172A]' : 'bg-[#F1F5F9] text-[#94A3B8]'}`}>{l}</span>))}</div></td>
                                            <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${c.status==='Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : c.status==='Menunggu' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span></td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1 justify-end flex-wrap">
                                                    {c.status==='Menunggu' ? (
                                                        <>
                                                            <button type="button" onClick={()=>handleApprove(c.id)} className="text-xs font-medium bg-[#0F172A] text-white px-2.5 py-1.5 rounded-lg">Setujui</button>
                                                            <button type="button" onClick={()=>handleReject(c.id)} className="text-xs font-medium bg-[#F1F5F9] text-[#64748B] px-2.5 py-1.5 rounded-lg">Tolak</button>
                                                        </>
                                                    ) : <span className="text-xs text-[#94A3B8]">—</span>}
                                                    <Link href={`${base}/cuti/${c.id}`} className="text-xs font-medium bg-white border px-2.5 py-1.5 rounded-lg">Detail</Link>
                                                    <button type="button" onClick={()=>handleDelete(c.id, c.nama)} className="text-xs font-medium bg-[#FEF2F2] text-[#991B1B] px-2.5 py-1.5 rounded-lg">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada data untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{isWilayah ? `Admin Wilayah: review & approve hanya ${ownRegion} • level sesuai kewenangan` : 'Super Admin approve semua level • Admin Wilayah approve own region level sesuai kewenangan'} • 1 karyawan = 1 titik</span>
                        <span>Karyawan mengajukan → Admin menyetujui/menolak/menghapus</span>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
