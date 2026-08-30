import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { getBase, loadRegions, OWN_REGION, WILAYAH_LIST as wilayahList, getSitesForWilayah, getValidSiteIds, siteById } from './_shared';

const dummy = [
    { id: 1, nama: 'Andi Saputra', wilayah: 'Kab. Gowa', office_location_id: 201, kantor: 'Kantor Gowa', jam: '07:52', jarak: 42, radius: 200, alasan: 'Macet poros Gowa — lampiran foto jalan', dokumen: 'surat.pdf', status: 'pending', sisaLove: '3/4' },
    { id: 2, nama: 'Rina Wati', wilayah: 'Kab. Maros', office_location_id: null, kantor: 'Kantor Maros', jam: '07:48', jarak: 28, radius: null, alasan: 'Antar anak sakit — surat dokter', dokumen: 'foto.jpg', status: 'pending', sisaLove: '4/4' },
    { id: 3, nama: 'Rudi Hartono', wilayah: 'Kab. Bone', office_location_id: 401, kantor: 'Kantor Bone', jam: '07:55', jarak: 18, radius: 150, alasan: 'Ban bocor', dokumen: 'bukti.jpg', status: 'approved', sisaLove: '2/4' },
    { id: 4, nama: 'Dewi Lestari', wilayah: 'Kab. Takalar', office_location_id: 2001, kantor: 'Kantor Takalar', jam: '08:05', jarak: 95, radius: 200, alasan: 'Hujan deras', dokumen: 'surat.pdf', status: 'rejected', sisaLove: '1/4', note: 'Dokumen tidak relevan' },
    { id: 5, nama: 'Budi Santoso', wilayah: 'Kab. Gowa', office_location_id: 202, kantor: 'Kantor Gowa', jam: '07:44', jarak: 21, radius: 150, alasan: 'Keterlambatan KRL', dokumen: 'tiket.pdf', status: 'pending', sisaLove: '0/4' },
];

