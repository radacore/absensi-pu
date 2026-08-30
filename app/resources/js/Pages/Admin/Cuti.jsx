import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }
const OWN_REGION = 'Kab. Gowa';

const wilayahList = ['Semua','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];

const initial = [
    { id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', jenis: 'Tahunan', tgl: '28–30 Agu', alasan: 'Acara keluarga', status: 'Menunggu', level: 2 },
    { id: 2, nama: 'Rudi Hartono', email: 'rudi@bbws-pj.go.id', wilayah: 'Kab. Bone', jenis: 'Sakit', tgl: '23 Agu', alasan: 'Demam — surat dokter', status: 'Menunggu', level: 1 },
    { id: 3, nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', wilayah: 'Kota Makassar', jenis: 'Besar', tgl: '20 Agu', alasan: 'Haji', status: 'Disetujui', level: 3 },
    { id: 4, nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', wilayah: 'Kab. Maros', jenis: 'Tahunan', tgl: '25–26 Agu', alasan: 'Keperluan pribadi', status: 'Ditolak', level: 1 },
    { id: 5, nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', wilayah: 'Kab. Takalar', jenis: 'Melahirkan', tgl: '15 Agu–15 Nov', alasan: 'Cuti melahirkan', status: 'Menunggu', level: 2 },
];

export default function CutiAdmin() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [status, setStatus] = useState('Semua');

    const baseList = isWilayah ? initial.filter((c) => c.wilayah === OWN_REGION) : initial;
    const filtered = useMemo(() => baseList.filter((c) => {
        if (!isWilayah && wilayah !== 'Semua' && c.wilayah !== wilayah) return false;
        if (isWilayah && wilayah !== OWN_REGION) return false;
        if (status !== 'Semua' && c.status !== status) return false;
        if (q && !c.nama.toLowerCase().includes(q.toLowerCase()) && !c.email.toLowerCase().includes(q.toLowerCase()) && !c.jenis.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [q, wilayah, status, baseList, isWilayah]);

    const counts = { pending: initial.filter((c)=>c.status==='Menunggu').length, approved: initial.filter((c)=>c.status==='Disetujui').length, rejected: initial.filter((c)=>c.status==='Ditolak').length };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Cuti — ${OWN_REGION}` : 'Cuti — Berjenjang'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `Approve own region level sesuai kewenangan • ${OWN_REGION}` : 'Atasan → Admin Wilayah → Kantor Pusat • 3 level, reject terminal • Approval sesuai level & wilayah'}</p>
                    </div>
                    <span className="hidden lg:inline-flex bg-[#F8FAFC] border border-[#E2E8F0] text-xs font-medium px-3 py-1.5 rounded-full text-[#64748B]">{filtered.length} hasil • {counts.pending} menunggu</span>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.pending}</p><p className="text-xs text-[#64748B]">Menunggu</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.approved}</p><p className="text-xs text-[#64748B]">Disetujui</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#10B981]"></span></div>
                    <div className="bg-white rounded-2xl p-4 text-center shadow-[0_2px_16px_rgba(15,23,42,0.04)]"><p className="text-xl font-semibold text-[#0F172A]">{counts.rejected}</p><p className="text-xs text-[#64748B]">Ditolak</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#EF4444]"></span></div>
                </div>

                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex flex-wrap gap-2 items-center">
                    {isWilayah ? (
                        <span className="rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm font-medium text-[#0F172A]">{OWN_REGION}</span>
                    ) : (
                        <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            {wilayahList.map((w)=><option key={w} value={w}>{w}</option>)}
                        </select>
                    )}
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="Menunggu">Menunggu</option>
                        <option value="Disetujui">Disetujui</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari nama / email / jenis..." className="flex-1 min-w-[180px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} dari {initial.length}</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Jenis</th><th className="text-left px-4 py-3">Tanggal</th><th className="text-left px-4 py-3">Level</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((c) => (
                                    <tr key={c.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3"><p className="font-medium text-[#0F172A]">{c.nama}</p><p className="text-xs text-[#64748B]">{c.email}</p><p className="text-xs text-[#94A3B8] line-clamp-1">{c.alasan}</p></td>
                                        <td className="px-4 py-3 text-xs"><span className="bg-[#F1F5F9] px-2 py-1 rounded-full">{c.wilayah}</span></td>
                                        <td className="px-4 py-3 text-xs"><span className="bg-[#EFF6FF] text-[#1E3A8A] px-2 py-1 rounded-full font-medium">{c.jenis}</span></td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">{c.tgl}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-1">
                                                {[1,2,3].map((l) => (<span key={l} className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium ${l < c.level ? 'bg-[#0F172A] text-white' : l === c.level ? 'bg-[#FCB833] text-[#0F172A]' : 'bg-[#F1F5F9] text-[#94A3B8]'}`}>{l}</span>))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${c.status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : c.status === 'Menunggu' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span></td>
                                        <td className="px-4 py-3 text-right"><Link href={`${base}/cuti/${c.id}`} className="text-xs font-medium bg-[#0F172A] text-white px-3 py-1.5 rounded-lg inline-block hover:bg-[#1E3A8A]">Review</Link></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada data untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{isWilayah ? `Admin Wilayah: review & approve hanya ${OWN_REGION} • level sesuai kewenangan` : 'Super Admin approve semua level • Admin Wilayah approve own region level sesuai kewenangan'}</span>
                        <span>Reject terminal — tidak lanjut level berikutnya</span>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
