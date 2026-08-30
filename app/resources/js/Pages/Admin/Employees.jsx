import AdminLayout from '@/Layouts/AdminLayout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }
const OWN_REGION = 'Kab. Gowa';

const initial = [
    { id: 1, nik: '7371001234567890', nip: '198501012010011001', nama: 'Andi Saputra', email: 'andi@bbws-pj.go.id', gol: 'III/a', jabatan: 'Staff Teknik', unit: 'Bidang Jalan', status: 'PNS', kantor: 'Gowa', region: 'Kab. Gowa', foto: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face&auto=format' },
    { id: 2, nik: '7371001234567891', nip: '199002022015022002', nama: 'Siti Rahma', email: 'siti@bbws-pj.go.id', gol: 'III/b', jabatan: 'Analis Data', unit: 'Bidang Air', status: 'PPPK', kantor: 'Makassar', region: 'Kota Makassar', foto: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop&crop=face&auto=format' },
    { id: 3, nik: '7371001234567892', nip: '', nama: 'Budi Santoso', email: 'budi@bbws-pj.go.id', gol: '-', jabatan: 'Operator', unit: 'Bidang Jalan', status: 'Kontrak', kantor: 'Maros', region: 'Kab. Maros', foto: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face&auto=format' },
];

const regions = ['Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];
const empty = { nik:'', nip:'', nama:'', email:'', gol:'-', jabatan:'', unit:'', status:'PNS', region: regions[1] };

export default function Employees() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [list, setList] = useState(initial);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ ...empty, region: isWilayah ? OWN_REGION : empty.region });
    const [toast, setToast] = useState(null);
    const [filter, setFilter] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [photo, setPhoto] = useState(null);
    const [preview, setPreview] = useState(null);

    const scopedList = isWilayah ? list.filter((e) => e.region === OWN_REGION) : list;
    const filtered = (() => {
        const baseList = scopedList;
        if (!isWilayah && filter === 'Semua') return baseList;
        if (isWilayah) {
            if (filter === OWN_REGION) return baseList;
            return baseList.filter((e) => e.status === filter);
        }
        return baseList.filter((e) => e.region === filter || e.status === filter);
    })();

    const openAdd = () => { setEditing(null); setForm(empty); setPhoto(null); setPreview(null); setOpen(true); };
    const openEdit = (e) => { setEditing(e); setForm({ nik:e.nik, nip:e.nip||'', nama:e.nama, email:e.email, gol:e.gol, jabatan:e.jabatan, unit:e.unit, status:e.status, region:e.region }); setPreview(e.foto); setPhoto(null); setOpen(true); };
    const close = () => setOpen(false);

    const onPhoto = (ev) => {
        const f = ev.target.files?.[0]; if (!f) return; setPhoto(f);
        const r = new FileReader(); r.onload = () => setPreview(r.result); r.readAsDataURL(f);
    };

    const save = () => {
        if (isWilayah && form.region !== OWN_REGION) { setToast(`Admin Wilayah hanya boleh di ${OWN_REGION}`); setTimeout(()=>setToast(null),2000); return; }
        if (!form.nama.trim() || !form.email.trim() || !/^\S+@\S+\.\S+$/.test(form.email)) { setToast('Nama & email valid wajib'); setTimeout(()=>setToast(null),2000); return; }
        if (!/^\d{16}$/.test(form.nik)) { setToast('NIK 16 digit'); setTimeout(()=>setToast(null),2000); return; }
        if (editing) {
            if (isWilayah && editing.region !== OWN_REGION) { setToast('Tidak bisa edit karyawan luar wilayah'); setTimeout(()=>setToast(null),2000); return; }
            setList((l) => l.map((x) => x.id===editing.id ? { ...x, nik:form.nik, nip:form.nip, nama:form.nama, email:form.email, gol:form.gol, jabatan:form.jabatan, unit:form.unit, status:form.status, region:form.region, kantor:form.region.replace('Kab. ','').replace('Kota ',''), foto: preview||x.foto } : x));
            setToast('Karyawan diperbarui (frontend only)');
        } else {
            const next = { id: Date.now(), nik:form.nik, nip:form.nip, nama:form.nama, email:form.email, gol:form.gol, jabatan:form.jabatan, unit:form.unit, status:form.status, region:form.region, kantor:form.region.replace('Kab. ','').replace('Kota ',''), foto: preview || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face&auto=format' };
            setList((l) => [...l, next]); setToast('Karyawan ditambah (frontend only)');
        }
        setOpen(false); setTimeout(()=>setToast(null),2000);
    };
    const handleReset = (e) => {
        if (isWilayah && e.region !== OWN_REGION) { setToast('Hanya own region'); setTimeout(()=>setToast(null),2000); return; }
        setToast(`Reset password — link kirim ke ${e.email} (Admin/Super Admin, frontend only)`); setTimeout(()=>setToast(null),3000);
    };
    const remove = (id) => {
        const target = list.find((x)=>x.id===id);
        if (isWilayah && target && target.region !== OWN_REGION) { setToast('Hanya own region'); setTimeout(()=>setToast(null),2000); return; }
        setList((l)=>l.filter((x)=>x.id!==id)); setToast('Dihapus (frontend only)'); setTimeout(()=>setToast(null),2000);
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Karyawan — ${OWN_REGION}` : 'Karyawan — Lengkap HR'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `${scopedList.length} karyawan own region • ${OWN_REGION}` : `${list.length} karyawan • email UK • NIP UK jika ada • Foto S3`}</p>
                        {isWilayah && <span className="inline-block mt-1 text-xs bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">Write own region saja</span>}
                    </div>
                    <button type="button" onClick={openAdd} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Tambah Karyawan</button>
                </div>

                <div className="flex gap-2 overflow-x-auto pb-1">
                    {isWilayah ? ['Kab. Gowa','PNS','PPPK','Kontrak'].map((f) => (
                        <button key={f} type="button" onClick={() => setFilter(f)} className={`shrink-0 text-xs font-medium px-3 py-1.5 rounded-full border ${filter===f ? 'bg-[#0F172A] text-white border-[#0F172A]' : 'bg-white text-[#64748B] border-[#E2E8F0]'}`}>{f}</button>
                    )) : ['Semua','Kab. Gowa','Kota Makassar','PNS','PPPK','Kontrak'].map((f) => (
                        <button key={f} type="button" onClick={() => setFilter(f)} className={`shrink-0 text-xs font-medium px-3 py-1.5 rounded-full border ${filter===f ? 'bg-[#0F172A] text-white border-[#0F172A]' : 'bg-white text-[#64748B] border-[#E2E8F0]'}`}>{f}</button>
                    ))}
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">NIK / NIP</th><th className="text-left px-4 py-3">Jabatan</th><th className="text-left px-4 py-3">Wilayah</th><th className="px-4 py-3"></th></tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((e) => (
                                    <tr key={e.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <img src={e.foto} alt={e.nama} className="w-8 h-8 rounded-full object-cover" />
                                                <div>
                                                    <p className="font-medium text-[#0F172A]">{e.nama}</p>
                                                    <p className="text-xs text-[#64748B]">{e.gol} • {e.unit} • {e.status}</p>
                                                    <p className="text-xs text-[#94A3B8]">{e.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs"><span className="font-mono text-[#0F172A]">{e.nik}</span><br /><span className="text-[#94A3B8]">{e.nip || '-'}</span></td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">{e.jabatan}</td>
                                        <td className="px-4 py-3 text-xs"><span className="bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">{e.region}</span></td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={()=>openEdit(e)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Edit</button>
                                                <button type="button" onClick={()=>handleReset(e)} className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1.5 rounded-lg">Reset</button>
                                                <button type="button" onClick={()=>remove(e.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">{isWilayah ? `Admin Wilayah: tampil & write hanya ${OWN_REGION} — reset password own saja` : 'Super Admin write all • Admin Wilayah write own region (Reset password karyawan own region) • read all'}</div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2">{toast}</p>}

                {open && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={close}>
                        <div className="bg-white rounded-2xl w-full max-w-[560px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e)=>e.stopPropagation()}>
                            <div className="sticky top-0 bg-white px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editing ? 'Edit Karyawan' : 'Tambah Karyawan'}</h3>
                                <button type="button" onClick={close} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div className="flex gap-4">
                                    <div className="w-20 h-20 rounded-full bg-[#F1F5F9] overflow-hidden flex items-center justify-center shrink-0 border-2 border-white shadow-sm">
                                        {preview ? <img src={preview} alt="preview" className="w-full h-full object-cover" /> : <span className="text-xs text-[#94A3B8]">Foto</span>}
                                    </div>
                                    <div className="flex-1">
                                        <label className="text-xs font-medium text-[#334155]">Foto (S3)</label>
                                        <input type="file" accept="image/*" onChange={onPhoto} className="mt-1.5 w-full text-xs text-[#64748B] file:mr-2 file:rounded-lg file:border-0 file:bg-[#0F172A] file:text-white file:px-3 file:py-1.5 file:text-xs" />
                                        {photo && <p className="text-xs text-[#10B981] mt-1">{photo.name}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Nama lengkap</label>
                                        <input value={form.nama} onChange={(e)=>setForm({...form, nama:e.target.value})} placeholder="Andi Saputra" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Email (UK, login)</label>
                                        <input value={form.email} onChange={(e)=>setForm({...form, email:e.target.value})} placeholder="nama@bbws-pj.go.id" type="email" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">NIK (16 digit)</label>
                                        <input value={form.nik} onChange={(e)=>setForm({...form, nik:e.target.value.replace(/\D/g,'').slice(0,16)})} placeholder="7371..." className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm font-mono outline-none" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">NIP (opsional)</label>
                                        <input value={form.nip} onChange={(e)=>setForm({...form, nip:e.target.value})} placeholder="1985..." className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm font-mono outline-none" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Golongan</label>
                                        <select value={form.gol} onChange={(e)=>setForm({...form, gol:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"><option>III/a</option><option>III/b</option><option>II/a</option><option>II/b</option><option>-</option></select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Status</label>
                                        <select value={form.status} onChange={(e)=>setForm({...form, status:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"><option>PNS</option><option>PPPK</option><option>Kontrak</option><option>Honorer</option></select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Jabatan</label>
                                        <input value={form.jabatan} onChange={(e)=>setForm({...form, jabatan:e.target.value})} placeholder="Staff Teknik" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Unit</label>
                                        <input value={form.unit} onChange={(e)=>setForm({...form, unit:e.target.value})} placeholder="Bidang Jalan" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Wilayah (region) {isWilayah && <span className="text-[#94A3B8]">— terkunci own</span>}</label>
                                        {isWilayah ? (
                                            <div className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] px-3 py-2.5 text-sm text-[#0F172A] font-medium">{OWN_REGION}</div>
                                        ) : (
                                            <select value={form.region} onChange={(e)=>setForm({...form, region:e.target.value})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                                {regions.map((r)=><option key={r} value={r}>{r}</option>)}
                                            </select>
                                        )}
                                    </div>
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
