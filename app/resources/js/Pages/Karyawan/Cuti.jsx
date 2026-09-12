import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Cuti() {
    const { props } = usePage();
    const me = props.me ?? { nama: '—', region: '' };
    const assigned = props.assigned ?? null;
    const list = props.list ?? [];
    const flash = props.flash;
    const errors = props.errors;

    const [showForm, setShowForm] = useState(false);
    const [jenis, setJenis] = useState('Tahunan');
    const [mulai, setMulai] = useState('');
    const [selesai, setSelesai] = useState('');
    const [alasan, setAlasan] = useState('');
    const [toast, setToast] = useState(null);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);

    const show = (msg, ok = true) => { setToast({ msg, ok }); setTimeout(() => setToast(null), 2500); };
    useEffect(() => { if (flash?.success) show(flash.success, true); if (flash?.error) show(flash.error, false); }, [flash?.success, flash?.error]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => { if (errors && Object.keys(errors).length) show(Object.values(errors).flat().join(' '), false); }, [errors]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleSubmit = () => {
        if (!mulai || !selesai) { show('Tanggal mulai & selesai wajib', false); return; }
        if (mulai > selesai) { show('Tanggal mulai tidak boleh setelah selesai', false); return; }
        if (!alasan.trim()) { show('Alasan wajib diisi', false); return; }
        router.post('/karyawan/cuti', { jenis, mulai, selesai, alasan: alasan.trim() }, {
            preserveScroll: true,
            onSuccess: () => { setJenis('Tahunan'); setMulai(''); setSelesai(''); setAlasan(''); setShowForm(false); },
            onError: (e) => show(Object.values(e).flat().join(' ') || 'Gagal ajukan', false),
        });
    };
    const handleCancel = (id) => {
        router.delete(`/karyawan/cuti/${id}`, {
            preserveScroll: true,
            onSuccess: () => setConfirmDeleteId(null),
            onError: (e) => show(Object.values(e).flat().join(' ') || 'Gagal batalkan', false),
        });
    };

    const tone = (s) => s === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : s === 'Menunggu' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]';

    if (!assigned) {
        return (<KaryawanLayout><div className="bg-white rounded-2xl p-6 text-center"><p className="text-sm text-[#64748B]">Titik belum di-assign — hubungi Admin</p></div></KaryawanLayout>);
    }

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Cuti</h2>
                        <p className="text-sm text-[#64748B]">Berjenjang 3 tahap • {assigned.site.nama_lokasi} • {assigned.site.radius}m</p>
                    </div>
                    <button type="button" onClick={() => setShowForm(!showForm)} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">{showForm ? 'Tutup' : 'Ajukan cuti'}</button>
                </div>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="jenis" className="text-xs font-medium text-[#334155]">Jenis</label>
                                <select id="jenis" value={jenis} onChange={(e)=>setJenis(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white outline-none"><option>Tahunan</option><option>Sakit</option><option>Besar</option><option>Melahirkan</option></select>
                            </div>
                            <div>
                                <label htmlFor="mulai" className="text-xs font-medium text-[#334155]">Mulai</label>
                                <input id="mulai" type="date" value={mulai} onChange={(e)=>setMulai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="selesai" className="text-xs font-medium text-[#334155]">Selesai</label>
                                <input id="selesai" type="date" value={selesai} onChange={(e)=>setSelesai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                        </div>
                        <div>
                            <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e)=>setAlasan(e.target.value)} placeholder="Tuliskan alasan cuti" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none"></textarea>
                        </div>
                        <button type="button" onClick={handleSubmit} className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#1E3A8A] transition">Kirim pengajuan</button>
                        {toast && <p className={`text-xs text-center rounded-xl py-2 ${toast.ok ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF3C7] text-[#92400E]'}`}>{toast.msg}</p>}
                    </div>
                )}

                {!showForm && toast && <p className={`text-xs text-center rounded-xl py-2 ${toast.ok ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{toast.msg}</p>}

                <div className="space-y-3">
                    {list.length === 0 ? (
                        <p className="text-sm text-[#94A3B8] bg-white rounded-2xl p-6 text-center">Belum ada pengajuan — ajukan cuti pertama</p>
                    ) : list.map((r) => (
                        <div key={r.id} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-sm text-[#0F172A]">{r.jenis} • {r.tgl}</p>
                                    <p className="text-sm text-[#475569] mt-1">{r.alasan}</p>
                                    <p className="text-xs text-[#94A3B8] mt-1">{r.wilayah} • titik {r.office_location_id}</p>
                                </div>
                                <span className={`shrink-0 text-xs font-medium px-2.5 py-1 rounded-full ${tone(r.status)}`}>{r.status}</span>
                            </div>
                            <div className="mt-4 flex items-center gap-1.5">
                                {['Atasan', 'Admin Wilayah', 'Kantor Pusat'].map((s, i) => (
                                    <div key={s} className="flex items-center gap-1.5">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${i < r.level ? 'bg-[#0F172A] text-white' : i === r.level && r.status === 'Menunggu' ? 'bg-[#FEF3C7] text-[#92400E]' : i === r.level && r.status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F1F5F9] text-[#64748B]'}`}>{s}</span>
                                        {i < 2 && <span className="text-[#CBD5E1]">—</span>}
                                    </div>
                                ))}
                            </div>
                            {r.status === 'Menunggu' && r.level === 0 && (
                                <button type="button" onClick={()=>setConfirmDeleteId(r.id)} className="mt-3 text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Batalkan pengajuan</button>
                            )}
                            {r.status === 'Ditolak' && r.note && <p className="text-xs text-[#991B1B] bg-[#FEF2F2] rounded-lg px-3 py-1.5 mt-2">Catatan: {r.note}</p>}
                        </div>
                    ))}
                </div>
                {confirmDeleteId != null && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-2xl p-5 w-full max-w-sm">
                            <p className="font-medium text-[#0F172A]">Batalkan cuti?</p>
                            <p className="text-sm text-[#64748B] mt-1">Pengajuan yang dibatalkan tidak bisa dikembalikan.</p>
                            <div className="flex gap-2 mt-4">
                                <button type="button" onClick={()=>setConfirmDeleteId(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-2.5 text-sm font-medium">Batal</button>
                                <button type="button" onClick={()=>handleCancel(confirmDeleteId)} className="flex-1 rounded-xl bg-[#EF4444] text-white py-2.5 text-sm font-semibold">Batalkan</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </KaryawanLayout>
    );
}
