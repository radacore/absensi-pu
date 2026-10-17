import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

function statusStyle(s) {
    if (s === 'Disetujui') return 'bg-[#DCFCE7] text-[#065F46]';
    if (s === 'Ditolak') return 'bg-[#FEF2F2] text-[#991B1B]';
    return 'bg-[#FFF7E6] text-[#92400E]';
}

function durasi(menit) {
    const jam = Math.floor(menit / 60);
    const sisa = menit % 60;
    if (jam && sisa) return `${jam} jam ${sisa} menit`;
    if (jam) return `${jam} jam`;
    return `${sisa} menit`;
}

export default function LemburAdmin() {
    const { url, props } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const regionsData = props.regions ?? [];
    const requests = props.requests ?? [];
    const ownRegion = regionsData[0]?.name || '';
    const confirm = useConfirm();

    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? ownRegion : 'Semua');
    const [status, setStatus] = useState('Semua');
    const [rejectNote, setRejectNote] = useState({ id: null, text: '' });

    useEffect(() => { if (isWilayah && ownRegion) setWilayah(ownRegion); }, [ownRegion, isWilayah]);

    const filtered = useMemo(() => {
        let list = requests;
        if (wilayah !== 'Semua') list = list.filter((r) => r.kantor === wilayah);
        if (status !== 'Semua') list = list.filter((r) => r.status === status);
        const key = q.trim().toLowerCase();
        if (key) {
            list = list.filter((r) =>
                String(r.nama).toLowerCase().includes(key) || String(r.nik).includes(key) || String(r.keterangan).toLowerCase().includes(key));
        }
        return list;
    }, [requests, wilayah, status, q]);

    const menunggu = filtered.filter((r) => r.status === 'Menunggu').length;
    const totalMenit = filtered.filter((r) => r.status === 'Disetujui').reduce((a, r) => a + (r.menit || 0), 0);

    const setujui = async (r) => {
        const ok = await confirm({
            title: `Setujui lembur ${r.nama}?`,
            description: `${r.tanggal} • ${r.jamMulai}—${r.jamSelesai} (${durasi(r.menit)}). Setelah disetujui, menitnya masuk rekap bulanan sebagai angka lembur tersendiri.`,
            confirmLabel: 'Ya, setujui',
            tone: 'warning',
        });
        if (!ok) return;
        router.put(`${base}/lembur/${r.id}/approve`, {}, { preserveScroll: true });
    };

    const tolak = (r) => {
        if (rejectNote.id !== r.id) { setRejectNote({ id: r.id, text: '' }); return; }
        const note = rejectNote.text.trim();
        if (note.length < 3) { toast.error('Isi alasan penolakan minimal 3 karakter'); return; }
        router.put(`${base}/lembur/${r.id}/reject`, { note }, {
            preserveScroll: true,
            onSuccess: () => setRejectNote({ id: null, text: '' }),
        });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Lembur</h1>
                    <p className="text-sm text-[#64748B]">
                        Lembur diajukan karyawan terpisah dari jam kerja biasa. Hanya pengajuan yang disetujui yang dihitung di rekap bulanan.
                    </p>
                    <p className="text-xs text-[#64748B] mt-1">{menunggu} pengajuan menunggu persetujuan • {durasi(totalMenit)} disetujui pada daftar ini.</p>
                </div>

                <div className="bg-white rounded-2xl p-4 flex flex-wrap gap-3 items-end shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <label className="text-xs font-medium text-[#334155] flex-1 min-w-[200px]">Cari
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Nama, NIK, atau keterangan"
                            className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none"
                        />
                    </label>
                    {!isWilayah && (
                        <label className="text-xs font-medium text-[#334155]">Wilayah
                            <select value={wilayah} onChange={(e) => setWilayah(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                                <option value="Semua">Semua</option>
                                {regionsData.map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}
                            </select>
                        </label>
                    )}
                    <label className="text-xs font-medium text-[#334155]">Status
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="Semua">Semua</option>
                            <option value="Menunggu">Menunggu</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </label>
                    <span className="text-xs text-[#64748B] ml-auto self-center">{filtered.length} pengajuan</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr>
                                    <th className="text-left px-4 py-3">Karyawan</th>
                                    <th className="text-left px-4 py-3">Tanggal</th>
                                    <th className="text-left px-4 py-3">Jam</th>
                                    <th className="text-left px-4 py-3">Keterangan</th>
                                    <th className="text-left px-4 py-3">Status</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => (
                                    <tr key={r.id} className="hover:bg-[#F8FAFC]/50 align-top">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-[#0F172A]">{r.nama}</p>
                                            <p className="text-xs text-[#64748B]">{r.nik} • {r.kantor}</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#334155] font-mono">{r.tanggal}</td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">
                                            <p>{r.jamMulai}—{r.jamSelesai}</p>
                                            <p className="text-[#64748B]">{durasi(r.menit)}</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#334155] max-w-[280px]">{r.keterangan}</td>
                                        <td className="px-4 py-3 text-xs">
                                            <span className={`px-2 py-1 rounded-full ${statusStyle(r.status)}`}>{r.status}</span>
                                            {r.status === 'Ditolak' && r.note && <p className="text-[#991B1B] mt-1">{r.note}</p>}
                                            {r.status === 'Disetujui' && r.diprosesOleh && <p className="text-[#64748B] mt-1">oleh {r.diprosesOleh}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {r.status === 'Menunggu' && (
                                                <div className="flex flex-col gap-1 items-end">
                                                    <div className="flex gap-1">
                                                        <button type="button" onClick={() => setujui(r)} className="text-xs font-medium text-[#065F46] bg-[#DCFCE7] px-3 py-1.5 rounded-lg">Setujui</button>
                                                        <button type="button" onClick={() => tolak(r)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Tolak</button>
                                                    </div>
                                                    {rejectNote.id === r.id && (
                                                        <div className="flex gap-1 items-center">
                                                            <input
                                                                value={rejectNote.text}
                                                                onChange={(e) => setRejectNote({ id: r.id, text: e.target.value })}
                                                                placeholder="Alasan penolakan"
                                                                className="rounded-lg bg-[#F8FAFC] border-0 px-2 py-1.5 text-xs outline-none w-[160px]"
                                                            />
                                                            <button type="button" onClick={() => tolak(r)} className="text-xs font-medium bg-[#991B1B] text-white px-2 py-1.5 rounded-lg">Kirim</button>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length === 0 && <p className="text-center text-sm text-[#64748B] py-8">Belum ada pengajuan lembur.</p>}
                </div>
            </div>
        </AdminLayout>
    );
}
