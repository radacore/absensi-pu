import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Dinas() {
    const { props } = usePage();
    const list = props.list ?? [];
    const confirm = useConfirm();

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({
        nomor_surat: '', tanggal_mulai: '', tanggal_selesai: '',
        keterangan: '', tujuan: '', transportasi: 'mobil', pembebanan_anggaran: '',
    });

    const submit = () => {
        if (!form.nomor_surat.trim()) { toast.error('Nomor surat wajib diisi'); return; }
        if (!form.tanggal_mulai || !form.tanggal_selesai) { toast.error('Tanggal mulai dan selesai wajib'); return; }
        if (form.tanggal_selesai < form.tanggal_mulai) { toast.error('Tanggal selesai tidak boleh sebelum mulai'); return; }
        if (form.keterangan.trim().length < 5) { toast.error('Keterangan minimal 5 karakter'); return; }
        if (!form.tujuan.trim()) { toast.error('Tujuan wajib diisi'); return; }
        router.post('/karyawan/dinas', form, {
            preserveScroll: true,
            onSuccess: () => {
                setShowForm(false);
                setForm({ nomor_surat: '', tanggal_mulai: '', tanggal_selesai: '', keterangan: '', tujuan: '', transportasi: 'mobil', pembebanan_anggaran: '' });
            },
        });
    };

    const cancel = async (id) => {
        const ok = await confirm({
            title: 'Batalkan pengajuan dinas?',
            description: 'Pengajuan akan dihapus permanen dan tidak dapat dikembalikan.',
            confirmLabel: 'Ya, batalkan',
            cancelLabel: 'Tidak',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/karyawan/dinas/${id}`, { preserveScroll: true });
    };

    const statusColor = (s) => s === 'Disetujui' ? 'bg-[#DCFCE7] text-[#065F46] border-[#86EFAC]'
        : s === 'Ditolak' ? 'bg-[#FEE2E2] text-[#991B1B] border-[#FCA5A5]'
        : 'bg-[#FEF3C7] text-[#92400E] border-[#FCD34D]';

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Perjalanan Dinas</h2>
                        <p className="text-sm text-[#64748B]">Ajukan surat perjalanan dinas untuk direkap sebagai hadir</p>
                    </div>
                    <button type="button" onClick={() => setShowForm(!showForm)} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">{showForm ? 'Tutup' : 'Ajukan Dinas'}</button>
                </div>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-3">
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Nomor Surat
                                <input type="text" value={form.nomor_surat} onChange={(e) => setForm({ ...form, nomor_surat: e.target.value })} placeholder="misal: 2485/SPT/0627/2026" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </label>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-xs font-medium text-[#334155]">Tanggal Mulai
                                <input type="date" value={form.tanggal_mulai} onChange={(e) => setForm({ ...form, tanggal_mulai: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </label>
                            <label className="text-xs font-medium text-[#334155]">Tanggal Selesai
                                <input type="date" value={form.tanggal_selesai} onChange={(e) => setForm({ ...form, tanggal_selesai: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </label>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Keterangan Tugas
                                <textarea rows="2" value={form.keterangan} onChange={(e) => setForm({ ...form, keterangan: e.target.value })} placeholder="misal: Monitoring pelaksanaan kegiatan" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none resize-none" />
                            </label>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-xs font-medium text-[#334155]">Tujuan
                                <input type="text" value={form.tujuan} onChange={(e) => setForm({ ...form, tujuan: e.target.value })} placeholder="misal: Bone" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </label>
                            <label className="text-xs font-medium text-[#334155]">Transportasi
                                <select value={form.transportasi} onChange={(e) => setForm({ ...form, transportasi: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                    <option value="mobil">Mobil</option>
                                    <option value="motor">Motor</option>
                                    <option value="pesawat">Pesawat</option>
                                    <option value="kapal">Kapal</option>
                                    <option value="darat lain">Darat Lain</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Pembebanan Anggaran
                                <input type="text" value={form.pembebanan_anggaran} onChange={(e) => setForm({ ...form, pembebanan_anggaran: e.target.value })} placeholder="misal: Kendaraan Sewa" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </label>
                        </div>
                        <button type="button" onClick={submit} className="w-full rounded-xl bg-[#0D9488] text-white py-2.5 text-sm font-semibold">Kirim Pengajuan</button>
                    </div>
                )}

                {list.length === 0 ? (
                    <p className="text-sm text-[#94A3B8] bg-white rounded-2xl p-6 text-center">Belum ada pengajuan dinas</p>
                ) : (
                    <div className="space-y-3">
                        {list.map((r) => (
                            <div key={r.id} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-sm text-[#0F172A] truncate">{r.tglLabel}</p>
                                        <p className="text-xs text-[#64748B] mt-1">📄 {r.nomor_surat} • 🎯 {r.tujuan}</p>
                                        <p className="text-xs text-[#475569] mt-2">{r.keterangan}</p>
                                        {r.transportasi && <p className="text-xs text-[#94A3B8] mt-1">🚗 {r.transportasi}{r.pembebanan_anggaran ? ` • 💰 ${r.pembebanan_anggaran}` : ''}</p>}
                                    </div>
                                    <span className={`shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full border ${statusColor(r.status)}`}>{r.status}</span>
                                </div>
                                {r.status === 'Ditolak' && r.note && (
                                    <div className="mt-3 bg-[#FEF2F2] rounded-lg p-2 text-xs text-[#991B1B]"><strong>Catatan admin:</strong> {r.note}</div>
                                )}
                                {r.status === 'Menunggu' && (
                                    <button type="button" onClick={() => cancel(r.id)} className="mt-3 text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Batalkan Pengajuan</button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </KaryawanLayout>
    );
}
