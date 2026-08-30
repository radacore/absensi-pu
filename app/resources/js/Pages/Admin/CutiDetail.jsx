import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { getBase, loadCuti, saveCuti } from './_shared';

export default function CutiDetail() {
    const { props, url } = usePage();
    const base = getBase(url);
    const id = props.id || 1;
    const [list, setList] = useState(() => loadCuti());
    useEffect(() => {
        const sync = () => setList(loadCuti());
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = () => sync();
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
    }, []);
    const data = useMemo(() => list.find((c) => String(c.id) === String(id)) || list[0] || null, [list, id]);
    const [showDoc, setShowDoc] = useState(false);
    const [note, setNote] = useState('');

    if (!data) return <AdminLayout><p className="text-sm text-[#94A3B8]">Cuti tidak ditemukan</p></AdminLayout>;

    const patch = (p) => { const next = list.map((c) => c.id === data.id ? { ...c, ...p } : c); setList(next); saveCuti(next); };
    const approve = () => {
        if (data.level < 3) patch({ level: data.level + 1, status: data.level + 1 >= 3 ? 'Disetujui' : 'Menunggu' });
        else patch({ status: 'Disetujui' });
    };
    const reject = () => patch({ status: 'Ditolak', note: note.trim() || data.note || '' });

    return (
        <AdminLayout>
            <div className="space-y-5 max-w-[880px]">
                <Link href={`${base}/cuti`} className="inline-flex items-center gap-2 text-sm font-medium text-[#64748B] hover:text-[#0F172A]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M15 18l-6-6 6-6"/></svg>
                    Kembali ke daftar cuti
                </Link>

                <div className="bg-white rounded-2xl p-6 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-full bg-[#F1F5F9] flex items-center justify-center text-sm font-semibold text-[#334155]">{data.nama.split(' ').map((w)=>w[0]).join('').slice(0,2)}</div>
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">{data.nama}</h1>
                            <p className="text-sm text-[#64748B]">{data.wilayah} • {data.jenis} • {data.tgl}</p>
                            <p className="text-xs text-[#94A3B8]">{data.email} • titik {data.office_location_id}</p>
                            <span className={`inline-block mt-2 text-xs font-medium px-2.5 py-1 rounded-full ${data.status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : data.status === 'Menunggu' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{data.status} • Level {data.level}/3</span>
                        </div>
                    </div>

                    <div className="mt-6 grid lg:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div>
                                <p className="text-xs font-medium text-[#94A3B8]">Alasan</p>
                                <p className="text-sm text-[#0F172A] mt-1 leading-relaxed">{data.alasan}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-[#94A3B8]">Dokumen pendukung</p>
                                {data.dokumen ? (
                                    <button type="button" onClick={() => setShowDoc(true)} className="mt-2 inline-flex items-center gap-2 text-sm font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-2 rounded-xl hover:bg-[#DBEAFE]">
                                        {data.dokumen} — Lihat
                                    </button>
                                ) : <p className="text-sm text-[#94A3B8] mt-1">Tidak ada dokumen</p>}
                            </div>
                        </div>
                        <div className="bg-[#F8FAFC] rounded-2xl p-4">
                            <p className="text-xs font-medium text-[#94A3B8]">Timeline berjenjang</p>
                            <div className="mt-3 space-y-3">
                                {[
                                    { l: 1, name: 'Atasan Langsung' },
                                    { l: 2, name: `Admin Wilayah ${data.wilayah}` },
                                    { l: 3, name: 'Kantor Pusat' },
                                ].map((s) => {
                                    const state = data.status === 'Ditolak' ? (data.level >= s.l ? 'approved' : 'pending') : data.level >= s.l ? 'approved' : data.level + 1 === s.l && data.status === 'Menunggu' ? 'current' : 'pending';
                                    return (
                                        <div key={s.l} className="flex items-center gap-3">
                                            <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold ${state === 'approved' ? 'bg-[#0F172A] text-white' : state === 'current' ? 'bg-[#FCB833] text-[#0F172A]' : 'bg-white border border-[#E2E8F0] text-[#94A3B8]'}`}>{s.l}</span>
                                            <div className="flex-1">
                                                <p className="text-sm font-medium text-[#0F172A]">{s.name}</p>
                                                <p className="text-xs text-[#64748B]">{state === 'approved' ? 'Disetujui' : state === 'current' ? 'Menunggu Anda' : 'Menunggu'}</p>
                                            </div>
                                            {state === 'approved' && <span className="w-2 h-2 rounded-full bg-[#10B981]"></span>}
                                        </div>
                                    );
                                })}
                            </div>
                            {data.note && data.status === 'Ditolak' && <p className="text-xs text-[#991B1B] bg-[#FEF2F2] rounded-lg px-3 py-1.5 mt-3">Catatan: {data.note}</p>}
                        </div>
                    </div>

                    {data.status === 'Menunggu' && (
                        <div className="mt-6 space-y-3">
                            <input value={note} onChange={(e)=>setNote(e.target.value)} placeholder="Catatan penolakan (opsional, untuk Tolak)" className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            <div className="flex gap-3">
                                <button type="button" onClick={reject} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Tolak</button>
                                <button type="button" onClick={approve} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold hover:bg-[#1E3A8A]">Setujui → Level {Math.min(3, data.level + 1)}</button>
                            </div>
                        </div>
                    )}
                    {data.status !== 'Menunggu' && (
                        <p className="mt-6 text-center text-sm font-medium text-[#64748B]">Status final: <span className={data.status === 'Disetujui' ? 'text-[#065F46]' : 'text-[#991B1B]'}>{data.status}</span></p>
                    )}
                </div>

                {showDoc && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => setShowDoc(false)}>
                        <div className="bg-white rounded-2xl max-w-[640px] w-full overflow-hidden" onClick={(e) => e.stopPropagation()}>
                            <div className="px-5 py-4 flex items-center justify-between border-b">
                                <h3 className="font-medium text-sm text-[#0F172A]">{data.dokumen}</h3>
                                <button type="button" onClick={() => setShowDoc(false)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="p-6">
                                {data.dokumen.endsWith('.jpg') ? (
                                    <img src="https://images.unsplash.com/photo-1589829085413-56de8ae18c73?w=800&h=600&fit=crop&auto=format" alt="Dokumen" className="w-full rounded-xl object-cover" />
                                ) : (
                                    <div className="bg-[#F8FAFC] rounded-xl p-8 text-center">
                                        <p className="text-sm font-medium text-[#0F172A]">Preview PDF</p>
                                        <p className="text-xs text-[#64748B] mt-1">{data.dokumen}</p>
                                        <div className="mt-4 h-32 bg-white rounded-xl border-2 border-dashed border-[#E2E8F0] flex items-center justify-center">
                                            <span className="text-xs text-[#94A3B8]">PDF content preview</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
