import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

const empty = { nama: '', email: '', region: '', password: '' };

export default function AdminWilayah() {
    const { url, props } = usePage();
    const { admins, regionNames } = props;
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const list = admins || [];
    const regions = regionNames || [];
    const confirm = useConfirm();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ ...empty, region: regions[1] || '' });

    const openAdd = () => { setEditing(null); setForm({ ...empty, region: regions[1] || '' }); setOpen(true); };
    const openEdit = (a) => { setEditing(a); setForm({ nama: a.nama, email: a.email, region: a.region, password: '' }); setOpen(true); };
    const close = () => setOpen(false);

    const save = () => {
        if (!form.nama.trim() || !/^\S+@\S+\.\S+$/.test(form.email)) { toast.error('Nama dan email valid wajib diisi'); return; }
        if (!editing && form.password.length < 8) { toast.error('Password minimal 8 karakter'); return; }
        const payload = { nama: form.nama, email: form.email, region: form.region };
        if (editing) {
            if (form.password) payload.password = form.password;
            router.put(`${base}/admin-wilayah/${editing.id}`, payload, { preserveScroll: true });
        } else {
            payload.password = form.password;
            router.post(`${base}/admin-wilayah`, payload, { preserveScroll: true });
        }
        setOpen(false);
    };

    const handleReset = () => {
        toast.error('Reset password belum tersedia di fase ini — silakan hubungi Super Admin');
    };
    const toggleStatus = async (a) => {
        const willDeactivate = a.status === 'Aktif';
        if (willDeactivate) {
            const ok = await confirm({
                title: `Nonaktifkan ${a.nama}?`,
                description: `${a.email} • ${a.region}. Admin ini tidak akan bisa login sampai diaktifkan kembali.`,
                confirmLabel: 'Ya, nonaktifkan',
                cancelLabel: 'Batal',
                tone: 'warning',
            });
            if (!ok) return;
        }
        router.put(`${base}/admin-wilayah/${a.id}/toggle`, { is_active: !willDeactivate }, { preserveScroll: true });
    };
    const remove = async (id) => {
        const target = list.find((x)=>x.id===id);
        if (!target) return;
        const ok = await confirm({
            title: `Hapus ${target.nama}?`,
            description: `${target.email} • ${target.region}. Akun Admin Wilayah akan dihapus permanen dan tidak dapat dikembalikan.`,
            confirmLabel: 'Ya, hapus',
            cancelLabel: 'Batal',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`${base}/admin-wilayah/${target.id}`, { preserveScroll: true });
    };

    if (isWilayah) {
        return (
            <AdminLayout>
                <div className="bg-white rounded-2xl p-8 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <p className="text-sm font-medium text-[#991B1B]">Akses ditolak</p>
                    <p className="text-sm text-[#64748B] mt-1">Menu Admin Wilayah hanya untuk Super Admin — gunakan /super-admin/admin-wilayah</p>
                    <p className="text-xs text-[#94A3B8] mt-2">Admin Wilayah tidak bisa kelola akun lain (least privilege)</p>
                </div>
            </AdminLayout>
        );
    }
    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Admin Wilayah</h1>
                        <p className="text-sm text-[#64748B]">Kelola akun Admin Wilayah — assign 1 region, role admin_wilayah</p>
                    </div>
                    <button type="button" onClick={openAdd} className="bg-[#FCB833] text-[#0F172A] rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Tambah Admin</button>
                </div>
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Admin</th><th className="text-left px-4 py-3">Region</th><th className="text-left px-4 py-3">Role</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {list.map((a) => (
                                    <tr key={a.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3"><div className="flex items-center gap-3"><img src={a.avatar} alt={a.nama} className="w-8 h-8 rounded-full object-cover" /><div><p className="font-medium text-[#0F172A]">{a.nama}</p><p className="text-xs text-[#64748B]">{a.email}</p></div></div></td>
                                        <td className="px-4 py-3"><span className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">{a.region}</span></td>
                                        <td className="px-4 py-3 text-xs">admin_wilayah</td>
                                        <td className="px-4 py-3">
                                            <button type="button" onClick={()=>toggleStatus(a)} className={`text-xs font-medium px-2 py-1 rounded-full ${a.status === 'Aktif' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{a.status}</button>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={()=>openEdit(a)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2 py-1.5 rounded-lg">Edit</button>
                                                <button type="button" onClick={()=>handleReset(a)} className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1.5 rounded-lg">Reset</button>
                                                <button type="button" onClick={()=>remove(a.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-2 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">Hanya Super Admin Makassar bisa kelola Admin Wilayah</div>
                </div>

                {open && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={close}>
                        <div className="bg-white rounded-2xl w-full max-w-[480px] shadow-xl" onClick={(e)=>e.stopPropagation()}>
                            <div className="px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editing ? 'Edit Admin Wilayah' : 'Tambah Admin Wilayah'}</h3>
                                <button type="button" onClick={close} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama</label>
                                    <input value={form.nama} onChange={(e)=>setForm({...form, nama:e.target.value})} placeholder="Admin Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Email (login)</label>
                                    <input value={form.email} onChange={(e)=>setForm({...form, email:e.target.value})} placeholder="admin.gowa@bbws-pj.go.id" type="email" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Region (assign 1 wilayah)</label>
                                    <select value={form.region} onChange={(e)=>setForm({...form, region:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10">
                                        {regions.map((r)=><option key={r} value={r}>{r}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">{editing ? 'Password baru (kosongkan jika tidak ganti)' : 'Password awal'}</label>
                                    <input value={form.password} onChange={(e)=>setForm({...form, password:e.target.value})} placeholder="Min. 8 karakter" type="password" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={close} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={save} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
