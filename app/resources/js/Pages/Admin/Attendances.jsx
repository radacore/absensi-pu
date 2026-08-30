import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, loadAttendances, saveAttendances, getBase, OWN_REGION, WILAYAH_LIST as wilayahList, getSitesForWilayah, getValidSiteIds, siteById } from './_shared';

const statusLabel = { on_time: 'Tepat waktu', late: 'Terlambat', excused_love: 'Love', early_leave: 'Pulang awal' };
const statusTone = { on_time: 'bg-[#ECFDF5] text-[#065F46]', late: 'bg-[#FFF7E6] text-[#92400E]', excused_love: 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30', early_leave: 'bg-[#FEF2F2] text-[#991B1B]' };

export default function Attendances() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees] = useState(() => loadEmployees());
    const [attendances, setAttendances] = useState(() => loadAttendances());
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [siteFilter, setSiteFilter] = useState('Semua');
    const [status, setStatus] = useState('Semua');
    const [tgl, setTgl] = useState(() => new Date().toISOString().slice(0,10));
    const [detail, setDetail] = useState(null);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setAttendances(loadAttendances()); };
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

    const baseList = useMemo(() => {
        let list = attendances.filter((r) => r.tgl === tgl);
        if (list.length === 0) list = attendances;
        return isWilayah ? list.filter((r) => r.wilayah === OWN_REGION) : list;
    }, [attendances, tgl, isWilayah]);
    const filtered = useMemo(() => baseList.filter((r) => {
        if (!isWilayah && wilayah !== 'Semua' && r.wilayah !== wilayah) return false;
        if (isWilayah && wilayah !== OWN_REGION) return false;
        if (siteFilter !== 'Semua') {
            if (String(r.office_location_id) !== String(siteFilter)) return false;
        }
        if (status !== 'Semua' && r.status !== status) return false;
        if (q && !r.nama.toLowerCase().includes(q.toLowerCase()) && !r.email.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [q, wilayah, siteFilter, status, baseList, isWilayah]);

    const stats = { hadir: filtered.length, late: filtered.filter((r)=>r.status==='late').length, love: filtered.filter((r)=>r.love).length };

    const handleExport = () => { setToast('Ekspor CSV'); setTimeout(()=>setToast(null),2000); };
    const handleDelete = (id) => {
        if (!confirm('Hapus absensi ini?')) return;
        const next = attendances.filter((x)=>x.id!==id); setAttendances(next); saveAttendances(next); setDetail(null);
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Absensi — ${OWN_REGION}` : 'Absensi'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `Hanya own region • 1 karyawan = 1 titik` : 'Filter wilayah → titik proyek → status • 1 karyawan = 1 titik • di luar titik assigned ditolak 422'}</p>
                        <p className="text-xs text-[#94A3B8] mt-1">Radius per titik 50–1000 m — absen valid hanya di titik penugasan dalam radius</p>
                    </div>
                    <button type="button" onClick={handleExport} className="bg-white border border-[#E2E8F0] rounded-xl px-4 py-2.5 text-sm font-medium text-[#334155] shrink-0">⬇ Export CSV{isWilayah ? ` ${OWN_REGION}` : ''}</button>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.hadir}</p><p className="text-xs text-[#64748B]">Hadir (filter)</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#10B981]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.late}</p><p className="text-xs text-[#64748B]">Terlambat</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.love}</p><p className="text-xs text-[#64748B]">Pakai Love</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                </div>

                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex flex-wrap gap-2 items-center">
                    <input type="date" value={tgl} onChange={(e)=>setTgl(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    {isWilayah ? (
                        <span className="rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm font-medium text-[#0F172A]">{OWN_REGION}</span>
                    ) : (
                        <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            {wilayahList.map((w)=><option key={w} value={w}>{w}</option>)}
                        </select>
                    )}
                    <select value={siteFilter} onChange={(e)=>setSiteFilter(e.target.value)} className="rounded-xl bg-[#FFF7E6] border border-[#FCB833]/20 px-3 py-2 text-sm outline-none min-w-[170px]">
                        <option value="Semua">Semua titik</option>
                        {sitesForWilayah.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                        {!isWilayah && wilayah==='Semua' && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                    </select>
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="on_time">Tepat waktu</option>
                        <option value="late">Terlambat</option>
                        <option value="excused_love">Love (excused)</option>
                        <option value="early_leave">Pulang awal</option>
                    </select>
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari nama / email..." className="flex-1 min-w-[160px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} hasil • {tgl}</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Waktu</th><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Titik Proyek</th><th className="text-left px-4 py-3">Jarak</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => {
                                    const hit = siteById(r.office_location_id, regionsData);
                                    const inRadius = hit ? r.jarak <= hit.site.radius : false;
                                    return (
                                        <tr key={r.id} className="hover:bg-[#F8FAFC]/50">
                                            <td className="px-4 py-3"><div className="flex items-center gap-3"><img src={r.foto} alt={r.nama} className="w-8 h-8 rounded-full object-cover" /><div><p className="font-medium text-[#0F172A]">{r.nama}</p><p className="text-xs text-[#64748B]">{r.email}</p></div></div></td>
                                            <td className="px-4 py-3 text-xs"><span className="font-medium text-[#0F172A]">{r.datang}</span> → <span className="text-[#64748B]">{r.pulang ? r.pulang : '—'}</span></td>
                                            <td className="px-4 py-3 text-xs"><span className="bg-[#F1F5F9] px-2 py-1 rounded-full">{r.wilayah}</span></td>
                                            <td className="px-4 py-3 text-xs">
                                                {hit ? (
                                                    <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${inRadius ? 'bg-[#EFF6FF] text-[#1E3A8A] hover:bg-[#DBEAFE]' : 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]'}`} title={`${hit.site.nama_lokasi} • ${hit.site.radius}m`}>
                                                        {hit.site.nama_lokasi} • {hit.site.radius}m
                                                    </Link>
                                                ) : (
                                                    <span className="inline-flex bg-[#F1F5F9] text-[#64748B] px-2.5 py-1 rounded-full text-xs">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs"><span className={`px-2 py-1 rounded-full font-medium ${hit ? (inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]') : 'bg-[#F1F5F9] text-[#64748B]'}`}>{r.jarak} m {hit ? (inRadius ? '• Dalam' : '• Di luar') : ''}</span></td>
                                            <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${statusTone[r.status]}`}>{statusLabel[r.status]}{r.love ? ` • ${r.love}` : ''}</span></td>
                                            <td className="px-4 py-3 text-right"><button type="button" onClick={()=>setDetail(r)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Detail</button></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada data untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{isWilayah ? `Admin Wilayah: hanya ${OWN_REGION}` : 'Super Admin lihat semua • Admin Wilayah own region saja'}</span>
                        <span>1 karyawan = 1 titik • Jam 07:30–16:00 WITA</span>
                    </div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                {detail && (() => {
                    const hit = siteById(detail.office_location_id, regionsData);
                    const inRadius = hit ? detail.jarak <= hit.site.radius : false;
                    return (
                        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={()=>setDetail(null)}>
                            <div className="bg-white rounded-2xl w-full max-w-[520px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e)=>e.stopPropagation()}>
                                <div className="px-5 py-4 flex items-center justify-between border-b sticky top-0 bg-white rounded-t-2xl">
                                    <div>
                                        <h3 className="font-semibold text-[#0F172A]">{detail.nama}</h3>
                                        <p className="text-xs text-[#64748B]">{detail.wilayah} • {detail.kantor} • {detail.jarak} m {hit ? `• ${hit.site.nama_lokasi} ${hit.site.radius}m • ${inRadius ? 'Dalam' : 'Di luar'}` : ''} • {statusLabel[detail.status]}</p>
                                    </div>
                                    <button type="button" onClick={()=>setDetail(null)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                                </div>
                                <div className="p-5 space-y-4">
                                    <img src={detail.selfie} alt="selfie" className="w-full h-[280px] object-cover rounded-xl" />
                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu datang</p><p className="font-medium text-[#0F172A]">{detail.datang} WITA</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu pulang</p><p className="font-medium text-[#0F172A]">{detail.pulang || '— belum pulang'}</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Koordinat absen</p><p className="font-mono text-xs text-[#0F172A]">{detail.lat.toFixed(4)}, {detail.lng.toFixed(4)}</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Jarak ke titik assigned</p><p className={`font-medium ${hit && inRadius ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>{detail.jarak} m {hit ? `/${hit.site.radius}m • ${inRadius ? 'Dalam' : 'Di luar'}` : ''}</p></div>
                                    </div>
                                    {hit && (
                                        <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className="flex items-center justify-between bg-[#EFF6FF] rounded-xl p-3 hover:bg-[#DBEAFE]">
                                            <span><p className="text-sm font-medium text-[#1E3A8A]">{hit.site.nama_lokasi}</p><p className="text-xs text-[#64748B]">{hit.region.name} • {hit.site.lat.toFixed(4)}, {hit.site.lng.toFixed(4)} • {hit.site.radius}m</p></span>
                                            <span className="text-xs font-semibold text-[#1E3A8A]">Lihat titik →</span>
                                        </Link>
                                    )}
                                    <div className="bg-[#FFF7E6] rounded-xl p-3 flex items-center justify-between">
                                        <p className="text-xs text-[#92400E]">Status: {statusLabel[detail.status]} {detail.love ? `• Love ${detail.love}` : ''}</p>
                                        <span className={`text-xs font-medium px-2 py-1 rounded-full ${statusTone[detail.status]}`}>{statusLabel[detail.status]}</span>
                                    </div>
                                    <div className="flex gap-2">
                                        <button type="button" onClick={()=>handleDelete(detail.id)} className="flex-1 bg-[#FEF2F2] text-[#991B1B] rounded-xl py-2.5 text-sm font-semibold">Hapus</button>
                                        <button type="button" onClick={()=>setDetail(null)} className="flex-1 bg-[#0F172A] text-white rounded-xl py-2.5 text-sm font-semibold">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })()}
            </div>
        </AdminLayout>
    );
}
