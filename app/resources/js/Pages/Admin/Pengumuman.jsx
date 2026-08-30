import AdminLayout from '@/Layouts/AdminLayout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }
const OWN_REGION = 'Kab. Gowa';

const initial = [
    { id: 1, judul: 'Apel Pagi Senin — Pusat', konten: 'Apel pukul 07:30 di halaman kantor pusat.', scope: 'Global', region: '', pin: true, tgl: '24 Agu 2026', stat: 'Terkirim 24 kantor' },
    { id: 2, judul: 'Pemeliharaan Jalan — Gowa', konten: 'Penutupan sementara ruas poros 08:00–16:00.', scope: 'Wilayah', region: 'Kab. Gowa', pin: false, tgl: '23 Agu 2026', stat: 'Terkirim 72 karyawan' },
    { id: 3, judul: 'Jadwal Cuti Bersama', konten: 'Cuti bersama nasional — lihat kalender.', scope: 'Global', region: '', pin: false, tgl: '20 Agu 2026', stat: 'Terkirim 24 kantor' },
];
const regions = ['','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];
const empty = { judul:'', konten:'', scope:'Global', region:'', pin:false };
const LS_PENGUMUMAN = 'bbws_mock_pengumuman_v3';
function loadPengumuman() { try { const raw = localStorage.getItem(LS_PENGUMUMAN); if (raw) return JSON.parse(raw); } catch {} return initial; }
function savePengumuman(list) { try { localStorage.setItem(LS_PENGUMUMAN, JSON.stringify(list)); } catch {} }

export default function PengumumanAdmin() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [list, setList] = useState(() => loadPengumuman());
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(isWilayah ? { ...empty, scope: 'Wilayah', region: OWN_REGION } : empty);
    const [toast, setToast] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState(null);

    const openAdd = () => { setEditing(null); setForm(isWilayah ? { ...empty, scope: 'Wilayah', region: OWN_REGION } : empty); setOpen(true); };
    const openEdit = (p) => {
        if (isWilayah && p.scope === 'Global') { setToast('Admin Wilayah tidak bisa edit Global'); setTimeout(()=>setToast(null),2000); return; }
        setEditing(p); setForm({ judul:p.judul, konten:p.konten, scope:p.scope, region:p.region||'', pin:p.pin }); setOpen(true);
    };
    const close = () => setOpen(false);

    const save = () => {
        if (isWilayah && form.scope === 'Global') { setToast('Admin Wilayah tidak boleh buat Global'); setTimeout(()=>setToast(null),2000); return; }
        if (!form.judul.trim() || !form.konten.trim()) { setToast('Judul & konten wajib'); setTimeout(()=>setToast(null),2000); return; }
        if (form.scope==='Wilayah') {
            if (isWilayah && form.region !== OWN_REGION) { setToast(`Hanya boleh ${OWN_REGION}`); setTimeout(()=>setToast(null),2000); return; }
            if (!form.region) { setToast('Pilih wilayah'); setTimeout(()=>setToast(null),2000); return; }
        }
        const tgl = new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
        if (editing) {
            const next = list.map((x)=>x.id===editing.id ? { ...x, judul: form.judul, konten: form.konten, scope: form.scope, region: form.scope==='Wilayah' ? form.region : '', pin: form.pin, tgl } : x);
            setList(next); savePengumuman(next); setToast('Pengumuman diperbarui');
        } else {
            const nextItem = { id: Date.now(), judul: form.judul, konten: form.konten, scope: form.scope, region: form.scope==='Wilayah' ? form.region : '', pin: form.pin, tgl, stat: form.scope==='Global' ? 'Terkirim 24 kantor' : `Terkirim ${form.region}` };
            const next = [nextItem, ...list]; setList(next); savePengumuman(next); setToast('Pengumuman dibuat');
        }
        setOpen(false); setTimeout(()=>setToast(null),2000);
    };
    const remove = (id) => {
        const target = list.find((x)=>x.id===id);
        if (!target) return;
        if (isWilayah && target.scope === 'Global') { setToast('Tidak bisa hapus Global'); setTimeout(()=>setToast(null),2000); return; }
        setConfirmDelete(target);
    };
    const confirmRemove = () => {
        if (!confirmDelete) return;
        const next = list.filter((x)=>x.id!==confirmDelete.id); setList(next); savePengumuman(next); setToast(`"${confirmDelete.judul}" dihapus`); setConfirmDelete(null); setTimeout(()=>setToast(null),2000);
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Pengumuman — ${OWN_REGION}` : 'Pengumuman'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `Hanya Wilayah ${OWN_REGION} • tidak bisa Global atau wilayah lain` : 'Super Admin broadcast Global • Admin Wilayah targeted region • Pinned'}</p>
                    </div>
                    <button type="button" onClick={openAdd} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Buat Pengumuman</button>
                </div>
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Judul</th><th className="text-left px-4 py-3">Scope</th><th className="text-left px-4 py-3">Tanggal</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {list.map((p) => (
                                    <tr key={p.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3"><span className="font-medium text-[#0F172A]">{p.pin ? '📌 ' : ''}{p.judul}</span><p className="text-xs text-[#64748B] line-clamp-1">{p.konten}</p></td>
                                        <td className="px-4 py-3"><span className={`text-xs font-medium px-2 py-1 rounded-full ${p.scope === 'Global' ? 'bg-[#0F172A] text-white' : 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/20'}`}>{p.scope}{p.region ? ` • ${p.region}` : ''}</span></td>
                                        <td className="px-4 py-3 text-xs text-[#64748B]">{p.tgl}</td>
                                        <td className="px-4 py-3 text-xs text-[#64748B]">{p.stat}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={()=>openEdit(p)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2 py-1.5 rounded-lg">Edit</button>
                                                <button type="button" onClick={()=>remove(p.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-2 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                {confirmDelete && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => setConfirmDelete(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[400px] shadow-xl" onClick={(e)=>e.stopPropagation()}>
                            <div className="px-6 py-4">
                                <h3 className="font-semibold text-[#0F172A]">Hapus pengumuman?</h3>
                                <p className="text-sm text-[#64748B] mt-1">“{confirmDelete.judul}” — {confirmDelete.scope}{confirmDelete.region ? ` • ${confirmDelete.region}` : ''}</p>
                                <p className="text-xs text-[#94A3B8] mt-2">Pengumuman akan dihapus. Tidak dapat dibatalkan.</p>
                            </div>
                            <div className="px-6 pb-5 flex gap-2">
                                <button type="button" onClick={() => setConfirmDelete(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                <button type="button" onClick={confirmRemove} className="flex-1 rounded-xl bg-[#EF4444] text-white py-3 text-sm font-semibold">Ya, hapus</button>
                            </div>
                        </div>
                    </div>
                )}

                {open && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={close}>
                        <div className="bg-white rounded-2xl w-full max-w-[560px] shadow-xl" onClick={(e)=>e.stopPropagation()}>
                            <div className="px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editing ? 'Edit Pengumuman' : 'Buat Pengumuman'}</h3>
                                <button type="button" onClick={close} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Judul</label>
                                    <input value={form.judul} onChange={(e)=>setForm({...form, judul:e.target.value})} placeholder="Apel pagi Senin — Pusat" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Konten</label>
                                    <textarea value={form.konten} onChange={(e)=>setForm({...form, konten:e.target.value})} rows={3} placeholder="Isi pengumuman..." className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10 placeholder:text-[#94A3B8]" />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Scope {isWilayah && <span className="text-[#94A3B8]">— Wilayah only</span>}</label>
                                        {isWilayah ? (
                                            <div className="mt-1.5 w-full rounded-xl bg-[#F1F5F9] px-3 py-2.5 text-sm text-[#0F172A] font-medium">Wilayah targeted — {OWN_REGION}</div>
                                        ) : (
                                            <select value={form.scope} onChange={(e)=>setForm({...form, scope:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                                <option value="Global">Global (semua kantor)</option>
                                                <option value="Wilayah">Wilayah targeted</option>
                                            </select>
                                        )}
                                    </div>
                                    {form.scope==='Wilayah' && (
                                        <div>
                                            <label className="text-xs font-medium text-[#334155]">Wilayah {isWilayah && <span className="text-[#94A3B8]">— terkunci</span>}</label>
                                            {isWilayah ? (
                                                <div className="mt-1.5 w-full rounded-xl bg-[#F1F5F9] px-3 py-2.5 text-sm text-[#0F172A] font-medium">{OWN_REGION}</div>
                                            ) : (
                                                <select value={form.region} onChange={(e)=>setForm({...form, region:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                                    <option value="">Pilih wilayah</option>
                                                    {regions.filter(Boolean).map((r)=><option key={r} value={r}>{r}</option>)}
                                                </select>
                                            )}
                                        </div>
                                    )}
                                </div>
                                <label className="flex items-center gap-2 text-sm text-[#334155] cursor-pointer">
                                    <input type="checkbox" checked={form.pin} onChange={(e)=>setForm({...form, pin:e.target.checked})} className="rounded accent-[#FCB833]" /> Pinned (disematkan di atas)
                                </label>
                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={close} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={save} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">{editing ? 'Simpan' : 'Terbitkan'}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
