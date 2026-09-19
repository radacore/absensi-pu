import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

const statusColor = (s) => s === 'Disetujui' ? 'bg-[#DCFCE7] text-[#065F46] border-[#86EFAC]'
    : s === 'Ditolak' ? 'bg-[#FEE2E2] text-[#991B1B] border-[#FCA5A5]'
    : 'bg-[#FEF3C7] text-[#92400E] border-[#FCD34D]';

export default function Dinas() {
    const { url, props } = usePage();
    const base = getBase(url);
    const list = props.list ?? [];
    const regions = props.regions ?? [];
    const confirm = useConfirm();

    const [filterStatus, setFilterStatus] = useState('Semua');
    const [filterRegion, setFilterRegion] = useState('Semua');
    const [rejectForm, setRejectForm] = useState({ id: null, note: '' });

    const filtered = useMemo(() => list.filter((r) =>
        (filterStatus === 'Semua' || r.status === filterStatus) &&
        (filterRegion === 'Semua' || r.wilayah === filterRegion)
    ), [list, filterStatus, filterRegion]);

    const approve = async (r) => {
        const ok = await confirm({
            title: `Setujui dinas ${r.nama}?`,
            description: `${r.tglLabel} — ${r.tujuan}. Setelah disetujui, tanggal dinas akan direkap sebagai hadir.`,
            confirmLabel: 'Setujui',
            tone: 'warning',
        });
        if (!ok) return;
        router.put(`${base}/dinas/${r.id}/approve`, {}, { preserveScroll: true });
    };

    const submitReject = () => {
        if (rejectForm.note.trim().length < 3) { toast.error('Alasan reject minimal 3 karakter'); return; }
        router.put(`${base}/dinas/${rejectForm.id}/reject`, { note: rejectForm.note.trim() }, {
            preserveScroll: true,
            onSuccess: () => setRejectForm({ id: null, note: '' }),
        });
    };

    const remove = async (r) => {
        const ok = await confirm({
            title: `Hapus pengajuan dinas ${r.nama}?`,
            description: `${r.tglLabel}. Data akan dihapus permanen.`,
            confirmLabel: 'Ya, hapus',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`${base}/dinas/${r.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Perjalanan Dinas</h1>
                        <p className="text-sm text-[#64748B]">{list.length} pengajuan • {list.filter((r) => r.status === 'Menunggu').length} menunggu approval</p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl p-4 flex flex-wrap gap-3 items-end shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <label className="text-xs font-medium text-[#334155]">Status
                        <select value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="Semua">Semua</option>
                            <option value="Menunggu">Menunggu</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </label>
                    <label className="text-xs font-medium text-[#334155]">Wilayah
                        <select value={filterRegion} onChange={(e) => setFilterRegion(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="Semua">Semua</option>
                            {regions.map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}
                        </select>
                    </label>
                    <span className="text-xs text-[#64748B] ml-auto self-center">{filtered.length} hasil</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr>
                                    <th className="text-left px-4 py-3">Karyawan</th>
                                    <th className="text-left px-4 py-3">Nomor Surat</th>
                                    <th className="text-left px-4 py-3">Tanggal Dinas</th>
                                    <th className="text-left px-4 py-3">Tujuan</th>
                                    <th className="text-left px-4 py-3">Dokumen</th>
                                    <th className="text-left px-4 py-3">Status</th>
                                    <th className="text-right px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => (
                                    <tr key={r.id} className="hover:bg-[#F8FAFC]/60 align-top">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-[#0F172A]">{r.nama}</p>
                                            <p className="text-xs text-[#94A3B8]">{r.wilayah}</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs font-mono text-[#0F172A]">{r.nomor_surat}</td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">
                                            {r.tglLabel}
                                            <p className="text-[#94A3B8]">Diajukan {r.tanggal_pengajuan}</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">
                                            {r.tujuan}
                                            {r.transportasi && <p className="text-[#94A3B8]">🚗 {r.transportasi}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.dokumen_nama ? (
                                                <div className="space-y-1">
                                                    <a href={`${base}/dinas/${r.id}/dokumen`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs font-medium text-[#0D9488] bg-[#F0FDFA] border border-[#99F6E4] px-2 py-1.5 rounded-lg">
                                                        {r.dokumen_is_image ? '🖼' : '📄'} Lihat
                                                    </a>
                                                    <p className="text-[10px] text-[#94A3B8] max-w-[150px] truncate" title={r.dokumen_nama}>{r.dokumen_nama} • {r.dokumen_size}</p>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-[#CBD5E1]">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block text-xs font-semibold px-2.5 py-1 rounded-full border ${statusColor(r.status)}`}>{r.status}</span>
                                            {r.status === 'Ditolak' && r.note && <p className="text-xs text-[#991B1B] mt-1 italic">{r.note}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                {r.status === 'Menunggu' && (
                                                    <>
                                                        <button type="button" onClick={() => approve(r)} className="text-xs font-medium bg-[#DCFCE7] text-[#065F46] px-2 py-1.5 rounded-lg">Setujui</button>
                                                        <button type="button" onClick={() => setRejectForm({ id: r.id, note: '' })} className="text-xs font-medium bg-[#FFEDD5] text-[#9A3412] px-2 py-1.5 rounded-lg">Tolak</button>
                                                    </>
                                                )}
                                                <button type="button" onClick={() => remove(r)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length === 0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada pengajuan dinas untuk filter ini.</p>}
                </div>

                {rejectForm.id && (
                    <div className="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center p-4" onClick={() => setRejectForm({ id: null, note: '' })}>
                        <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4 border-b border-[#F1F5F9]">
                                <h3 className="font-semibold text-[#0F172A]">Tolak Pengajuan Dinas</h3>
                                <p className="text-xs text-[#94A3B8] mt-1">Tulis alasan singkat (minimal 3 karakter). Alasan akan dilihat karyawan.</p>
                            </div>
                            <div className="px-6 py-4">
                                <textarea rows="3" value={rejectForm.note} onChange={(e) => setRejectForm({ ...rejectForm, note: e.target.value })} placeholder="Alasan penolakan..." className="w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none resize-none" />
                            </div>
                            <div className="px-6 pb-5 flex gap-2">
                                <button type="button" onClick={() => setRejectForm({ id: null, note: '' })} className="flex-1 rounded-xl bg-[#F1F5F9] py-2.5 text-sm font-semibold text-[#64748B]">Batal</button>
                                <button type="button" onClick={submitReject} className="flex-1 rounded-xl bg-[#EF4444] text-white py-2.5 text-sm font-semibold">Kirim Penolakan</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
