import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }
const OWN_REGION = 'Kab. Gowa';
const MAX_SITES = 20;

const dummy = [
    { id: 1, name: 'Kota Makassar', kantor: 'Kantor Pusat', tipe: 'pusat', locations: [{ nama_lokasi: 'Bendungan Tallo — Makassar', lat: -5.1477, lng: 119.4327, radius: 300 }, { nama_lokasi: 'Jembatan Pettarani', lat: -5.156, lng: 119.44, radius: 200 }], address: 'Jl. AP Pettarani No.1 — Makassar' },
    { id: 2, name: 'Kab. Gowa', kantor: 'Kantor Wilayah Gowa', tipe: 'cabang', locations: [{ nama_lokasi: 'Bendungan Bili-Bili', lat: -5.3114, lng: 119.42, radius: 200 }, { nama_lokasi: 'Jembatan Pampang', lat: -5.32, lng: 119.45, radius: 150 }], address: 'Jl. Poros Malino — Gowa' },
    { id: 3, name: 'Kab. Maros', kantor: 'Kantor Wilayah Maros', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Maros', lat: -5.005, lng: 119.58, radius: 200 }], address: 'Jl. Poros Maros' },
    { id: 4, name: 'Kab. Bone', kantor: 'Kantor Wilayah Bone', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Bone', lat: -4.54, lng: 120.33, radius: 150 }], address: 'Jl. Ahmad Yani — Bone' },
    { id: 5, name: 'Kota Parepare', kantor: 'Kantor Wilayah Parepare', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Parepare', lat: -4.0148, lng: 119.625, radius: 200 }], address: 'Jl. Andi Makkasau — Parepare' },
    { id: 6, name: 'Kota Palopo', kantor: 'Kantor Wilayah Palopo', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Palopo', lat: -3.0014, lng: 120.192, radius: 200 }], address: 'Jl. Andi Djemma — Palopo' },
    { id: 7, name: 'Kab. Bantaeng', kantor: 'Kantor Wilayah Bantaeng', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Bantaeng', lat: -5.54, lng: 119.93, radius: 180 }], address: 'Jl. Andi Mannappiang — Bantaeng' },
    { id: 8, name: 'Kab. Barru', kantor: 'Kantor Wilayah Barru', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Barru', lat: -4.42, lng: 119.68, radius: 180 }], address: 'Jl. Sultan Hasanuddin — Barru' },
    { id: 9, name: 'Kab. Bulukumba', kantor: 'Kantor Wilayah Bulukumba', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Bulukumba', lat: -5.56, lng: 120.19, radius: 200 }], address: 'Jl. Sam Ratulangi — Bulukumba' },
    { id: 10, name: 'Kab. Enrekang', kantor: 'Kantor Wilayah Enrekang', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Enrekang', lat: -3.58, lng: 119.77, radius: 200 }], address: 'Jl. Pahlawan — Enrekang' },
    { id: 11, name: 'Kab. Jeneponto', kantor: 'Kantor Wilayah Jeneponto', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Jeneponto', lat: -5.66, lng: 119.73, radius: 200 }], address: 'Jl. Pahlawan — Jeneponto' },
    { id: 12, name: 'Kab. Kepulauan Selayar', kantor: 'Kantor Wilayah Selayar', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Selayar', lat: -6.12, lng: 120.45, radius: 250 }], address: 'Jl. Ahmad Yani — Benteng Selayar' },
    { id: 13, name: 'Kab. Luwu', kantor: 'Kantor Wilayah Luwu', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Luwu', lat: -3.39, lng: 120.38, radius: 200 }], address: 'Jl. Trans Sulawesi — Belopa' },
    { id: 14, name: 'Kab. Luwu Timur', kantor: 'Kantor Wilayah Luwu Timur', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Luwu Timur', lat: -2.60, lng: 121.10, radius: 200 }], address: 'Jl. Soekarno Hatta — Malili' },
    { id: 15, name: 'Kab. Luwu Utara', kantor: 'Kantor Wilayah Luwu Utara', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Luwu Utara', lat: -2.77, lng: 120.10, radius: 200 }], address: 'Jl. Simpurusiang — Masamba' },
    { id: 16, name: 'Kab. Pangkajene dan Kepulauan', kantor: 'Kantor Wilayah Pangkep', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Pangkep', lat: -4.84, lng: 119.54, radius: 200 }], address: 'Jl. H. Abd. Rahman — Pangkajene' },
    { id: 17, name: 'Kab. Pinrang', kantor: 'Kantor Wilayah Pinrang', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Pinrang', lat: -3.79, lng: 119.65, radius: 200 }], address: 'Jl. Bintang — Pinrang' },
    { id: 18, name: 'Kab. Sinjai', kantor: 'Kantor Wilayah Sinjai', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Sinjai', lat: -5.12, lng: 120.25, radius: 200 }], address: 'Jl. Persatuan Raya — Sinjai' },
    { id: 19, name: 'Kab. Soppeng', kantor: 'Kantor Wilayah Soppeng', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Soppeng', lat: -4.35, lng: 119.88, radius: 200 }], address: 'Jl. Lamumpatue — Watansoppeng' },
    { id: 20, name: 'Kab. Takalar', kantor: 'Kantor Wilayah Takalar', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Takalar', lat: -5.41, lng: 119.44, radius: 200 }], address: 'Jl. Syekh Yusuf — Takalar' },
    { id: 21, name: 'Kab. Tana Toraja', kantor: 'Kantor Wilayah Tana Toraja', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Tana Toraja', lat: -3.04, lng: 119.84, radius: 200 }], address: 'Jl. Pongtiku — Makale' },
    { id: 22, name: 'Kab. Toraja Utara', kantor: 'Kantor Wilayah Toraja Utara', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Toraja Utara', lat: -3.05, lng: 119.81, radius: 200 }], address: 'Jl. Poros Rantepao — Rantepao' },
    { id: 23, name: 'Kab. Wajo', kantor: 'Kantor Wilayah Wajo', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Wajo', lat: -4.12, lng: 120.03, radius: 200 }], address: 'Jl. Andi Paddanguri — Sengkang' },
    { id: 24, name: 'Kab. Sidrap', kantor: 'Kantor Wilayah Sidrap', tipe: 'cabang', locations: [{ nama_lokasi: 'Kantor Sidrap', lat: -3.94, lng: 119.79, radius: 200 }], address: 'Jl. Jenderal Sudirman — Pangkajene Sidenreng' },
];

const emptyForm = { name: '', kantor: '', tipe: 'cabang', address: '', locations: [{ nama_lokasi: '', lat: '', lng: '', radius: 200 }] };

export default function Regions() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [regions, setRegions] = useState(dummy);
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [toast, setToast] = useState(null);

    const ownRegion = dummy.find((r) => r.name === OWN_REGION) || dummy[1];
    const liveOwn = regions.find((r) => r.name === OWN_REGION) || ownRegion;
    const displayRegions = isWilayah ? [liveOwn] : regions;
    const filtered = displayRegions.filter((r) => r.name.toLowerCase().includes(q.toLowerCase()) || r.kantor.toLowerCase().includes(q.toLowerCase()));

    const openAdd = () => {
        if (isWilayah) { setToast('Hanya Super Admin bisa tambah wilayah'); setTimeout(()=>setToast(null),2000); return; }
        setEditing(null); setForm(emptyForm); setOpen(true);
    };
    const openEdit = (r) => {
        if (isWilayah && r.name !== OWN_REGION) return;
        setEditing(r);
        setForm({ name: r.name, kantor: r.kantor, tipe: r.tipe, address: r.address, locations: r.locations.map((l) => ({ ...l })) });
        setOpen(true);
    };
    const close = () => setOpen(false);

    const updateLoc = (idx, patch) => {
        setForm((f) => ({ ...f, locations: f.locations.map((l, i) => i === idx ? { ...l, ...patch } : l) }));
    };
    const addLoc = () => {
        if (form.locations.length >= MAX_SITES) { setToast(`Maksimal ${MAX_SITES} titik per wilayah`); setTimeout(()=>setToast(null),2000); return; }
        setForm((f) => ({ ...f, locations: [...f.locations, { nama_lokasi: '', lat: '', lng: '', radius: 200 }] }));
    };
    const removeLoc = (idx) => {
        if (form.locations.length <= 1) { setToast('Minimal 1 titik per wilayah — tidak bisa hapus terakhir'); setTimeout(()=>setToast(null),2000); return; }
        setForm((f) => ({ ...f, locations: f.locations.filter((_, i) => i !== idx) }));
    };

    const handleSave = () => {
        if (!form.name.trim() || !form.kantor.trim()) {
            setToast('Nama wilayah & kantor wajib diisi'); setTimeout(() => setToast(null), 2000); return;
        }
        const invalid = form.locations.some((l) => !l.nama_lokasi.trim() || l.lat === '' || l.lng === '' );
        if (invalid) { setToast('Lengkapi nama titik + koordinat tiap titik'); setTimeout(() => setToast(null), 2000); return; }
        const badRadius = form.locations.some((l) => Number(l.radius) < 50 || Number(l.radius) > 1000);
        if (badRadius) { setToast('Radius tiap titik 50–1000m'); setTimeout(() => setToast(null), 2000); return; }
        if (editing) {
            setRegions((list) => list.map((r) => r.id === editing.id ? { ...r, name: form.name, kantor: form.kantor, tipe: form.tipe, address: form.address, locations: form.locations.map((l) => ({ ...l, lat: Number(l.lat), lng: Number(l.lng), radius: Number(l.radius) })) } : r));
            setToast('Wilayah & titik proyek diperbarui (frontend only)'); setOpen(false); setTimeout(() => setToast(null), 2000);
        } else {
            const next = { id: Date.now(), name: form.name, kantor: form.kantor, tipe: form.tipe, address: form.address, locations: form.locations.map((l) => ({ ...l, lat: Number(l.lat), lng: Number(l.lng), radius: Number(l.radius) })) };
            setRegions((list) => [...list, next]);
            setToast('Wilayah + titik proyek ditambah (frontend only)'); setOpen(false); setTimeout(() => setToast(null), 2000);
        }
    };

    const handleDelete = (id) => {
        if (isWilayah) { setToast('Admin Wilayah tidak bisa hapus wilayah'); setTimeout(()=>setToast(null),2000); return; }
        setRegions((list) => list.filter((r) => r.id !== id));
        setToast('Wilayah dihapus (frontend only)'); setTimeout(() => setToast(null), 2000);
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Titik Proyek — ${OWN_REGION}` : 'Kantor Wilayah & Titik Proyek'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? 'Kelola N titik proyek di wilayahmu — ex Bendungan Bili-Bili, Jembatan Pampang • tambah/edit/hapus titik (minimal 1, radius 50–1000m)' : `24 Wilayah — Kantor Pusat + 23 Wilayah • Tiap wilayah N titik proyek (Bendungan A, Jembatan B — radius 50–1000m, Leaflet)`}</p>
                        {isWilayah && <span className="inline-block mt-1 text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">Mode Admin Wilayah — kelola titik di own region • wilayah lain read-only</span>}
                    </div>
                    <div className="flex gap-2">
                        {!isWilayah && <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari Gowa, Bone, Parepare..." className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm w-[220px] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10" />}
                        {isWilayah && <span className="text-xs text-[#94A3B8] self-center">{liveOwn.kantor} • {liveOwn.locations.length} titik proyek</span>}
                        {!isWilayah && <button type="button" onClick={openAdd} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Tambah Wilayah</button>}
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Kantor</th><th className="text-left px-4 py-3">Titik Proyek (N)</th><th className="text-left px-4 py-3">Radius</th><th className="px-4 py-3"></th></tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => (
                                    <tr key={r.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3.5"><span className="font-medium text-[#0F172A]">{r.name}</span> {r.tipe === 'pusat' && <span className="ml-1 text-xs bg-[#0F172A] text-white px-1.5 py-0.5 rounded">Pusat</span>}</td>
                                        <td className="px-4 py-3.5 text-[#334155] text-xs">{r.kantor}<br /><span className="text-[#94A3B8]">{r.address}</span></td>
                                        <td className="px-4 py-3.5 text-xs">
                                            {r.locations.map((l, i) => (
                                                <div key={`${r.id}-${i}-${l.nama_lokasi}`} className="leading-tight">
                                                    <span className="font-medium text-[#0F172A]">{l.nama_lokasi}</span> <span className="text-[#64748B]">{Number(l.lat).toFixed(4)}, {Number(l.lng).toFixed(4)}</span>
                                                </div>
                                            ))}
                                            {r.locations.length > 1 && <span className="inline-block mt-1 text-xs bg-[#FFF7E6] text-[#92400E] px-2 py-0.5 rounded-full border border-[#FCB833]/20">{r.locations.length} titik • Bendungan/Jembatan</span>}
                                        </td>
                                        <td className="px-4 py-3.5">
                                            <div className="flex flex-col gap-1">
                                                {r.locations.map((l, i) => (<span key={`${r.id}-r-${i}`} className="inline-flex items-center gap-1 text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2.5 py-1 rounded-full border border-[#FCB833]/20 w-fit">{l.radius} m</span>))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3.5 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={() => openEdit(r)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Edit</button>
                                                {!isWilayah && <button type="button" onClick={() => handleDelete(r.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex items-center justify-between">
                        <span>Menampilkan {filtered.length} dari {regions.length} wilayah • {filtered.reduce((a,r)=>a+r.locations.length,0)} titik proyek</span>
                        <span className="hidden lg:inline">Klik peta untuk set lat/lng • Validasi ke titik terdekat • Di luar semua titik ditolak 422</span>
                    </div>
                </div>

                <div className="bg-[#FFF7E6] rounded-2xl p-4 flex gap-3">
                    <span className="w-8 h-8 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </span>
                    <div>
                        <p className="text-sm font-medium text-[#0F172A]">Geofence N titik — validasi ke titik terdekat (Bendungan A / Jembatan B)</p>
                        <p className="text-xs text-[#92400E]">Super Admin kelola N titik di 24 wilayah • Admin Wilayah tambah/edit/hapus titik di own region saja (minimal 1 titik, radius 50–1000m) • Absen valid jika dalam radius titik terdekat</p>
                    </div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 px-3">{toast}</p>}

                {open && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={close}>
                        <div className="bg-white rounded-2xl w-full max-w-[640px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="sticky top-0 bg-white px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editing ? 'Edit Wilayah & Titik Proyek' : 'Tambah Wilayah & Titik Proyek'}</h3>
                                <button type="button" onClick={close} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Nama wilayah</label>
                                        <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Kab. Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Nama kantor</label>
                                        <input value={form.kantor} onChange={(e) => setForm({ ...form, kantor: e.target.value })} placeholder="Kantor Wilayah Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Tipe</label>
                                        <select value={form.tipe} onChange={(e) => setForm({ ...form, tipe: e.target.value })} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"><option value="pusat">pusat</option><option value="cabang">cabang</option></select>
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Alamat</label>
                                        <input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} placeholder="Jl. Poros — Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                    </div>
                                </div>

                                <div className="pt-2">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-medium text-[#0F172A]">Titik Proyek — Bendungan / Jembatan / Embung ({form.locations.length}/{MAX_SITES})</h4>
                                        <button type="button" onClick={addLoc} disabled={form.locations.length >= MAX_SITES} className="text-xs font-semibold bg-[#FFF7E6] text-[#92400E] px-3 py-1.5 rounded-lg border border-[#FCB833]/20 disabled:opacity-40">+ Tambah titik</button>
                                    </div>
                                    <p className="text-xs text-[#94A3B8] mt-1">Tiap titik: nama + lat/lng (Leaflet) + radius 50–1000m • Minimal 1 titik per wilayah</p>
                                    <div className="mt-3 space-y-3">
                                        {form.locations.map((l, idx) => (
                                            <div key={idx} className="bg-[#F8FAFC] rounded-xl p-3 space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs font-semibold text-[#0F172A]">Titik {idx + 1} {l.nama_lokasi ? `— ${l.nama_lokasi}` : ''}</span>
                                                    {form.locations.length > 1 && <button type="button" onClick={() => removeLoc(idx)} className="text-xs text-[#991B1B] font-medium">Hapus titik</button>}
                                                </div>
                                                <input value={l.nama_lokasi} onChange={(e) => updateLoc(idx, { nama_lokasi: e.target.value })} placeholder="Nama titik (ex: Bendungan Bili-Bili / Jembatan Pampang)" className="w-full rounded-lg bg-white border border-[#E2E8F0] px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                                <div className="grid grid-cols-3 gap-2">
                                                    <input value={l.lat} onChange={(e) => updateLoc(idx, { lat: e.target.value })} placeholder="Lat" type="number" step="0.0001" className="rounded-lg bg-white border border-[#E2E8F0] px-3 py-2 text-sm outline-none" />
                                                    <input value={l.lng} onChange={(e) => updateLoc(idx, { lng: e.target.value })} placeholder="Lng" type="number" step="0.0001" className="rounded-lg bg-white border border-[#E2E8F0] px-3 py-2 text-sm outline-none" />
                                                    <div className="flex items-center gap-1">
                                                        <input value={l.radius} onChange={(e) => updateLoc(idx, { radius: e.target.value })} type="range" min="50" max="1000" step="10" className="flex-1 accent-[#FCB833]" />
                                                        <span className="text-xs font-semibold bg-white border border-[#E2E8F0] px-2 py-1 rounded-lg shrink-0">{l.radius}m</span>
                                                    </div>
                                                </div>
                                                <p className="text-xs text-[#94A3B8]">Klik peta Leaflet untuk isi koordinat • Geser slider radius 50–1000m</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={close} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={handleSave} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
