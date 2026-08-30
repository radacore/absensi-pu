import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { getBase, loadRegions, OWN_REGION, WILAYAH_LIST as wilayahList, getSitesForWilayah, getValidSiteIds, siteById, loadLove, saveLove, loadSettings, LOVE_JENIS, loveJenisLabel } from './_shared';

export default function LoveAdmin() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [claims, setClaims] = useState(() => loadLove());
    const [settings, setSettings] = useState(() => loadSettings());
    const [rejectNote, setRejectNote] = useState({ id: null, text: '' });
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [siteFilter, setSiteFilter] = useState('Semua');
    const [status, setStatus] = useState('Semua');
    const [jenisFilter, setJenisFilter] = useState('Semua');
    const [approverQ, setApproverQ] = useState('');
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [toast, setToast] = useState(null);
    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setClaims(loadLove()); setSettings(loadSettings()); };
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = () => sync();
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
    }, []);
    useEffect(() => {
        if (siteFilter === 'Semua') return;
        if (!getValidSiteIds(regionsData, wilayah, isWilayah).has(String(siteFilter))) setSiteFilter('Semua');
    }, [wilayah, siteFilter, regionsData, isWilayah]);
    const sitesForWilayah = useMemo(() => getSitesForWilayah(regionsData, wilayah, isWilayah), [regionsData, wilayah, isWilayah]);

    const baseClaims = isWilayah ? claims.filter((c) => c.wilayah === OWN_REGION) : claims;
    const filtered = useMemo(() => baseClaims.filter((c) => {
        if (!isWilayah && wilayah !== 'Semua' && c.wilayah !== wilayah) return false;
        if (isWilayah && wilayah !== OWN_REGION) return false;
        if (siteFilter !== 'Semua') {
            if (String(c.office_location_id) !== String(siteFilter)) return false;
        }
        if (status !== 'Semua' && c.status !== status) return false;
        if (jenisFilter !== 'Semua' && (c.jenis || 'terlambat') !== jenisFilter) return false;
        if (approverQ && !String(c.approver_nama||'').toLowerCase().includes(approverQ.toLowerCase()) && !String(c.approver_nip||'').includes(approverQ)) return false;
        if (q && !c.nama.toLowerCase().includes(q.toLowerCase()) && !c.kantor?.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [baseClaims, q, wilayah, siteFilter, status, jenisFilter, approverQ, isWilayah]);

    const counts = { pending: claims.filter((c)=>c.status==='pending').length, approved: claims.filter((c)=>c.status==='approved').length };
    const loveMax = settings.loveMax ?? 4;

    const handle = (id, nextStatus) => {
        if (nextStatus === 'rejected' && !rejectNote.text.trim()) { setToast('Isi alasan reject'); setTimeout(()=>setToast(null),2000); return; }
        const next = claims.map((c) => (c.id === id ? { ...c, status: nextStatus, note: nextStatus==='rejected' ? rejectNote.text.trim() : c.note } : c));
        setClaims(next); saveLove(next);
        setRejectNote({ id: null, text: '' });
        setToast(nextStatus === 'approved' ? 'Disetujui' : 'Ditolak'); setTimeout(()=>setToast(null),2000);
    };
    const handleDelete = (id) => {
        if (!confirm('Hapus Love claim ini?')) return;
        const next = claims.filter((c) => c.id !== id); setClaims(next); saveLove(next);
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Love Claims — ${OWN_REGION}` : `Love Claims — ${loveMax} Hati / bulan`}</h1>
                        <p className="text-sm text-[#64748B]">Hanya <span className="font-medium text-[#0F172A]">terlambat / lupa absen / lupa pulang bulan sama</span> & dalam radius <span className="font-medium text-[#0F172A]">titik penugasan</span> • Persetujuan 1 level Admin • 1 Love = 1 pengajuan • max {loveMax}/bulan</p>
                        <p className="text-xs text-[#94A3B8] mt-1">Terlambat cek jarak ≤ radius; lupa absen jam bebas 00–23:59 • 1 karyawan = 1 titik • Reset tgl 1 pukul 00:00 WITA</p>
                    </div>
                    <span className="shrink-0 bg-[#FFF7E6] border border-[#FCB833]/30 text-[#92400E] text-xs font-medium px-3 py-1.5 rounded-full">{counts.pending} pending • {counts.approved} approved bulan ini • max {loveMax}</span>
                </div>
                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.pending}</p><p className="text-xs text-[#64748B]">Pending</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{claims.filter(c=>c.status==='approved').length}</p><p className="text-xs text-[#64748B]">Disetujui bulan ini</p></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{loveMax}</p><p className="text-xs text-[#64748B]">Love max (global)</p></div>
                </div>

                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex flex-wrap gap-2 items-center">
                    {isWilayah ? (
                        <span className="rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm font-medium text-[#0F172A]">{OWN_REGION}</span>
                    ) : (
                        <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            {wilayahList.map((w)=><option key={w} value={w}>{w}</option>)}
                        </select>
                    )}
                    <select value={siteFilter} onChange={(e)=>setSiteFilter(e.target.value)} className="rounded-xl bg-[#FFF7E6] border border-[#FCB833]/20 px-3 py-2 text-sm outline-none min-w-[160px]">
                        <option value="Semua">Semua titik</option>
                        {sitesForWilayah.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                        {!isWilayah && wilayah==='Semua' && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                    </select>
                    <select value={jenisFilter} onChange={(e)=>setJenisFilter(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua jenis</option>
                        {LOVE_JENIS.map((j)=><option key={j.value} value={j.value}>{j.label}</option>)}
                    </select>
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input value={approverQ} onChange={(e)=>setApproverQ(e.target.value)} placeholder="Cari atasan nama/NIP..." className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10 min-w-[160px]" />
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari karyawan / kantor..." className="flex-1 min-w-[160px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} hasil</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Titik Proyek</th><th className="text-left px-4 py-3">Jenis • Jam</th><th className="text-left px-4 py-3">Atasan (ACC)</th><th className="text-left px-4 py-3">Alasan</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((c) => {
                                    const hit = siteById(c.office_location_id, regionsData);
                                    const isLate = (c.jenis || 'terlambat') === 'terlambat';
                                    const inRadius = isLate && c.office_location_id != null && c.radius != null ? c.jarak <= c.radius : true;
                                    return (
                                        <tr key={c.id} className="hover:bg-[#F8FAFC]/50">
                                            <td className="px-4 py-3"><p className="font-medium text-[#0F172A]">{c.nama}</p><p className="text-xs text-[#64748B]">{c.wilayah} • {c.kantor}</p></td>
                                            <td className="px-4 py-3 text-xs">{hit ? <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className={`px-2 py-1 rounded-full font-medium ${inRadius ? 'bg-[#EFF6FF] text-[#1E3A8A]' : 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]'} hover:opacity-80`}>{hit.site.nama_lokasi} • {hit.site.radius}m</Link> : <span className="bg-[#F1F5F9] text-[#64748B] px-2 py-1 rounded-full">—</span>}</td>
                                            <td className="px-4 py-3 text-xs"><span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium mr-1 ${c.jenis==='lupa_absen' ? 'bg-[#EFF6FF] text-[#1E3A8A]' : c.jenis==='lupa_pulang' ? 'bg-[#F5F3FF] text-[#6D28D9]' : 'bg-[#FFF7E6] text-[#92400E]'}`}>{loveJenisLabel(c.jenis)}</span><span title={hit && isLate ? `${c.jarak} m / ${c.radius} m • ${hit.site.nama_lokasi} • ${inRadius ? 'Dalam' : 'Di luar'}` : ''} className={isLate && !inRadius ? 'text-[#991B1B]' : 'text-[#0F172A]'}>{c.tgl || '—'} • {c.jam} {isLate && c.jarak != null ? `• ${c.jarak}m${c.radius ? `/${c.radius}m` : ''} ${hit ? (inRadius ? '• Dalam' : '• Di luar') : ''}` : ''}</span></td>
                                            <td className="px-4 py-3 text-xs"><p className="font-medium text-[#0F172A]">{c.approver_nama || '—'}</p><p className="text-xs text-[#64748B] font-mono">{c.approver_nip || ''}</p><p className="text-xs text-[#94A3B8]">{c.approver_scope || ''}</p></td>
                                            <td className="px-4 py-3 text-xs text-[#334155] max-w-[200px] truncate" title={c.alasan}>{c.alasan}</td>
                                            <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${c.status === 'pending' ? 'bg-[#FFF7E6] text-[#92400E]' : c.status === 'approved' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}{c.note ? ` • ${c.note}` : ''}</span></td>
                                            <td className="px-4 py-3 text-right">
                                                {c.status === 'pending' ? (
                                                    <div className="flex gap-1 justify-end flex-wrap">
                                                        <button type="button" onClick={() => handle(c.id, 'approved')} disabled={isLate && (!hit || !inRadius)} title={isLate && !hit ? 'Tidak ada titik' : isLate && !inRadius ? `${c.jarak} m / ${c.radius} m — di luar radius` : ''} className={`text-xs font-medium px-2.5 py-1.5 rounded-lg ${isLate && (!hit || !inRadius) ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#10B981] text-white hover:bg-[#059669]'}`}>Approve</button>
                                                        {rejectNote.id === c.id ? (
                                                            <span className="flex gap-1">
                                                                <input value={rejectNote.text} onChange={(e)=>setRejectNote({ id: c.id, text: e.target.value })} placeholder="Alasan reject..." className="w-28 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] px-2 py-1 text-xs outline-none" />
                                                                <button type="button" onClick={() => handle(c.id, 'rejected')} className="text-xs font-medium bg-[#EF4444] text-white px-2 py-1.5 rounded-lg">Kirim</button>
                                                                <button type="button" onClick={()=>setRejectNote({id:null,text:''})} className="text-xs px-1">✕</button>
                                                            </span>
                                                        ) : (
                                                            <button type="button" onClick={() => setRejectNote({ id: c.id, text: '' })} className="text-xs font-medium bg-[#F1F5F9] text-[#64748B] px-2.5 py-1.5 rounded-lg">Reject</button>
                                                        )}
                                                        <button type="button" onClick={()=>handleDelete(c.id)} className="text-xs bg-[#FEF2F2] text-[#991B1B] px-2 py-1.5 rounded-lg">Hapus</button>
                                                    </div>
                                                ) : (
                                                    <button type="button" onClick={()=>handleDelete(c.id)} className="text-xs bg-[#FEF2F2] text-[#991B1B] px-2 py-1.5 rounded-lg">Hapus</button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada claim untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">{isWilayah ? `Admin Wilayah: review & approve hanya ${OWN_REGION}` : 'Super Admin lihat semua • Admin Wilayah own region saja'}</div>
                </div>


            </div>
        </AdminLayout>
    );
}
