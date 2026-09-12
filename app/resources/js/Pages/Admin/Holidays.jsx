import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const namaBulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

export default function Holidays() {
    const { props } = usePage();
    const { holidays, year } = props;
    const confirm = useConfirm();

    const [form, setForm] = useState({ tanggal: '', nama: '', cuti_bersama: false });
    const [editing, setEditing] = useState(null);
    const [filterYear, setFilterYear] = useState(String(year));

    const yearOptions = [];
    const now = new Date().getFullYear();
    for (let y = now - 2; y <= now + 3; y++) yearOptions.push(y);

    const changeYear = (y) => {
        setFilterYear(String(y));
        router.get('/super-admin/holidays', { year: y }, { preserveState: true, preserveScroll: true });
    };

    const openAdd = () => { setEditing(null); setForm({ tanggal: `${filterYear}-01-01`, nama: '', cuti_bersama: false }); };
    const openEdit = (h) => { setEditing(h); setForm({ tanggal: h.tanggal, nama: h.nama, cuti_bersama: h.cuti_bersama }); };
    const cancel = () => { setEditing(null); setForm({ tanggal: '', nama: '', cuti_bersama: false }); };

    const save = () => {
        if (!form.tanggal || !form.nama.trim()) { toast.error('Tanggal dan nama wajib diisi'); return; }
        if (editing) {
            router.put(`/super-admin/holidays/${editing.id}`, form, { preserveScroll: true, onSuccess: cancel });
        } else {
            router.post('/super-admin/holidays', form, { preserveScroll: true, onSuccess: cancel });
        }
    };

    const remove = async (h) => {
        const ok = await confirm({
            title: `Hapus libur ${h.nama}?`,
            description: `Tanggal ${h.tanggal}. Kalender rekap akan menghitung ulang hari kerja.`,
            confirmLabel: 'Ya, hapus',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/super-admin/holidays/${h.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Hari Libur Nasional</h1>
                        <p className="text-sm text-[#64748B]">{holidays.length} libur di tahun {filterYear} • dipakai rekap presensi untuk menandai hari libur</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <select value={filterYear} onChange={(e) => changeYear(e.target.value)} className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none">
                            {yearOptions.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <button type="button" onClick={openAdd} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">+ Tambah Libur</button>
                    </div>
                </div>

                {(editing || form.tanggal) && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-3">
                        <h3 className="font-medium text-sm text-[#0F172A]">{editing ? `Edit ${editing.nama}` : 'Tambah Libur Baru'}</h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label className="text-xs font-medium text-[#334155]">Tanggal
                                <input type="date" value={form.tanggal} onChange={(e) => setForm({ ...form, tanggal: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                            </label>
                            <label className="text-xs font-medium text-[#334155] md:col-span-2">Nama libur
                                <input type="text" value={form.nama} onChange={(e) => setForm({ ...form, nama: e.target.value })} placeholder="misal: Tahun Baru" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                            </label>
                        </div>
                        <label className="inline-flex items-center gap-2 text-sm text-[#334155]">
                            <input type="checkbox" checked={form.cuti_bersama} onChange={(e) => setForm({ ...form, cuti_bersama: e.target.checked })} />
                            <span>Ini adalah Cuti Bersama</span>
                        </label>
                        <div className="flex gap-2">
                            <button type="button" onClick={cancel} className="rounded-xl bg-[#F1F5F9] text-[#64748B] px-4 py-2 text-sm font-medium">Batal</button>
                            <button type="button" onClick={save} className="rounded-xl bg-[#0F172A] text-white px-4 py-2 text-sm font-semibold">{editing ? 'Simpan Perubahan' : 'Tambah'}</button>
                        </div>
                    </div>
                )}

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                            <tr>
                                <th className="text-left px-4 py-3">Tanggal</th>
                                <th className="text-left px-4 py-3">Hari</th>
                                <th className="text-left px-4 py-3">Nama</th>
                                <th className="text-left px-4 py-3">Jenis</th>
                                <th className="text-right px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#F1F5F9]">
                            {holidays.map((h) => {
                                const d = new Date(h.tanggal + 'T00:00:00');
                                const hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][d.getDay()];
                                return (
                                    <tr key={h.id} className="hover:bg-[#F8FAFC]/60">
                                        <td className="px-4 py-3 font-mono text-[#0F172A]">{h.tanggal}</td>
                                        <td className="px-4 py-3 text-[#334155]">{hari} • {d.getDate()} {namaBulan[d.getMonth()]}</td>
                                        <td className="px-4 py-3 font-medium text-[#0F172A]">{h.nama}</td>
                                        <td className="px-4 py-3">
                                            {h.cuti_bersama
                                                ? <span className="inline-block text-xs bg-[#FEF3C7] text-[#92400E] px-2 py-0.5 rounded-full border border-[#FCD34D]">Cuti Bersama</span>
                                                : <span className="inline-block text-xs bg-[#FEE2E2] text-[#991B1B] px-2 py-0.5 rounded-full border border-[#FCA5A5]">Libur Nasional</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={() => openEdit(h)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Edit</button>
                                                <button type="button" onClick={() => remove(h)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    {holidays.length === 0 && <p className="text-center text-sm text-[#94A3B8] py-8">Belum ada hari libur di tahun {filterYear}.</p>}
                </div>
            </div>
        </AdminLayout>
    );
}
