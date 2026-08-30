import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees, saveEmployees, getBase, OWN_REGION } from './_shared';

const regionsList = ['Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];

function regionIdByName(name, regionsData) {
    const f = regionsData.find((r) => r.name === name);
    return f ? f.id : null;
}
function siteById(siteId, regionsData) {
    for (const r of regionsData) {
        const s = r.locations.find((x) => x.id === siteId);
        if (s) return { site: s, region: r };
    }
    return null;
}

export default function Employees() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [list, setList] = useState(() => loadEmployees());
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [toast, setToast] = useState(null);
    const [filterRegion, setFilterRegion] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [filterSite, setFilterSite] = useState('Semua');
    const [filterStatus, setFilterStatus] = useState('Semua');
    const [photo, setPhoto] = useState(null);
    const [preview, setPreview] = useState(null);

    const [form, setForm] = useState({
        nik: '', nip: '', nama: '', email: '', gol: '-', jabatan: '', unit: '', status: 'PNS',
        region: isWilayah ? OWN_REGION : regionsList[1],
        office_location_id: null,
    });

    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setList(loadEmployees()); };
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);

    // keep form office_location_id valid when region changes
    const sitesForFormRegion = useMemo(() => {
        const r = regionsData.find((x) => x.name === form.region);
        return r ? r.locations : [];
    }, [regionsData, form.region]);

    const sitesForFilterRegion = useMemo(() => {
        if (filterRegion === 'Semua') return [];
        const r = regionsData.find((x) => x.name === filterRegion);
        return r ? r.locations : [];
    }, [regionsData, filterRegion]);

    const scopedList = useMemo(() => isWilayah ? list.filter((e) => e.region === OWN_REGION) : list, [list, isWilayah]);

    const filtered = useMemo(() => {
        let base2 = scopedList;
        if (!isWilayah && filterRegion !== 'Semua') base2 = base2.filter((e) => e.region === filterRegion);
        if (filterSite !== 'Semua') {
            if (filterSite === '__null') base2 = base2.filter((e) => e.office_location_id == null);
            else base2 = base2.filter((e) => String(e.office_location_id) === String(filterSite));
        }
        if (filterStatus !== 'Semua') base2 = base2.filter((e) => e.status === filterStatus);
        return base2;
    }, [scopedList, filterRegion, filterSite, filterStatus, isWilayah]);

    const openAdd = () => {
        const defaultRegion = isWilayah ? OWN_REGION : regionsList[1];
        const r = regionsData.find((x) => x.name === defaultRegion);
        const firstSiteId = r?.locations?.[0]?.id ?? null;
        setEditing(null);
        setForm({ nik:'', nip:'', nama:'', email:'', gol:'-', jabatan:'', unit:'', status:'PNS', region: defaultRegion, office_location_id: firstSiteId });
        setPhoto(null); setPreview(null); setOpen(true);
    };
    const openEdit = (e) => {
        setEditing(e);
        setForm({ nik:e.nik, nip:e.nip||'', nama:e.nama, email:e.email, gol:e.gol, jabatan:e.jabatan, unit:e.unit, status:e.status, region:e.region, office_location_id: e.office_location_id ?? null });
        setPreview(e.foto); setPhoto(null); setOpen(true);
    };
    const close = () => setOpen(false);

    const onPhoto = (ev) => {
        const f = ev.target.files?.[0]; if (!f) return; setPhoto(f);
        const r = new FileReader(); r.onload = () => setPreview(r.result); r.readAsDataURL(f);
    };

    const showToast = (msg) => { setToast(msg); setTimeout(()=>setToast(null),2500); };

    const save = () => {
        if (isWilayah && form.region !== OWN_REGION) { showToast(`Admin Wilayah hanya boleh di ${OWN_REGION}`); return; }
        if (!form.nama.trim() || !form.email.trim() || !/^\S+@\S+\.\S+$/.test(form.email)) { showToast('Nama & email valid wajib'); return; }
        if (!/^\d{16}$/.test(form.nik)) { showToast('NIK 16 digit'); return; }
        const regionObj = regionsData.find((r) => r.name === form.region);
        const rid = regionObj ? regionObj.id : null;
        if (form.office_location_id != null) {
            const ok = regionObj?.locations.some((s) => s.id === Number(form.office_location_id));
            if (!ok) { showToast('Titik tidak sesuai wilayah'); return; }
        }
        if (editing) {
            if (isWilayah && editing.region !== OWN_REGION) { showToast('Tidak bisa edit karyawan luar wilayah'); return; }
            const nextList = list.map((x) => x.id===editing.id ? {
                ...x, nik:form.nik, nip:form.nip, nama:form.nama, email:form.email, gol:form.gol, jabatan:form.jabatan, unit:form.unit, status:form.status, region:form.region, regionId: rid, office_location_id: form.office_location_id == null || form.office_location_id === '' ? null : Number(form.office_location_id), kantor:form.region.replace('Kab. ','').replace('Kota ',''), foto: preview||x.foto
            } : x);
            setList(nextList); saveEmployees(nextList); showToast('Karyawan diperbarui');
        } else {
            // cek nik/email unik simple
            if (list.some((x)=>x.nik===form.nik)) { showToast('NIK sudah ada'); return; }
            if (list.some((x)=>x.email===form.email)) { showToast('Email sudah ada'); return; }
            const next = {
                id: Date.now(), nik:form.nik, nip:form.nip, nama:form.nama, email:form.email, gol:form.gol, jabatan:form.jabatan, unit:form.unit, status:form.status,
                region:form.region, regionId: rid, office_location_id: form.office_location_id == null || form.office_location_id === '' ? null : Number(form.office_location_id),
                kantor:form.region.replace('Kab. ','').replace('Kota ',''), foto: preview || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face&auto=format'
            };
            const nextList = [...list, next]; setList(nextList); saveEmployees(nextList); showToast(next.office_location_id == null ? 'Karyawan ditambah — tanpa titik, tidak bisa absen. Assign via Kelola titik.' : 'Karyawan ditambah — siap absen di titik assigned');
        }
        setOpen(false);
    };

    const handleReset = (e) => {
        if (isWilayah && e.region !== OWN_REGION) { showToast('Hanya own region'); return; }
        showToast(`Reset password — link kirim ke ${e.email} (frontend only)`);
    };
    const remove = (id) => {
        const target = list.find((x)=>x.id===id);
        if (isWilayah && target && target.region !== OWN_REGION) { showToast('Hanya own region'); return; }
        const nextList = list.filter((x)=>x.id!==id); setList(nextList); saveEmployees(nextList); showToast('Dihapus');
    };

    const countUnassigned = scopedList.filter((e)=>e.office_location_id==null).length;

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Karyawan — ${OWN_REGION}` : 'Karyawan — Lengkap HR'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `${scopedList.length} karyawan own region • ${countUnassigned} belum punya titik` : `${list.length} karyawan • ${countUnassigned} tanpa titik di ${filterRegion === 'Semua' ? 'semua wilayah' : filterRegion}`}</p>
                        <p className="text-xs text-[#94A3B8] mt-1">1 karyawan = 1 titik • <span className="font-medium text-[#92400E]">Tanpa titik tidak bisa absen</span> — assign di halaman titik (Kelola) atau saat tambah/edit di sini.</p>
                        {isWilayah && <span className="inline-block mt-2 text-xs bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">Write own region saja • tanpa titik = belum bisa absen</span>}
                    </div>
                    <button type="button" onClick={openAdd} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Tambah Karyawan</button>
                </div>

                <div className="bg-white rounded-2xl p-4 flex flex-wrap gap-3 items-end shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    {!isWilayah && (
                        <label className="text-xs font-medium text-[#334155]">Wilayah
                            <select value={filterRegion} onChange={(e)=>{ setFilterRegion(e.target.value); setFilterSite('Semua'); }} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                                <option value="Semua">Semua</option>
                                {regionsData.map((r)=><option key={r.id} value={r.name}>{r.name}</option>)}
                            </select>
                        </label>
                    )}
                    <label className="text-xs font-medium text-[#334155]">Titik Proyek
                        <select value={filterSite} onChange={(e)=>setFilterSite(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none min-w-[180px]">
                            <option value="Semua">Semua titik</option>
                            <option value="__null">Tanpa titik {isWilayah ? '' : '(semua wilayah)'} </option>
                            {(isWilayah ? regionsData.find((r)=>r.name===OWN_REGION)?.locations||[] : sitesForFilterRegion).map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                            {filterRegion==='Semua' && !isWilayah && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-[#334155]">Status
                        <select value={filterStatus} onChange={(e)=>setFilterStatus(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="Semua">Semua</option><option>PNS</option><option>PPPK</option><option>Kontrak</option><option>Honorer</option>
                        </select>
                    </label>
                    <span className="text-xs text-[#64748B] ml-auto self-center">{filtered.length} hasil {filterSite === '__null' && '• belum punya titik'}</span>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">NIK / NIP</th><th className="text-left px-4 py-3">Jabatan</th><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Titik Proyek</th><th className="px-4 py-3"></th></tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((e) => {
                                    const hit = e.office_location_id != null ? siteById(e.office_location_id, regionsData) : null;
                                    return (
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
                                            <td className="px-4 py-3 text-xs">
                                                {hit ? (
                                                    <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className="inline-flex flex-col bg-[#EFF6FF] text-[#1E3A8A] px-2.5 py-1 rounded-full hover:bg-[#DBEAFE]">
                                                        <span className="font-medium leading-tight">{hit.site.nama_lokasi}</span>
                                                        <span className="text-[10px] text-[#64748B] font-mono">{hit.site.radius}m</span>
                                                    </Link>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 bg-[#FEF2F2] text-[#991B1B] px-2.5 py-1 rounded-full border border-[#FECACA]">Tanpa titik</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex gap-1 justify-end">
                                                    <button type="button" onClick={()=>openEdit(e)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Edit</button>
                                                    <button type="button" onClick={()=>handleReset(e)} className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1.5 rounded-lg">Reset</button>
                                                    <button type="button" onClick={()=>remove(e.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {filtered.length===0 && <p className="text-center text-sm text-[#94A3B8] py-8">Tidak ada karyawan — coba ubah filter atau tambah di halaman titik.</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{isWilayah ? `Admin Wilayah: tulis hanya ${OWN_REGION} • tanpa titik tidak bisa absen — assign di Kelola titik` : 'Super Admin tulis semua • Admin Wilayah tulis own region'}</span>
                        <span>1 karyawan = 1 titik • Tanpa titik → tidak bisa absen (422)</span>
                    </div>
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
                                        <input value={form.email} onChange={(e)=>setForm({...form, email:e.target.value})} placeholder="nama@bbws-pj.go.id" type="email" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
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
                                        <label className="text-xs font-medium text-[#334155]">Wilayah {isWilayah && <span className="text-[#94A3B8]">— terkunci own</span>}</label>
                                        {isWilayah ? (
                                            <div className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] px-3 py-2.5 text-sm text-[#0F172A] font-medium">{OWN_REGION}</div>
                                        ) : (
                                            <select value={form.region} onChange={(e)=>setForm({...form, region:e.target.value, office_location_id: null})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                                {regionsData.map((r)=><option key={r.id} value={r.name}>{r.name} — {r.locations.length} titik</option>)}
                                            </select>
                                        )}
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Titik Proyek — 1 karyawan = 1 titik</label>
                                        <select value={form.office_location_id ?? ''} onChange={(e)=>setForm({...form, office_location_id: e.target.value === '' ? null : Number(e.target.value)})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                            <option value="">— Tanpa titik (tidak bisa absen) — assign nanti di halaman titik</option>
                                            {sitesForFormRegion.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m • {s.lat.toFixed(3)},{s.lng.toFixed(3)}</option>)}
                                        </select>
                                        <p className="text-xs mt-1">
                                            {form.office_location_id == null
                                                ? <span className="text-[#991B1B]">⚠ Tanpa titik — karyawan tidak bisa absen (422). Pilih titik di atas atau assign nanti via Kelola titik.</span>
                                                : <span className="text-[#065F46]">✓ Akan absen di {sitesForFormRegion.find((s)=>s.id===Number(form.office_location_id))?.nama_lokasi || 'titik terpilih'} — dalam radius baru valid.</span>}
                                        </p>
                                        {form.office_location_id == null && sitesForFormRegion.length > 0 && <p className="text-xs mt-1"><button type="button" onClick={()=>setForm({...form, office_location_id: sitesForFormRegion[0].id})} className="text-[#1E3A8A] font-medium underline">Pilih {sitesForFormRegion[0].nama_lokasi} (saran)</button></p>}
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
