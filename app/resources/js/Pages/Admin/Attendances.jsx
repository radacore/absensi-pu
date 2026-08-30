import AdminLayout from '@/Layouts/AdminLayout';
import { useState, useMemo } from 'react';

const wilayahList = ['Semua','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];

const initial = [
    { id: 1, nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', datang: '07:52', pulang: '16:12', status: 'late', love: 'pending', jarak: 42, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 2, nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', wilayah: 'Kota Makassar', kantor: 'Kantor Pusat', datang: '07:38', pulang: '16:05', status: 'on_time', love: null, jarak: 38, lat: -5.1477, lng: 119.4327, foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 3, nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', wilayah: 'Kab. Maros', kantor: 'Kantor Maros', datang: '07:40', pulang: '16:05', status: 'on_time', love: null, jarak: 21, lat: -5.005, lng: 119.58, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 4, nama: 'Rina Wati', email: 'rina@bbws-pj.go.id', wilayah: 'Kab. Gowa', kantor: 'Kantor Gowa', datang: '07:48', pulang: '', status: 'late', love: 'approved', jarak: 18, lat: -5.3114, lng: 119.42, foto: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 5, nama: 'Rudi Hartono', email: 'rudi@bbws-pj.go.id', wilayah: 'Kab. Bone', kantor: 'Kantor Bone', datang: '07:55', pulang: '15:40', status: 'excused_love', love: 'approved', jarak: 28, lat: -4.54, lng: 120.33, foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=face&auto=format' },
    { id: 6, nama: 'Dewi Lestari', email: 'dewi@bbws-pj.go.id', wilayah: 'Kab. Takalar', kantor: 'Kantor Takalar', datang: '08:05', pulang: '', status: 'late', love: null, jarak: 95, lat: -5.41, lng: 119.44, foto: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=200&h=200&fit=crop&crop=face&auto=format', selfie: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&h=400&fit=crop&crop=face&auto=format' },
];

const statusLabel = { on_time: 'Tepat waktu', late: 'Terlambat', excused_love: 'Love', early_leave: 'Pulang awal' };
const statusTone = { on_time: 'bg-[#ECFDF5] text-[#065F46]', late: 'bg-[#FFF7E6] text-[#92400E]', excused_love: 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30', early_leave: 'bg-[#FEF2F2] text-[#991B1B]' };

export default function Attendances() {
    const [q, setQ] = useState('');
    const [wilayah, setWilayah] = useState('Semua');
    const [status, setStatus] = useState('Semua');
    const [tgl, setTgl] = useState('2026-08-24');
    const [detail, setDetail] = useState(null);
    const [toast, setToast] = useState(null);

    const filtered = useMemo(() => initial.filter((r) => {
        if (wilayah !== 'Semua' && r.wilayah !== wilayah) return false;
        if (status !== 'Semua' && r.status !== status) return false;
        if (q && !r.nama.toLowerCase().includes(q.toLowerCase()) && !r.email.toLowerCase().includes(q.toLowerCase())) return false;
        return true;
    }), [q, wilayah, status]);

    const stats = { hadir: filtered.length, late: filtered.filter((r)=>r.status==='late').length, love: filtered.filter((r)=>r.love).length };

    const handleExport = () => { setToast('Export CSV — frontend only (akan generate S3 /rekap/... )'); setTimeout(()=>setToast(null),2000); };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Absensi</h1>
                        <p className="text-sm text-[#64748B]">Filter per wilayah / tanggal / status • Di luar radius ditolak 422 (tidak tercatat) • 1–3 lokasi per wilayah</p>
                    </div>
                    <button type="button" onClick={handleExport} className="bg-white border border-[#E2E8F0] rounded-xl px-4 py-2.5 text-sm font-medium text-[#334155] shrink-0">⬇ Export CSV</button>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.hadir}</p><p className="text-xs text-[#64748B]">Hadir (filter)</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#10B981]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.late}</p><p className="text-xs text-[#64748B]">Terlambat</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                    <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] text-center"><p className="text-xl font-semibold text-[#0F172A]">{stats.love}</p><p className="text-xs text-[#64748B]">Pakai Love</p><span className="mt-1 inline-block w-6 h-1 rounded-full bg-[#FCB833]"></span></div>
                </div>

                <div className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)] flex flex-wrap gap-2 items-center">
                    <input type="date" value={tgl} onChange={(e)=>setTgl(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        {wilayahList.map((w)=><option key={w} value={w}>{w}</option>)}
                    </select>
                    <select value={status} onChange={(e)=>setStatus(e.target.value)} className="rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                        <option value="Semua">Semua status</option>
                        <option value="on_time">Tepat waktu</option>
                        <option value="late">Terlambat</option>
                        <option value="excused_love">Love (excused)</option>
                        <option value="early_leave">Pulang awal</option>
                    </select>
                    <input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Cari nama / email..." className="flex-1 min-w-[160px] rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                    <span className="text-xs text-[#94A3B8]">{filtered.length} hasil • {tgl}</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Waktu Datang</th><th className="text-left px-4 py-3">Waktu Pulang</th><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Jarak</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => (
                                    <tr key={r.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3"><div className="flex items-center gap-3"><img src={r.foto} alt={r.nama} className="w-8 h-8 rounded-full object-cover" /><div><p className="font-medium text-[#0F172A]">{r.nama}</p><p className="text-xs text-[#64748B]">{r.email}</p></div></div></td>
                                        <td className="px-4 py-3 text-xs font-medium text-[#0F172A]">{r.datang} WITA</td>
                                        <td className="px-4 py-3 text-xs text-[#64748B]">{r.pulang ? `${r.pulang} WITA` : '—'}</td>
                                        <td className="px-4 py-3 text-xs"><span className="bg-[#F1F5F9] px-2 py-1 rounded-full">{r.wilayah}</span></td>
                                        <td className="px-4 py-3 text-xs"><span className={`px-2 py-1 rounded-full font-medium ${r.jarak <= 200 ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{r.jarak} m</span></td>
                                        <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${statusTone[r.status]}`}>{statusLabel[r.status]}{r.love ? ` • ${r.love}` : ''}</span></td>
                                        <td className="px-4 py-3 text-right"><button type="button" onClick={()=>setDetail(r)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Detail selfie</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada data untuk filter ini</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>Super Admin lihat semua • Admin Wilayah lihat own region saja</span>
                        <span>Selfie S3 /attendance/... • Jam global 07:30–16:00 WITA • Toleransi 15m</span>
                    </div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                {detail && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={()=>setDetail(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[520px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e)=>e.stopPropagation()}>
                            <div className="px-5 py-4 flex items-center justify-between border-b sticky top-0 bg-white rounded-t-2xl">
                                <div>
                                    <h3 className="font-semibold text-[#0F172A]">{detail.nama}</h3>
                                    <p className="text-xs text-[#64748B]">{detail.wilayah} • {detail.kantor} • {detail.jarak} m dari kantor • {statusLabel[detail.status]}</p>
                                </div>
                                <button type="button" onClick={()=>setDetail(null)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="p-5 space-y-4">
                                <img src={detail.selfie} alt="selfie" className="w-full h-[280px] object-cover rounded-xl" />
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu datang</p><p className="font-medium text-[#0F172A]">{detail.datang} WITA</p></div>
                                    <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Waktu pulang</p><p className="font-medium text-[#0F172A]">{detail.pulang || '— belum pulang'}</p></div>
                                    <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Koordinat</p><p className="font-mono text-xs text-[#0F172A]">{detail.lat.toFixed(4)}, {detail.lng.toFixed(4)}</p></div>
                                    <div className="bg-[#F8FAFC] rounded-xl p-3"><p className="text-xs text-[#94A3B8]">Jarak ke kantor</p><p className={`font-medium ${detail.jarak <= 200 ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>{detail.jarak} m • {detail.jarak <= 200 ? 'Dalam radius' : 'Di luar radius'}</p></div>
                                </div>
                                <div className="bg-[#FFF7E6] rounded-xl p-3 flex items-center justify-between">
                                    <p className="text-xs text-[#92400E]">Status: {statusLabel[detail.status]} {detail.love ? `• Love ${detail.love}` : ''}</p>
                                    <span className={`text-xs font-medium px-2 py-1 rounded-full ${statusTone[detail.status]}`}>{statusLabel[detail.status]}</span>
                                </div>
                                <p className="text-xs text-[#94A3B8] text-center">Foto selfie S3 /attendance/{detail.id}/... • Jam global 07:30 toleransi 15m → ≤07:45 on_time</p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