export default function LoveAdmin() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [claims, setClaims] = useState(dummy);
    const [previewDoc, setPreviewDoc] = useState(null);
    const [rejectNote, setRejectNote] = useState({ id: null, text: '' });
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [siteFilter, setSiteFilter] = useState('Semua');
    const [status, setStatus] = useState('Semua');
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    useEffect(() => {
        const sync = () => setRegionsData(loadRegions());
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);
    useEffect(() => {
        if (siteFilter === 'Semua' || siteFilter === '__null') return;
        if (!getValidSiteIds(regionsData, wilayah, isWilayah).has(String(siteFilter))) setSiteFilter('Semua');
    }, [wilayah, siteFilter, regionsData, isWilayah]);
    const sitesForWilayah = useMemo(() => getSitesForWilayah(regionsData, wilayah, isWilayah), [regionsData, wilayah, isWilayah]);

    const baseClaims = isWilayah ? claims.filter((c) => c.wilayah === OWN_REGION) : claims;
    const filtered = useMemo(() => baseClaims.filter((c) => {
        if (!isWilayah && wilayah !== 'Semua' && c.wilayah !== wilayah) return false;
        if (isWilayah && wilayah !== OWN_REGION) return false;
        if (siteFilter !== 'Semua') {
            if (siteFilter === '__null') { if (c.office_location_id != null) return false; }
            else if (String(c.office_location_id) !== String(siteFilter)) return false;
        }
        if (status !== 'Semua' && c.status !== status) return false;
        if (q && !c.nama.toLowerCase().includes(q.toLowerCase()) && !c.kantor.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [baseClaims, q, wilayah, siteFilter, status, isWilayah]);

    const counts = { pending: claims.filter((c)=>c.status==='pending').length, approved: claims.filter((c)=>c.status==='approved').length };

    const handle = (id, nextStatus) => {
        if (nextStatus === 'rejected' && !rejectNote.text.trim()) { return; }
        setClaims((list) => list.map((c) => (c.id === id ? { ...c, status: nextStatus, note: nextStatus==='rejected' ? rejectNote.text : c.note } : c)));
        setRejectNote({ id: null, text: '' });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Love Claims — ${OWN_REGION}` : 'Love Claims — 4 Hati / bulan'}</h1>
                        <p className="text-sm text-[#64748B]">Hanya <span className="font-medium text-[#0F172A]">late bulan yang sama (hari beda boleh)</span> & dalam radius <span className="font-medium text-[#0F172A]">titik assigned</span> • Approval 1 level Admin Wilayah own region • 1 Love = 1 late • 1 karyawan = 1 titik</p>
                        <p className="text-xs text-[#94A3B8] mt-1">{isWilayah ? `Scope own region ${OWN_REGION} •` : ''} Di luar radius titik assigned tidak bisa claim (422 out_of_radius) • Tanpa titik tidak bisa claim • Reset 1st 00:00 WITA • 0 Love tidak bisa excuse</p>
                    </div>
                    <span className="shrink-0 bg-[#FFF7E6] border border-[#FCB833]/30 text-[#92400E] text-xs font-medium px-3 py-1.5 rounded-full">{counts.pending} pending • {counts.approved} approved bulan ini</span>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.pending}</p><p className="text-xs text-[#64748B]">Pending</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{claims.filter(c=>c.status==='approved').length}</p><p className="text-xs text-[#64748B]">Disetujui bulan ini</p></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">3.6</p><p className="text-xs text-[#64748B]">Rata sisa Love</p></div>
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
                        <option value="__null">Tanpa titik {isWilayah ? '' : '(semua wilayah)'} </option>
                        {sitesForWilayah.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                        {!isWilayah && wilayah==='Semua' && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                    </select>
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari nama / kantor..." className="flex-1 min-w-[160px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} hasil</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Titik Proyek</th><th className="text-left px-4 py-3">Late</th><th className="text-left px-4 py-3">Sisa</th><th className="text-left px-4 py-3">Alasan</th><th className="text-left px-4 py-3">Dokumen</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((c) => {
                                    const hit = siteById(c.office_location_id, regionsData);
                                    const inRadius = c.office_location_id != null && c.radius != null ? c.jarak <= c.radius : false;
                                    return (
                                        <tr key={c.id} className="hover:bg-[#F8FAFC]/50">
                                            <td className="px-4 py-3"><p className="font-medium text-[#0F172A]">{c.nama}</p><p className="text-xs text-[#64748B]">{c.wilayah} • {c.kantor}</p></td>
                                            <td className="px-4 py-3 text-xs">{hit ? <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className={`px-2 py-1 rounded-full font-medium ${inRadius ? 'bg-[#EFF6FF] text-[#1E3A8A]' : 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]'} hover:opacity-80`}>{hit.site.nama_lokasi} • {hit.site.radius}m</Link> : <span className="bg-[#FEF2F2] text-[#991B1B] px-2 py-1 rounded-full border">Tanpa titik</span>}</td>
                                            <td className="px-4 py-3 text-xs"><span title={hit ? `${c.jarak} m / ${c.radius} m • ${hit.site.nama_lokasi} • ${inRadius ? 'Dalam — bisa excuse' : 'Di luar — tidak bisa excuse'}` : 'Tanpa titik — tidak bisa excuse'} className={inRadius ? 'text-[#065F46]' : hit ? 'text-[#991B1B]' : 'text-[#991B1B]'}>{c.jam} • {c.jarak} m{c.radius ? `/${c.radius}m` : ''} {hit ? (inRadius ? '• Dalam' : '• Di luar') : ''}{!hit ? '• Tanpa titik' : ''}</span></td>
                                            <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${c.sisaLove.startsWith('0') ? 'bg-[#FEF2F2] text-[#991B1B]' : 'bg-[#FFF7E6] text-[#92400E]'}`}>{c.sisaLove}</span></td>
                                            <td className="px-4 py-3 text-xs max-w-[160px] truncate" title={c.alasan}>{c.alasan}{c.note ? ` — ${c.note}` : ''}</td>
                                            <td className="px-4 py-3"><button type="button" onClick={() => setPreviewDoc(c)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2 py-1 rounded-lg hover:bg-[#DBEAFE] inline-flex items-center gap-1">📄 {c.dokumen}</button></td>
                                            <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${c.status === 'pending' ? 'bg-[#FFF7E6] text-[#92400E]' : c.status === 'approved' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span></td>
                                            <td className="px-4 py-3 text-right">
                                                {c.status === 'pending' ? (
                                                    <div className="flex gap-1 justify-end">
                                                        <button type="button" onClick={() => handle(c.id, 'approved')} disabled={c.sisaLove.startsWith('0') || !hit || !inRadius} title={c.sisaLove.startsWith('0') ? 'Sisa Love 0 — tidak bisa excuse, reset bulan depan' : !hit ? 'Tanpa titik — tidak bisa approve (assign dulu)' : !inRadius ? `${c.jarak} m / ${c.radius} m — di luar radius titik assigned, tidak bisa excuse` : ''} className={`text-xs font-medium px-3 py-1.5 rounded-lg ${c.sisaLove.startsWith('0') || !hit || !inRadius ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#10B981] text-white hover:bg-[#059669]'}`}>Approve</button>
                                                        {rejectNote.id === c.id ? (
                                                            <div className="flex gap-1">
                                                                <input value={rejectNote.text} onChange={(e)=>setRejectNote({ id: c.id, text: e.target.value })} placeholder="Alasan reject..." className="w-28 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] px-2 py-1 text-xs outline-none" />
                                                                <button type="button" onClick={() => handle(c.id, 'rejected')} className="text-xs font-medium bg-[#EF4444] text-white px-2 py-1.5 rounded-lg">Kirim</button>
                                                                <button type="button" onClick={()=>setRejectNote({id:null,text:''})} className="text-xs px-1">✕</button>
                                                            </div>
                                                        ) : (
                                                            <button type="button" onClick={() => setRejectNote({ id: c.id, text: '' })} className="text-xs font-medium bg-[#F1F5F9] text-[#64748B] px-3 py-1.5 rounded-lg">Reject</button>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-[#94A3B8]">— {c.note || ''}</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada claim untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">{isWilayah ? `Admin Wilayah: review & approve hanya ${OWN_REGION} — love habis 0 / tanpa titik tidak bisa excuse` : 'Super Admin lihat semua • Admin Wilayah lihat own region saja • Tanpa titik tidak bisa excuse • Dalam radius titik assigned • Bulan sama, hari beda boleh'}</div>
                </div>

                {previewDoc && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => setPreviewDoc(null)}>
                        <div className="bg-white rounded-2xl max-w-[560px] w-full overflow-hidden" onClick={(e) => e.stopPropagation()}>
                            <div className="px-5 py-4 flex items-center justify-between border-b">
                                <div>
                                    <h3 className="font-medium text-sm text-[#0F172A]">{previewDoc.dokumen}</h3>
                                    <p className="text-xs text-[#64748B]">{previewDoc.nama} • {previewDoc.wilayah} • {previewDoc.jam} • {previewDoc.jarak} m{previewDoc.radius ? `/${previewDoc.radius}m` : ''} • Sisa {previewDoc.sisaLove}</p>
                                </div>
                                <button type="button" onClick={() => setPreviewDoc(null)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="p-6">
                                {previewDoc.dokumen.endsWith('.jpg') ? (
                                    <img src="https://images.unsplash.com/photo-1589829085413-56de8ae18c73?w=800&h=500&fit=crop&auto=format" alt="Dokumen" className="w-full rounded-xl object-cover" />
                                ) : (
                                    <div className="bg-[#F8FAFC] rounded-xl p-8 text-center">
                                        <p className="text-sm font-medium text-[#0F172A]">Preview PDF</p>
                                        <p className="text-xs text-[#64748B] mt-1">{previewDoc.dokumen} • {previewDoc.alasan}</p>
                                        <div className="mt-4 h-40 bg-white rounded-xl border-2 border-dashed border-[#E2E8F0] flex items-center justify-center">
                                            <span className="text-xs text-[#94A3B8]">PDF preview dummy — S3 /love-claims/... (frontend only)</span>
                                        </div>
                                    </div>
                                )}
                                <p className="text-xs text-[#94A3B8] mt-4 text-center">Hanya bulan sama (hari beda boleh) & dalam radius titik assigned • 1 Love = 1 late • tanpa titik tidak bisa claim</p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
