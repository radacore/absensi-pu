import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { loadRegions, loadEmployees, saveRegions, saveEmployees, getBase, OWN_REGION, MAX_SITES } from './_shared';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

const emptyWilayah = { name: '', kantor: '', tipe: 'cabang', address: '' };
const emptySite = { nama_lokasi: '', lat: '', lng: '', radius: 200, address: '' };

export default function Regions() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const [regions, setRegions] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [q, setQ] = useState('');
    const [toast, setToast] = useState(null);

    // wilayah modal (super admin only for add, own edit for admin wilayah)
    const [wilayahOpen, setWilayahOpen] = useState(false);
    const [editingWilayah, setEditingWilayah] = useState(null);
    const [wilayahForm, setWilayahForm] = useState(emptyWilayah);

    // tambah titik per 1 — dedicated single-site flow with maps
    const [addSiteFor, setAddSiteFor] = useState(null); // region id
    const [siteForm, setSiteForm] = useState(emptySite);
    const [confirmDeleteSite, setConfirmDeleteSite] = useState(null);
    const [confirmDeleteWilayah, setConfirmDeleteWilayah] = useState(null);
    const siteMapRef = useRef(null);
    const siteLeafletRef = useRef(null);

    // reload from storage when page shown (after SiteDetail edits)
    useEffect(() => {
        const sync = () => { setRegions(loadRegions()); setEmployees(loadEmployees()); };
        window.addEventListener('focus', sync);
        // also on visibility
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = () => sync();
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
    }, []);

    const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(null), 2500); };

    const liveOwn = useMemo(() => regions.find((r) => r.name === OWN_REGION) || regions.find((r) => r.id === 2) || regions[0], [regions]);
    const displayRegions = isWilayah ? (liveOwn ? [liveOwn] : []) : regions;
    const filtered = displayRegions.filter((r) => r.name.toLowerCase().includes(q.toLowerCase()) || r.kantor.toLowerCase().includes(q.toLowerCase()));

    const openAddWilayah = () => {
        if (isWilayah) { showToast('Hanya Super Admin bisa tambah wilayah'); return; }
        setEditingWilayah(null); setWilayahForm(emptyWilayah); setWilayahOpen(true);
    };
    const openEditWilayah = (r) => {
        if (isWilayah && r.name !== OWN_REGION) return;
        setEditingWilayah(r);
        setWilayahForm({ name: r.name, kantor: r.kantor, tipe: r.tipe, address: r.address });
        setWilayahOpen(true);
    };
    const closeWilayah = () => setWilayahOpen(false);

    const handleSaveWilayah = () => {
        const nameTrim = wilayahForm.name.trim();
        const kantorTrim = wilayahForm.kantor.trim();
        if (!nameTrim || !kantorTrim) { showToast('Nama wilayah & kantor wajib'); return; }
        const nameLower = nameTrim.toLowerCase();
        const dup = regions.some((r) => r.name.trim().toLowerCase() === nameLower && r.id !== editingWilayah?.id);
        if (dup) { showToast('Nama wilayah sudah ada'); return; }
        if (wilayahForm.tipe === 'pusat' && regions.some((r) => r.tipe === 'pusat' && r.id !== editingWilayah?.id)) { showToast('Hanya 1 Kantor Pusat — Makassar sudah Pusat'); return; }
        if (editingWilayah) {
            setRegions((prev) => {
                const next = prev.map((r) => r.id === editingWilayah.id ? { ...r, name: nameTrim, kantor: kantorTrim, tipe: wilayahForm.tipe, address: wilayahForm.address.trim() } : r);
                saveRegions(next); return next;
            });
            showToast('Wilayah diperbarui');
        } else {
            const nextRegion = { id: Date.now(), name: nameTrim, kantor: kantorTrim, tipe: wilayahForm.tipe, address: wilayahForm.address.trim(), locations: [] };
            setRegions((prev) => { const next = [...prev, nextRegion]; saveRegions(next); return next; });
            showToast('Wilayah ditambah — tambah 1 titik untuk aktifkan absen');
        }
        setWilayahOpen(false);
    };
    const handleDeleteWilayah = (id) => {
        if (isWilayah) { showToast('Admin Wilayah tidak bisa hapus wilayah'); return; }
        const r = regions.find((x) => x.id === id);
        if (!r) return;
        const nEmp = employees.filter((e) => e.region === r.name).length;
        const nSites = r.locations.length;
        setConfirmDeleteWilayah({ id, name: r.name, nEmp, nSites });
    };
    const confirmDeleteWilayahAction = () => {
        if (!confirmDeleteWilayah) return;
        const id = confirmDeleteWilayah.id;
        const r = regions.find((x) => x.id === id);
        setRegions((prev) => { const next = prev.filter((x) => x.id !== id); saveRegions(next); return next; });
        // orphan employees: set Tanpa titik + region keep but site null
        const emps = loadEmployees();
        const siteIds = new Set((r?.locations || []).map((s) => s.id));
        const nextEmps = emps.map((e) => siteIds.has(e.office_location_id) ? { ...e, office_location_id: null } : e);
        saveEmployees(nextEmps); setEmployees(nextEmps);
        showToast(r ? `Wilayah ${r.name} dihapus — ${confirmDeleteWilayah.nEmp} karyawan jadi Tanpa titik` : 'Wilayah dihapus');
        setConfirmDeleteWilayah(null);
    };

    // tambah 1 titik saja — with maps picker
    const openAddSite = (region) => {
        const can = isWilayah ? region.name === OWN_REGION : true;
        if (!can) { showToast('Hanya own region'); return; }
        if (region.locations.length >= MAX_SITES) { showToast(`Maksimal ${MAX_SITES} titik per wilayah`); return; }
        setAddSiteFor(region);
        setSiteForm(emptySite);
    };
    const closeAddSite = () => { setAddSiteFor(null); if (siteLeafletRef.current?.map) { siteLeafletRef.current.map.remove(); siteLeafletRef.current = null; } };

    // leaflet for add-site modal
    useEffect(() => {
        if (!addSiteFor || !siteMapRef.current || siteLeafletRef.current) return;
        let mounted = true;
        (async () => {
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            if (!mounted || !siteMapRef.current) return;
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: markerIcon2x,
                iconUrl: markerIcon,
                shadowUrl: markerShadow,
            });
            // default view: region first site or Sulsel center
            const fallback = addSiteFor.locations[0];
            const center = fallback ? [fallback.lat, fallback.lng] : [-5.15, 119.45];
            const map = L.map(siteMapRef.current).setView(center, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' }).addTo(map);
            let marker = null;
            let circle = null;
            const place = (latlng) => {
                const { lat, lng } = latlng;
                setSiteForm((f) => ({ ...f, lat: lat.toFixed(6), lng: lng.toFixed(6) }));
                if (!marker) {
                    marker = L.marker(latlng, { draggable: true }).addTo(map);
                    circle = L.circle(latlng, { radius: Number(siteForm.radius) || 200, color: '#FCB833', fillColor: '#FCB833', fillOpacity: 0.12, weight: 2 }).addTo(map);
                    marker.on('dragend', () => {
                        const p = marker.getLatLng(); circle.setLatLng(p); setSiteForm((f) => ({ ...f, lat: p.lat.toFixed(6), lng: p.lng.toFixed(6) }));
                    });
                } else {
                    marker.setLatLng(latlng); circle.setLatLng(latlng);
                }
            };
            const initLat = siteForm.lat ? Number(siteForm.lat) : null;
            const initLng = siteForm.lng ? Number(siteForm.lng) : null;
            if (initLat && initLng) place({ lat: initLat, lng: initLng });
            map.on('click', (e) => place(e.latlng));
            siteLeafletRef.current = { map, marker: () => marker, circle: () => circle, L };
            setTimeout(() => map.invalidateSize(), 200);
        })();
        return () => { mounted = false; };
    }, [addSiteFor?.id]);

    // sync circle radius from slider / latlng inputs
    useEffect(() => {
        const c = siteLeafletRef.current?.circle?.();
        if (c) c.setRadius(Number(siteForm.radius) || 200);
    }, [siteForm.radius]);
    useEffect(() => {
        const m = siteLeafletRef.current?.marker?.();
        const c = siteLeafletRef.current?.circle?.();
        const map = siteLeafletRef.current?.map;
        if (!m || !c || !map) return;
        const lat = Number(siteForm.lat), lng = Number(siteForm.lng);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            m.setLatLng([lat, lng]); c.setLatLng([lat, lng]); map.setView([lat, lng], map.getZoom());
        }
    }, [siteForm.lat, siteForm.lng]);

    const handleSaveSite = () => {
        if (!addSiteFor) return;
        const namaTrim = siteForm.nama_lokasi.trim();
        if (!namaTrim) { showToast('Nama titik wajib'); return; }
        const dupSite = addSiteFor.locations.some((s) => s.nama_lokasi.trim().toLowerCase() === namaTrim.toLowerCase());
        if (dupSite) { showToast('Nama titik sudah ada di wilayah ini'); return; }
        const lat = Number(siteForm.lat), lng = Number(siteForm.lng), radius = Number(siteForm.radius);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) { showToast('Pilih titik di peta / isi lat lng'); return; }
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) { showToast('Lat -90..90, Lng -180..180'); return; }
        if (radius < 50 || radius > 1000) { showToast('Radius 50–1000m'); return; }
        const newId = Date.now();
        const regionId = addSiteFor.id;
        setRegions((prev) => {
            const next = prev.map((r) => r.id !== regionId ? r : {
                ...r,
                locations: [...r.locations, { id: newId, nama_lokasi: siteForm.nama_lokasi.trim(), lat, lng, radius, address: siteForm.address.trim() }]
            });
            saveRegions(next); return next;
        });
        closeAddSite();
        showToast('Titik ditambah — membuka halaman titik untuk tambah anggota...');
        setTimeout(() => router.visit(`${base}/regions/${regionId}/sites/${newId}`), 400);
    };

    const handleDeleteSite = (regionId, siteId) => {
        const region = regions.find((r) => r.id === regionId);
        if (!region) return;
        if (isWilayah && region.name !== OWN_REGION) { showToast('Hanya own region'); return; }
        if (region.locations.length <= 1) { showToast('Minimal 1 titik per wilayah'); return; }
        const nAnggota = employees.filter((e) => e.office_location_id === siteId).length;
        const siteName = region.locations.find((s) => s.id === siteId)?.nama_lokasi || 'titik ini';
        setConfirmDeleteSite({ regionId, siteId, siteName, nAnggota, regionName: region.name });
    };
    const confirmDeleteSiteAction = () => {
        if (!confirmDeleteSite) return;
        const { regionId, siteId } = confirmDeleteSite;
        setRegions((prev) => {
            const next = prev.map((r) => r.id !== regionId ? r : { ...r, locations: r.locations.filter((s) => s.id !== siteId) });
            saveRegions(next); return next;
        });
        const emps = loadEmployees();
        const nextEmps = emps.map((e) => e.office_location_id === siteId ? { ...e, office_location_id: null } : e);
        saveEmployees(nextEmps); setEmployees(nextEmps);
        showToast(confirmDeleteSite.nAnggota > 0 ? `Titik ${confirmDeleteSite.siteName} dihapus — ${confirmDeleteSite.nAnggota} anggota jadi Tanpa titik (tidak bisa absen)` : `Titik ${confirmDeleteSite.siteName} dihapus`);
        setConfirmDeleteSite(null);
    };

    const countForSite = (siteId) => employees.filter((e) => e.office_location_id === siteId).length;

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Titik Proyek — ${OWN_REGION}` : 'Kantor Wilayah & Titik Proyek'}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? 'Tambah per 1 titik dengan peta • Tiap titik kelola anggota sendiri (karyawan per titik saja)' : '24 Wilayah • Tiap wilayah N titik — klik titik untuk halaman dedicated + maps + anggota per titik'}</p>
                        {isWilayah && <span className="inline-block mt-1 text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">Mode Admin Wilayah — kelola titik own region + anggota per titik</span>}
                    </div>
                    <div className="flex gap-2">
                        {!isWilayah && <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari Gowa, Bone, Parepare..." className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm w-[220px] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/10" />}
                        {isWilayah && liveOwn && <span className="text-xs text-[#94A3B8] self-center">{liveOwn.kantor} • {liveOwn.locations.length} titik</span>}
                        {!isWilayah && <button type="button" onClick={openAddWilayah} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">+ Tambah Wilayah</button>}
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr><th className="text-left px-4 py-3">Wilayah</th><th className="text-left px-4 py-3">Kantor</th><th className="text-left px-4 py-3">Titik Proyek (N) — per titik</th><th className="px-4 py-3"></th></tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {filtered.map((r) => {
                                    const canEditRegion = isWilayah ? r.name === OWN_REGION : true;
                                    return (
                                        <tr key={r.id} className="hover:bg-[#F8FAFC]/50">
                                            <td className="px-4 py-3.5 align-top"><span className="font-medium text-[#0F172A]">{r.name}</span> {r.tipe === 'pusat' && <span className="ml-1 text-xs bg-[#0F172A] text-white px-1.5 py-0.5 rounded">Pusat</span>}<br /><span className="text-xs text-[#94A3B8]">{r.address}</span></td>
                                            <td className="px-4 py-3.5 text-[#334155] text-xs align-top">{r.kantor}</td>
                                            <td className="px-4 py-3.5 align-top">
                                                {r.locations.length === 0 ? (
                                                    <span className="text-xs text-[#94A3B8]">Belum ada titik — tambah 1 titik untuk aktifkan absen</span>
                                                ) : (
                                                    <div className="space-y-1.5">
                                                        {r.locations.map((s) => (
                                                            <div key={s.id} className="flex items-center justify-between gap-2 bg-[#F8FAFC] rounded-xl px-3 py-2">
                                                                <Link href={`${base}/regions/${r.id}/sites/${s.id}`} className="min-w-0 flex-1">
                                                                    <p className="text-sm font-medium text-[#0F172A] hover:text-[#1E3A8A] truncate">{s.nama_lokasi}</p>
                                                                    <p className="text-xs text-[#64748B] font-mono">{Number(s.lat).toFixed(4)}, {Number(s.lng).toFixed(4)} • {s.radius} m • {countForSite(s.id)} anggota</p>
                                                                </Link>
                                                                <div className="flex items-center gap-1 shrink-0">
                                                                    <Link href={`${base}/regions/${r.id}/sites/${s.id}`} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-2.5 py-1 rounded-lg">Kelola</Link>
                                                                    {canEditRegion && <button type="button" onClick={() => handleDeleteSite(r.id, s.id)} title={`Hapus ${s.nama_lokasi}${countForSite(s.id) > 0 ? ` — ${countForSite(s.id)} anggota akan jadi Tanpa titik` : ''}`} className="text-xs text-[#991B1B] bg-[#FEF2F2] px-2 py-1 rounded-lg">Hapus</button>}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                                {canEditRegion && <button type="button" onClick={() => openAddSite(r)} className="mt-2 text-xs font-semibold bg-[#FFF7E6] text-[#92400E] px-3 py-1.5 rounded-lg border border-[#FCB833]/20">+ Tambah Titik (1 per aksi + peta)</button>}
                                            </td>
                                            <td className="px-4 py-3.5 text-right align-top">
                                                <div className="flex gap-1 justify-end">
                                                    <button type="button" onClick={() => openEditWilayah(r)} className={`text-xs font-medium px-3 py-1.5 rounded-lg ${canEditRegion ? 'text-[#1E3A8A] bg-[#EFF6FF]' : 'text-[#94A3B8] bg-[#F1F5F9] cursor-not-allowed'}`}>Edit Wilayah</button>
                                                    {!isWilayah && <button type="button" onClick={() => handleDeleteWilayah(r.id)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B] flex items-center justify-between">
                        <span>Menampilkan {filtered.length} dari {regions.length} wilayah • {filtered.reduce((a, r) => a + r.locations.length, 0)} titik • Karyawan per titik saja</span>
                        <span className="hidden lg:inline">Klik Kelola → halaman titik dedicated (peta + anggota)</span>
                    </div>
                </div>

                <div className="bg-[#FFF7E6] rounded-2xl p-4 flex gap-3">
                    <span className="w-8 h-8 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </span>
                    <div>
                        <p className="text-sm font-medium text-[#0F172A]">Alur per titik: Tambah 1 titik dengan peta → Kelola titik → Tambah anggota</p>
                        <p className="text-xs text-[#92400E]">1 karyawan = 1 titik — dipindah via halaman titik. Absen valid hanya di titik assigned-nya (dalam radius 50–1000m).</p>
                    </div>
                </div>

                {toast && <p className="text-xs text-center bg-[#ECFDF5] text-[#065F46] rounded-xl py-2 px-3">{toast}</p>}

                {wilayahOpen && (
                    <div className="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" onClick={closeWilayah}>
                        <div className="bg-white rounded-2xl w-full max-w-[520px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editingWilayah ? 'Edit Wilayah' : 'Tambah Wilayah'}</h3>
                                <button type="button" onClick={closeWilayah} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-3">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama wilayah</label>
                                    <input value={wilayahForm.name} onChange={(e) => setWilayahForm({ ...wilayahForm, name: e.target.value })} placeholder="Kab. Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama kantor</label>
                                    <input value={wilayahForm.kantor} onChange={(e) => setWilayahForm({ ...wilayahForm, kantor: e.target.value })} placeholder="Kantor Wilayah Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Tipe</label>
                                    <select value={wilayahForm.tipe} onChange={(e) => setWilayahForm({ ...wilayahForm, tipe: e.target.value })} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"><option value="pusat">pusat</option><option value="cabang">cabang</option></select>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Alamat</label>
                                    <input value={wilayahForm.address} onChange={(e) => setWilayahForm({ ...wilayahForm, address: e.target.value })} placeholder="Jl. Poros — Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                </div>
                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={closeWilayah} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={handleSaveWilayah} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan Wilayah</button>
                                </div>
                                <p className="text-xs text-[#94A3B8] text-center">Titik proyek ditambah terpisah per 1 titik dengan peta (tombol + Tambah Titik di tabel).</p>
                            </div>
                        </div>
                    </div>
                )}

                {addSiteFor && (
                    <div className="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4" onClick={closeAddSite}>
                        <div className="bg-white rounded-2xl w-full max-w-[640px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="sticky top-0 bg-white px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">Tambah Titik — {addSiteFor.name} (1 titik)</h3>
                                <button type="button" onClick={closeAddSite} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama titik</label>
                                    <input value={siteForm.nama_lokasi} onChange={(e) => setSiteForm({ ...siteForm, nama_lokasi: e.target.value })} placeholder="Bendungan Bili-Bili / Jembatan Pampang" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Alamat titik (opsional)</label>
                                    <input value={siteForm.address} onChange={(e) => setSiteForm({ ...siteForm, address: e.target.value })} placeholder="Jl. Poros — deskripsi titik" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Peta — klik untuk tentukan titik</label>
                                    <div ref={siteMapRef} className="mt-1.5 h-[280px] rounded-xl overflow-hidden border border-[#E2E8F0] bg-[#F1F5F9]" />
                                    <p className="text-xs text-[#94A3B8] mt-1">Klik peta untuk set lat/lng • Drag marker untuk geser</p>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <input value={siteForm.lat} onChange={(e) => setSiteForm({ ...siteForm, lat: e.target.value })} placeholder="Lat" type="number" step="0.000001" className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none" />
                                    <input value={siteForm.lng} onChange={(e) => setSiteForm({ ...siteForm, lng: e.target.value })} placeholder="Lng" type="number" step="0.000001" className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none" />
                                    <div className="flex items-center gap-1">
                                        <input value={siteForm.radius} onChange={(e) => setSiteForm({ ...siteForm, radius: e.target.value })} type="range" min="50" max="1000" step="10" className="flex-1 accent-[#FCB833]" />
                                        <span className="text-xs font-semibold bg-white border border-[#E2E8F0] px-2 py-1 rounded-lg shrink-0">{siteForm.radius} m</span>
                                    </div>
                                </div>
                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={closeAddSite} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={handleSaveSite} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan Titik</button>
                                </div>
                                <p className="text-xs text-[#94A3B8] text-center">Setelah simpan, buka Kelola → halaman titik untuk atur anggota (karyawan per titik saja).</p>
                            </div>
                        </div>
                    </div>
                )}

                {confirmDeleteSite && (
                    <div className="fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4" onClick={() => setConfirmDeleteSite(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4">
                                <h3 className="font-semibold text-[#0F172A]">Hapus {confirmDeleteSite.siteName}?</h3>
                                <p className="text-sm text-[#64748B] mt-2">{confirmDeleteSite.regionName} • {confirmDeleteSite.nAnggota > 0 ? <span className="font-semibold text-[#991B1B]">{confirmDeleteSite.nAnggota} anggota akan jadi Tanpa titik — tidak bisa absen (422)</span> : 'Tidak ada anggota di titik ini.'}</p>
                            </div>
                            <div className="px-6 pb-5 flex gap-2">
                                <button type="button" onClick={() => setConfirmDeleteSite(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                <button type="button" onClick={confirmDeleteSiteAction} className="flex-1 rounded-xl bg-[#EF4444] text-white py-3 text-sm font-semibold">Ya, hapus titik</button>
                            </div>
                        </div>
                    </div>
                )}

                {confirmDeleteWilayah && (
                    <div className="fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4" onClick={() => setConfirmDeleteWilayah(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4">
                                <h3 className="font-semibold text-[#0F172A]">Hapus wilayah {confirmDeleteWilayah.name}?</h3>
                                <p className="text-sm text-[#64748B] mt-2">{confirmDeleteWilayah.nSites} titik • {confirmDeleteWilayah.nEmp > 0 ? <span className="font-semibold text-[#991B1B]">{confirmDeleteWilayah.nEmp} karyawan akan jadi Tanpa titik — tidak bisa absen</span> : 'Tidak ada karyawan di wilayah ini.'}</p>
                                <p className="text-xs text-[#94A3B8] mt-1">Titik di wilayah ini juga terhapus.</p>
                            </div>
                            <div className="px-6 pb-5 flex gap-2">
                                <button type="button" onClick={() => setConfirmDeleteWilayah(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                <button type="button" onClick={confirmDeleteWilayahAction} className="flex-1 rounded-xl bg-[#EF4444] text-white py-3 text-sm font-semibold">Ya, hapus wilayah</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
