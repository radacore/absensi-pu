import AdminLayout from '@/Layouts/AdminLayout';
import { router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function actionLabel(a) {
    const map = {
        'employee.create': 'Tambah Karyawan',
        'employee.update': 'Perbarui Karyawan',
        'employee.delete': 'Hapus Karyawan',
        'employee.reset_password': 'Reset Kata Sandi',
        'admin_wilayah.create': 'Tambah Admin Wilayah',
        'admin_wilayah.update': 'Perbarui Admin Wilayah',
        'admin_wilayah.activate': 'Aktifkan Admin Wilayah',
        'admin_wilayah.deactivate': 'Nonaktifkan Admin Wilayah',
        'admin_wilayah.delete': 'Hapus Admin Wilayah',
        'cuti.approve_level': 'Approve Cuti (level)',
        'cuti.approve_final': 'Approve Cuti (final)',
        'cuti.reject': 'Tolak Cuti',
        'cuti.delete': 'Hapus Cuti',
        'love.approve': 'Approve Toleransi',
        'love.reject': 'Tolak Toleransi',
        'love.delete': 'Hapus Toleransi',
        'pengumuman.delete': 'Hapus Pengumuman',
    };
    return map[a] || a;
}

function actionTone(a) {
    if (a.endsWith('.delete') || a.endsWith('.deactivate')) return 'bg-[#FEE2E2] text-[#991B1B] border-[#FCA5A5]';
    if (a.endsWith('.reject')) return 'bg-[#FFEDD5] text-[#9A3412] border-[#FDBA74]';
    if (a.includes('reset_password')) return 'bg-[#FEF3C7] text-[#92400E] border-[#FCD34D]';
    if (a.includes('approve') || a.endsWith('.activate') || a.endsWith('.create')) return 'bg-[#DCFCE7] text-[#065F46] border-[#86EFAC]';
    return 'bg-[#EFF6FF] text-[#1E3A8A] border-[#BFDBFE]';
}

export default function AuditLog() {
    const { props } = usePage();
    const logs = props.logs ?? [];
    const actions = props.actions ?? [];
    const initial = props.filters ?? {};

    const [form, setForm] = useState({
        action: initial.action || '',
        actor: initial.actor || '',
        from: initial.from || '',
        to: initial.to || '',
        q: initial.q || '',
    });

    const submit = (e) => {
        e?.preventDefault?.();
        const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
        router.get('/super-admin/audit-log', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => {
        setForm({ action: '', actor: '', from: '', to: '', q: '' });
        router.get('/super-admin/audit-log', {}, { preserveState: false, preserveScroll: true });
    };

    const summary = useMemo(() => `${logs.length} baris terakhir`, [logs.length]);

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Audit Log</h1>
                        <p className="text-sm text-[#64748B]">Catatan aksi sensitif oleh Admin dan Super Admin</p>
                        <p className="text-xs text-[#94A3B8] mt-1">Reset password, delete data, approve/reject cuti dan toleransi, CRUD Admin Wilayah.</p>
                    </div>
                    <span className="text-xs bg-[#F1F5F9] text-[#334155] rounded-full px-3 py-1.5 shrink-0">{summary}</span>
                </div>

                <form onSubmit={submit} className="bg-white rounded-2xl p-4 grid grid-cols-2 lg:grid-cols-6 gap-3 items-end shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <label className="text-xs font-medium text-[#334155] col-span-2 lg:col-span-2">
                        Aksi
                        <select value={form.action} onChange={(e) => setForm({ ...form, action: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="">Semua aksi</option>
                            {actions.map((a) => <option key={a} value={a}>{actionLabel(a)}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-[#334155]">
                        Aktor
                        <input value={form.actor} onChange={(e) => setForm({ ...form, actor: e.target.value })} placeholder="Nama admin" className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                    </label>
                    <label className="text-xs font-medium text-[#334155]">
                        Dari
                        <input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                    </label>
                    <label className="text-xs font-medium text-[#334155]">
                        Sampai
                        <input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                    </label>
                    <div className="flex gap-2 col-span-2 lg:col-span-1">
                        <button type="submit" className="flex-1 rounded-xl bg-[#0F172A] text-white text-sm font-semibold py-2 hover:bg-[#1E3A8A] transition">Filter</button>
                        <button type="button" onClick={reset} className="rounded-xl bg-[#F1F5F9] text-[#334155] text-sm font-semibold py-2 px-3 hover:bg-[#E2E8F0] transition">Reset</button>
                    </div>
                    <label className="text-xs font-medium text-[#334155] col-span-2 lg:col-span-6">
                        Cari deskripsi atau subjek
                        <input value={form.q} onChange={(e) => setForm({ ...form, q: e.target.value })} placeholder="Cari kata kunci..." className="mt-1 block w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none" />
                    </label>
                </form>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr>
                                    <th className="text-left px-4 py-3">Waktu</th>
                                    <th className="text-left px-4 py-3">Aktor</th>
                                    <th className="text-left px-4 py-3">Aksi</th>
                                    <th className="text-left px-4 py-3">Subjek</th>
                                    <th className="text-left px-4 py-3">Deskripsi</th>
                                    <th className="text-left px-4 py-3">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {logs.map((log) => (
                                    <tr key={log.id} className="hover:bg-[#F8FAFC]/60">
                                        <td className="px-4 py-3 text-xs font-mono text-[#334155] whitespace-nowrap">{log.createdAt}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-[#0F172A]">{log.actorName || '—'}</p>
                                            <p className="text-xs text-[#94A3B8]">{log.actorType || 'sistem'}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block text-xs font-medium px-2.5 py-1 rounded-full border ${actionTone(log.action)}`}>{actionLabel(log.action)}</span>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <p className="font-medium text-[#0F172A]">{log.subjectLabel || '—'}</p>
                                            <p className="text-[#94A3B8]">{log.subjectType || '—'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-[#334155]">{log.description || '—'}</td>
                                        <td className="px-4 py-3 text-xs font-mono text-[#64748B]">{log.ip || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {logs.length === 0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada catatan sesuai filter.</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">Menampilkan maksimal 500 entri terbaru.</div>
                </div>
            </div>
        </AdminLayout>
    );
}
