import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, getBase, OWN_REGION } from './_shared';

const wilayahList = ['Semua','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];

function siteById(siteId, regionsData) {
    if (siteId == null) return null;
    for (const r of regionsData) {
        const s = r.locations.find((x) => x.id === Number(siteId));
        if (s) return { site: s, region: r };
    }
    return null;
}

const initial = [
    { id: 1, employee_id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 201, datang: '07:52', pulang: '16:12', status: 'late', love: 'pending', jarak: 42, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 2, nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', wilayah: 'Kota Makassar', kantor: 'Kantor Pusat', office_location_id: 101, datang: '07:38', pulang: '16:05', status: 'on_time', love: null, jarak: 38, lat: -5.1477, lng: 119.4327, foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 3, nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: 202, datang: '07:40', pulang: '16:05', status: 'on_time', love: null, jarak: 21, lat: -5.32, lng: 119.45, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 4, nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', office_location_id: null, datang: '07:48', pulang: '', status: 'late', love: null, jarak: 18, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 5, nama: 'Rudi Hartono', email: 'rudi@bbws-pj.go.id', wilayah: 'Kab. Bone', kantor: 'Kantor Bone', office_location_id: 401, datang: '07:55', pulang: '15:40', status: 'excused_love', love: 'approved', jarak: 28, lat: -4.54, lng: 120.33, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 6, nama: 'Dewi Lestari', email: 'dewi@bbws-pj.go.id', wilayah: 'Kab. Takalar', kantor: 'Kantor Takalar', office_location_id: 2001, datang: '08:05', pulang: '', status: 'late', love: null, jarak: 95, lat: -5.41, lng: 119.44, foto: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&h=400&fit=crop&crop=face&auto=format' },
];

const statusLabel = { on_time: 'Tepat waktu', late: 'Terlambat', excused_love: 'Love', early_leave: 'Pulang awal' };
const statusTone = { on_time: 'bg-[#ECFDF5] text-[#065F46]', late: 'bg-[#FFF7E6] text-[#92400E]', excused_love: 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30', early_leave: 'bg-[#FEF2F2] text-[#991B1B]' };

export default function Attendances() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees] = useState(() => loadEmployees());
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [siteFilter, setSiteFilter] = useState('Semua');
    const [status, setStatus] = useState('Semua');
    const [tgl, setTgl] = useState('2026-08-24');
    const [detail, setDetail] = useState(null);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        const sync = () => setRegionsData(loadRegions());
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);
    useEffect(() => {
        if (siteFilter === 'Semua' || siteFilter === '__null') return;
        // keep if site still belongs to new wilayah (or isWilayah own)
        const validIds = (() => {
            if (isWilayah) {
                const r = regionsData.find((x) => x.name === OWN_REGION);
                return new Set((r?.locations || []).map((s) => String(s.id)));
            }
            if (wilayah === 'Semua') return new Set();
            const r = regionsData.find((x) => x.name === wilayah);
            return new Set((r?.locations || []).map((s) => String(s.id)));
        })();
        if (!validIds.has(String(siteFilter))) setSiteFilter('Semua');
    }, [wilayah, siteFilter, regionsData, isWilayah]);

    const sitesForWilayah = useMemo(() => {
        if (isWilayah) {
            const r = regionsData.find((x) => x.name === OWN_REGION);
            return r ? r.locations : [];
        }
        if (wilayah === 'Semua') return [];
        const r = regionsData.find((x) => x.name === wilayah);
        return r ? r.locations : [];
    }, [regionsData, wilayah, isWilayah]);

    const baseList = isWilayah ? initial.filter((r) => r.wilayah === OWN_REGION) : initial;
    const filtered = useMemo(() => baseList.filter((r) => {
        if (!isWilayah && wilayah !== 'Semua' && r.wilayah !== wilayah) return false;
        if (isWilayah && wilayah !== OWN_REGION) return false;
        if (siteFilter !== 'Semua') {
            if (siteFilter === '__null') { if (r.office_location_id != null) return false; }
            else if (String(r.office_location_id) !== String(siteFilter)) return false;
        }
        if (status !== 'Semua' && r.status !== status) return false;
        if (q && !r.nama.toLowerCase().includes(q.toLowerCase()) && !r.email.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [q, wilayah, siteFilter, status, baseList, isWilayah]);

    const stats = { hadir: filtered.length, late: filtered.filter((r)=>r.status==='late').length, love: filtered.filter((r)=>r.love).length, tanpaTitik: filtered.filter((r)=>r.office_location_id==null).length };

    const handleExport = () => { setToast('Export CSV — frontend only (akan generate S3 /rekap/... )'); setTimeout(()=>setToast(null),2000); };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Absensi — ${OWN_REGION}` : 'Absensi'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `Hanya own region • tanpa titik = 422 ditolak • 1 karyawan = 1 titik` : 'Filter wilayah → titik proyek → status • 1 karyawan = 1 titik • di luar titik assigned ditolak 422'}</p>
                        <p className="text-xs text-[#94A3B8] mt-1">Radius per titik 50–1000m — absen valid hanya di titik assigned karyawan dalam radius titiknya.</p>
                    </div>
                    <button type="button" onClick={handleExport} className="bg-white border border-[#E2E8F0] rounded-xl px-4 py-2.5 text-sm font-medium text-[#334155] shrink-0">⬇ Export CSV{isWilayah ? ` ${OWN_REGION}` : ''}</button>
                </div>

                <div className="grid grid-cols-4 gap-3">
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.hadir}</p><p className="text-xs text-[#64748B]">Hadir (filter)</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#10B981]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.late}</p><p className="text-xs text-[#64748B]">Terlambat</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.love}</p><p className="text-xs text-[#64748B]">Pakai Love</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#991B1B]">{stats.tanpaTitik}</p><p className="text-xs text-[#64748B]">Tanpa titik</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FECACA]"></span></div>
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
                        <option value="__null">Tanpa titik {isWilayah ? '' : '(semua wilayah)'} </option>
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
                                                    <span className="inline-flex bg-[#FEF2F2] text-[#991B1B] px-2.5 py-1 rounded-full border border-[#FECACA] text-xs">Tanpa titik</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-xs"><span className={`px-2 py-1 rounded-full font-medium ${hit ? (inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]') : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{r.jarak} m {hit ? (inRadius ? '• Dalam' : '• Di luar') : ''}</span></td>
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
                        <span>{isWilayah ? `Admin Wilayah: hanya ${OWN_REGION} • tanpa titik ditolak 422` : 'Super Admin lihat semua • Admin Wilayah own region saja'}</span>
                        <span>Selfie S3 /attendance/.../{'{'}region{'}/{'}employee{'}/{'}date{'}'} • 1 karyawan = 1 titik • Jam 07:30–16:00 WITA</span>
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
                                        <p className="text-xs text-[#64748B]">{detail.wilayah} • {detail.kantor} • {detail.jarak} m {hit ? `• ${hit.site.nama_lokasi} ${hit.site.radius}m • ${inRadius ? 'Dalam radius titik assigned' : 'Di luar radius titik assigned'}` : '• Tanpa titik (ditolak 422)'} • {statusLabel[detail.status]}</p>
                                    </div>
                                    <button type="button" onClick={()=>setDetail(null)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                                </div>
                                <div className="p-5 space-y-4">
                                    <img src={detail.selfie} alt="selfie" className="w-full h-[280px] object-cover rounded-xl" />
                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu datang</p><p className="font-medium text-[#0F172A]">{detail.datang} WITA</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu pulang</p><p className="font-medium text-[#0F172A]">{detail.pulang || '— belum pulang'}</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Koordinat absen</p><p className="font-mono text-xs text-[#0F172A]">{detail.lat.toFixed(4)}, {detail.lng.toFixed(4)}</p></div>
                                        <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Jarak ke titik assigned</p><p className={`font-medium ${hit && inRadius ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>{detail.jarak} m {hit ? `/${hit.site.radius}m • ${inRadius ? 'Dalam' : 'Di luar'}` : '(tanpa titik)'}</p></div>
                                    </div>
                                    {hit ? (
                                        <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className="flex items-center justify-between bg-[#EFF6FF] rounded-xl p-3 hover:bg-[#DBEAFE]">
                                            <span><p className="text-sm font-medium text-[#1E3A8A]">{hit.site.nama_lokasi}</p><p className="text-xs text-[#64748B]">{hit.region.name} • {hit.site.lat.toFixed(4)}, {hit.site.lng.toFixed(4)} • {hit.site.radius}m</p></span>
                                            <span className="text-xs font-semibold text-[#1E3A8A]">Lihat titik →</span>
                                        </Link>
                                    ) : (
                                        <div className="bg-[#FEF2F2] border border-[#FECACA] rounded-xl p-3"><p className="text-sm font-medium text-[#991B1B]">Tanpa titik assigned — absen seharusnya ditolak 422</p><p className="text-xs text-[#991B1B]/70">Karyawan belum di-assign ke titik proyek (office_location_id null) — tidak bisa absen. Assign di halaman titik.</p></div>
                                    )}
                                    <div className="bg-[#FFF7E6] rounded-xl p-3 flex items-center justify-between">
                                        <p className="text-xs text-[#92400E]">Status: {statusLabel[detail.status]} {detail.love ? `• Love ${detail.love}` : ''}</p>
                                        <span className={`text-xs font-medium px-2 py-1 rounded-full ${statusTone[detail.status]}`}>{statusLabel[detail.status]}</span>
                                    </div>
                                    <p className="text-xs text-[#94A3B8] text-center">Foto selfie S3 /attendance/{detail.wilayah}/{detail.nama}/... • Jam global 07:30 toleransi 15m → ≤07:45 on_time</p>
                                </div>
                            </div>
                        </div>
                    );
                })()}
            </div>
        </AdminLayout>
    );
}
