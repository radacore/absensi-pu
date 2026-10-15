import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { getBase, OWN_REGION, REGION_LIST as regionsList, siteById } from './_shared';

// B3: hari kerja per orang memakai nomor ISO-8601 (1 = Senin … 7 = Minggu),
// sama seperti pengaturan umum.
const HARI_LIST = [['1', 'Senin'], ['2', 'Selasa'], ['3', 'Rabu'], ['4', 'Kamis'], ['5', 'Jumat'], ['6', 'Sabtu'], ['7', 'Minggu']];

export default function Employees() {
    const { url, props } = usePage();
    const { regions, employees, settings } = props;
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const regionsData = regions;
    const list = employees;
    const confirm = useConfirm();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [filterRegion, setFilterRegion] = useState(isWilayah ? OWN_REGION : 'Semua');
    const [filterSite, setFilterSite] = useState('Semua');
    const [filterStatus, setFilterStatus] = useState('Semua');
    const [photo, setPhoto] = useState(null);
    const [preview, setPreview] = useState(null);

    const [form, setForm] = useState({
        nik: '', nip: '', nama: '', email: '', gol: '-', jabatan: '', unit: '', status: 'PNS',
        region: isWilayah ? OWN_REGION : regionsList[1],
        office_location_id: null,
        isShift: false,
        // null = ikut hari kerja umum.
        hariKerja: null,
        // F4: label regu bebas, kosong = tanpa regu.
        regu: '',
    });

    // F4: regu yang sudah pernah dipakai, sebagai saran penulisan (bukan daftar tertutup).
    const reguList = useMemo(
        () => [...new Set(list.map((e) => e.regu).filter(Boolean))].sort((a, b) => a.localeCompare(b)),
        [list],
    );

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
            base2 = base2.filter((e) => String(e.office_location_id) === String(filterSite));
        }
        if (filterStatus !== 'Semua') base2 = base2.filter((e) => e.status === filterStatus);
        return base2;
    }, [scopedList, filterRegion, filterSite, filterStatus, isWilayah]);

    const openAdd = () => {
        const defaultRegion = isWilayah ? OWN_REGION : regionsList[1];
        const r = regionsData.find((x) => x.name === defaultRegion);
        const firstSiteId = r?.locations?.[0]?.id ?? null;
        setEditing(null);
        setForm({ nik:'', nip:'', nama:'', email:'', gol:'-', jabatan:'', unit:'', status:'PNS', region: defaultRegion, office_location_id: firstSiteId, isShift: false, hariKerja: null, regu: '' });
        setPhoto(null); setPreview(null); setOpen(true);
    };
    const openEdit = (e) => {
        setEditing(e);
        setForm({ nik:e.nik, nip:e.nip||'', nama:e.nama, email:e.email, gol:e.gol, jabatan:e.jabatan, unit:e.unit, status:e.status, region:e.region, office_location_id: e.office_location_id ?? null, isShift: !!e.isShift, hariKerja: Array.isArray(e.hariKerja) ? e.hariKerja.map(String) : null, regu: e.regu || '' });
        setPreview(e.foto); setPhoto(null); setOpen(true);
    };
    const close = () => setOpen(false);

    const onPhoto = (ev) => {
        const f = ev.target.files?.[0]; if (!f) return; setPhoto(f);
        const r = new FileReader(); r.onload = () => setPreview(r.result); r.readAsDataURL(f);
    };

    const save = () => {
        if (isWilayah && form.region !== OWN_REGION) { toast.error(`Admin Wilayah hanya boleh di ${OWN_REGION}`); return; }
        if (!form.nama.trim() || !form.email.trim() || !/^\S+@\S+\.\S+$/.test(form.email)) { toast.error('Nama dan email valid wajib diisi'); return; }
        if (!/^\d{16}$/.test(form.nik)) { toast.error('NIK harus 16 digit'); return; }
        if (form.office_location_id == null || form.office_location_id === '') { toast.error('Titik proyek wajib dipilih (1 karyawan = 1 titik)'); return; }
        const regionObj = regionsData.find((r) => r.name === form.region);
        if (!regionObj) { toast.error('Wilayah tidak ditemukan'); return; }
        const ok = regionObj.locations.some((s) => s.id === Number(form.office_location_id));
        if (!ok) { toast.error('Titik tidak sesuai wilayah'); return; }
        if (form.hariKerja !== null && form.hariKerja.length === 0) { toast.error('Pilih minimal satu hari kerja, atau centang "Ikut hari kerja umum"'); return; }
        if (!editing) {
            if (list.some((x)=>x.nik===form.nik)) { toast.error('NIK sudah terdaftar'); return; }
            if (list.some((x)=>x.email===form.email)) { toast.error('Email sudah terdaftar'); return; }
        }
        const payload = {
            nik: form.nik, nip: form.nip || '', nama: form.nama, email: form.email, gol: form.gol,
            jabatan: form.jabatan, unit: form.unit, status: form.status,
            region: form.region, office_location_id: Number(form.office_location_id),
            isShift: !!form.isShift,
            hariKerja: form.hariKerja,
            regu: form.regu.trim(),
        };
        if (editing) {
            if (isWilayah && editing.region !== OWN_REGION) { toast.error('Tidak bisa mengedit karyawan luar wilayah'); return; }
            router.put(`${base}/employees/${editing.id}`, payload, { preserveScroll: true });
        } else {
            router.post(`${base}/employees`, payload, { preserveScroll: true });
        }
        setOpen(false);
    };

    const handleReset = async (e) => {
        if (isWilayah && e.region !== OWN_REGION) { toast.error('Hanya karyawan di wilayah Anda yang bisa direset'); return; }
        const ok = await confirm({
            title: `Reset kata sandi ${e.nama}?`,
            description: `${e.email} • ${e.region}. Kata sandi baru akan disamakan dengan NIK karyawan (${e.nik}) dan karyawan wajib menggantinya saat login berikutnya.`,
            confirmLabel: 'Reset ke NIK',
            cancelLabel: 'Batal',
            tone: 'warning',
        });
        if (!ok) return;
        router.post(`${base}/employees/${e.id}/reset-password`, {}, { preserveScroll: true });
    };

    const remove = async (id) => {
        const target = list.find((x)=>x.id===id);
        if (!target) return;
        if (isWilayah && target.region !== OWN_REGION) { toast.error('Hanya karyawan di wilayah Anda yang bisa dihapus'); return; }
        const ok = await confirm({
            title: `Hapus ${target.nama}?`,
            description: `${target.email} • ${target.region}. Data karyawan akan dihapus permanen dan tidak dapat dikembalikan.`,
            confirmLabel: 'Ya, hapus',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`${base}/employees/${target.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Karyawan — ${OWN_REGION}` : 'Karyawan — Lengkap HR'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `${scopedList.length} karyawan • own region` : `${list.length} karyawan • 24 wilayah`}</p>
                        <p className="text-xs text-[#64748B] mt-1">1 karyawan = 1 titik — wajib titik saat tambah/edit. Pindah via halaman titik (Kelola).</p>
                        {isWilayah && <span className="inline-block mt-2 text-xs bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">Write own region saja</span>}
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
                            {(isWilayah ? regionsData.find((r)=>r.name===OWN_REGION)?.locations||[] : sitesForFilterRegion).map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                            {filterRegion==='Semua' && !isWilayah && <option disabled>— pilih wilayah untuk titik spesifik</option>}
                        </select>
                    </label>
                    <label className="text-xs font-medium text-[#334155]">Status
                        <select value={filterStatus} onChange={(e)=>setFilterStatus(e.target.value)} className="mt-1 block rounded-xl bg-[#F8FAFC] border-0 px-3 py-2 text-sm outline-none">
                            <option value="Semua">Semua</option><option>PNS</option><option>PPPK</option><option>Kontrak</option><option>Honorer</option>
                        </select>
                    </label>
                    <span className="text-xs text-[#64748B] ml-auto self-center">{filtered.length} hasil</span>
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
                                                        <p className="font-medium text-[#0F172A]">{e.nama}{e.isShift && <span className="ml-2 align-middle text-[10px] font-semibold bg-[#E0E7FF] text-[#3730A3] px-2 py-0.5 rounded-full">Shift</span>}{Array.isArray(e.hariKerja) && <span className="ml-2 align-middle text-[10px] font-semibold bg-[#FFF7E6] text-[#92400E] px-2 py-0.5 rounded-full">Hari kerja khusus</span>}{e.regu && <span className="ml-2 align-middle text-[10px] font-semibold bg-[#F1F5F9] text-[#475569] px-2 py-0.5 rounded-full">{e.regu}</span>}</p>
                                                        <p className="text-xs text-[#64748B]">{e.gol} • {e.unit} • {e.status}</p>
                                                        <p className="text-xs text-[#64748B]">{e.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs"><span className="font-mono text-[#0F172A]">{e.nik}</span><br /><span className="text-[#64748B]">{e.nip || '-'}</span></td>
                                            <td className="px-4 py-3 text-xs text-[#334155]">{e.jabatan}</td>
                                            <td className="px-4 py-3 text-xs"><span className="bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">{e.region}</span></td>
                                            <td className="px-4 py-3 text-xs">
                                                {hit ? (
                                                    <Link href={`${base}/regions/${hit.region.id}/sites/${hit.site.id}`} className="inline-flex flex-col bg-[#EFF6FF] text-[#1E3A8A] px-2.5 py-1 rounded-full hover:bg-[#DBEAFE]">
                                                        <span className="font-medium leading-tight">{hit.site.nama_lokasi}</span>
                                                        <span className="text-[10px] text-[#64748B] font-mono">{hit.site.radius}m</span>
                                                    </Link>
                                                ) : (
                                                    <span className="inline-flex bg-[#F1F5F9] text-[#64748B] px-2.5 py-1 rounded-full">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex gap-1 justify-end flex-wrap">
                                                    <Link href={`${base}/employees/${e.id}/rekap`} className="text-xs font-medium text-[#065F46] bg-[#DCFCE7] px-3 py-1.5 rounded-lg hover:bg-[#BBF7D0]">Rekap</Link>
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
                    {filtered.length===0 && <p className="text-center text-sm text-[#64748B] py-8">Tidak ada karyawan — coba ubah filter atau tambah di halaman titik.</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex flex-wrap gap-2 justify-between">
                        <span>{isWilayah ? `Admin Wilayah: tulis hanya ${OWN_REGION}` : 'Super Admin tulis semua • Admin Wilayah tulis own region'}</span>
                        <span>1 karyawan = 1 titik (wajib)</span>
                    </div>
                </div>



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
                                        {preview ? <img src={preview} alt="preview" className="w-full h-full object-cover" /> : <span className="text-xs text-[#64748B]">Foto</span>}
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
                                        <label className="text-xs font-medium text-[#334155]">Wilayah {isWilayah && <span className="text-[#64748B]">— terkunci own</span>}</label>
                                        {isWilayah ? (
                                            <div className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] px-3 py-2.5 text-sm text-[#0F172A] font-medium">{OWN_REGION}</div>
                                        ) : (
                                            <select value={form.region} onChange={(e)=>setForm({...form, region:e.target.value, office_location_id: null})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                                {regionsData.map((r)=><option key={r.id} value={r.name}>{r.name} — {r.locations.length} titik</option>)}
                                            </select>
                                        )}
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Titik Proyek — 1 karyawan = 1 titik (wajib)</label>
                                        <select value={form.office_location_id ?? ''} onChange={(e)=>setForm({...form, office_location_id: e.target.value === '' ? '' : Number(e.target.value)})} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none">
                                            <option value="" disabled>— Pilih titik —</option>
                                            {sitesForFormRegion.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m • {s.lat.toFixed(3)},{s.lng.toFixed(3)}</option>)}
                                        </select>
                                        <p className="text-xs mt-1">
                                            <span className="text-[#065F46]">✓ Akan absen di {sitesForFormRegion.find((s)=>s.id===Number(form.office_location_id))?.nama_lokasi || '— pilih titik'} — dalam radius baru valid.</span>
                                        </p>
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs font-medium text-[#334155]">Regu (opsional)</label>
                                        <input
                                            value={form.regu}
                                            onChange={(e)=>setForm({...form, regu:e.target.value.slice(0,30)})}
                                            list="daftar-regu"
                                            placeholder="mis. Regu A — kosongkan bila tidak memakai regu"
                                            className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"
                                        />
                                        <datalist id="daftar-regu">
                                            {reguList.map((r) => <option key={r} value={r} />)}
                                        </datalist>
                                        <span className="block text-xs text-[#64748B] mt-1">Bebas diisi — jumlah regu tidak dibatasi. Dipakai untuk mengelompokkan dan mengisi jadwal shift satu regu sekaligus di halaman Jadwal Shift.</span>
                                    </div>
                                    <div className="col-span-2 rounded-xl bg-[#F8FAFC] px-3 py-3">
                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input type="checkbox" checked={!!form.isShift} onChange={(e)=>setForm({...form, isShift:e.target.checked})} className="mt-0.5 w-4 h-4 accent-[#0F172A]" />
                                            <span>
                                                <span className="text-sm font-medium text-[#0F172A]">Pekerja shift</span>
                                                <span className="block text-xs text-[#64748B] mt-0.5">Centang bila karyawan ini bekerja giliran (pagi/siang/malam). Absensinya mengikuti jadwal shift, bukan jam kantor biasa.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div className="col-span-2 rounded-xl bg-[#F8FAFC] px-3 py-3">
                                        <label className="flex items-start gap-3 cursor-pointer">
                                            <input type="checkbox" checked={form.hariKerja === null} onChange={(e)=>setForm({...form, hariKerja: e.target.checked ? null : (settings?.hariKerja ?? ['1','2','3','4','5']).map(String)})} className="mt-0.5 w-4 h-4 accent-[#0F172A]" />
                                            <span>
                                                <span className="text-sm font-medium text-[#0F172A]">Ikut hari kerja umum</span>
                                                <span className="block text-xs text-[#64748B] mt-0.5">Hari kerja umum saat ini: {(settings?.hariKerja ?? ['1','2','3','4','5']).map((d)=>HARI_LIST.find((h)=>h[0]===String(d))?.[1] ?? d).join(', ')}. Lepas centang bila karyawan ini punya hari kerja sendiri (mis. libur bergeser).</span>
                                            </span>
                                        </label>
                                        {form.hariKerja !== null && (
                                            <div className="mt-3 flex flex-wrap gap-1.5 pl-7">
                                                {HARI_LIST.map(([k, label]) => {
                                                    const on = form.hariKerja.includes(k);
                                                    return (
                                                        <button key={k} type="button" onClick={()=>setForm({...form, hariKerja: on ? form.hariKerja.filter((x)=>x!==k) : [...form.hariKerja, k].sort()})} className={`px-3 py-1.5 rounded-lg text-xs font-medium border ${on ? 'bg-[#1E3A8A] text-white border-[#1E3A8A]' : 'bg-white text-[#334155] border-[#E2E8F0]'}`}>{label}</button>
                                                    );
                                                })}
                                            </div>
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
