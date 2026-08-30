import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, useMemo } from 'react';
import { loadRegions, loadEmployees, saveRegions, saveEmployees, getBase, OWN_REGION } from './_shared';

// Dedicated page per titik — 1 karyawan = 1 titik saja
// Maps Leaflet per titik, anggota per titik, radius 50–1000m, pindah titik modal.

export default function SiteDetail({ regionId, siteId }) {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';

    const [regions, setRegions] = useState(loadRegions());
    const [employees, setEmployees] = useState(loadEmployees());
    const [toast, setToast] = useState(null);
    const [editOpen, setEditOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(null); // employee to move
    const [addOpen, setAddOpen] = useState(false);
    const [addQ, setAddQ] = useState('');
    const [selectedIds, setSelectedIds] = useState(() => new Set());
    const [confirmRemove, setConfirmRemove] = useState(null);
    const mapRef = useRef(null);
    const leafletRef = useRef(null);

    const region = useMemo(() => regions.find((r) => r.id === Number(regionId)), [regions, regionId]);
    const site = useMemo(() => region?.locations?.find((s) => s.id === Number(siteId)), [region, siteId]);

    const [form, setForm] = useState(() => site ? { nama_lokasi: site.nama_lokasi, lat: String(site.lat), lng: String(site.lng), radius: site.radius, address: site.address || '' } : { nama_lokasi: '', lat: '', lng: '', radius: 200, address: '' });
    useEffect(() => { if (site) setForm({ nama_lokasi: site.nama_lokasi, lat: String(site.lat), lng: String(site.lng), radius: site.radius, address: site.address || '' }); }, [site?.id]);
    useEffect(() => {
        const sync = () => { setRegions(loadRegions()); setEmployees(loadEmployees()); };
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        const onStorage = (e) => { if (!e.key || e.key === 'bbws_mock_regions_v3' || e.key === 'bbws_mock_employees_v3') sync(); };
        window.addEventListener('storage', onStorage);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); window.removeEventListener('storage', onStorage); };
    }, []);

    const canEdit = isWilayah ? region?.name === OWN_REGION : true;
    const anggota = useMemo(() => employees.filter((e) => e.office_location_id === Number(siteId)), [employees, siteId]);
    const kandidatTambah = useMemo(() => {
        if (!region) return [];
        // Kandidat = karyawan di region yang sama yang belum punya titik atau di titik ini sudah (filter q)
        // Karyawan per titik saja — jadi tampilkan yang office_location_id null atau sudah di site ini? Untuk assign: yang belum punya titik di region ini (null) atau beda titik bisa pindah via Move.
        const pool = employees.filter((e) => e.regionId === region.id && e.office_location_id == null);
        const q = addQ.toLowerCase().trim();
        if (!q) return pool;
        return pool.filter((e) => e.nama.toLowerCase().includes(q) || e.email.toLowerCase().includes(q) || e.nik.includes(q));
    }, [employees, region, addQ]);

    const showToast = (msg, ok = true) => { setToast({ msg, ok }); setTimeout(() => setToast(null), 2500); };

    // Leaflet map per titik
    useEffect(() => {
        if (!site || !mapRef.current || leafletRef.current) return;
        let mounted = true;
        (async () => {
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            if (!mounted || !mapRef.current) return;
            // fix default icon
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            });
            const map = L.map(mapRef.current).setView([site.lat, site.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' }).addTo(map);
            let marker = L.marker([site.lat, site.lng], { draggable: canEdit }).addTo(map);
            let circle = L.circle([site.lat, site.lng], { radius: site.radius, color: '#FCB833', fillColor: '#FCB833', fillOpacity: 0.12, weight: 2 }).addTo(map);
            const updateFromMarker = (latlng) => {
                const { lat, lng } = latlng;
                setForm((f) => ({ ...f, lat: String(lat.toFixed(6)), lng: String(lng.toFixed(6)) }));
                circle.setLatLng(latlng);
            };
            if (canEdit) {
                marker.on('dragend', () => updateFromMarker(marker.getLatLng()));
                map.on('click', (e) => { marker.setLatLng(e.latlng); updateFromMarker(e.latlng); });
            }
            leafletRef.current = { map, marker, circle, L };
        })();
        return () => { mounted = false; if (leafletRef.current?.map) { leafletRef.current.map.remove(); leafletRef.current = null; } };
    }, [site?.id, canEdit]);

    // sync circle radius when form.radius changes
    useEffect(() => {
        if (leafletRef.current?.circle) leafletRef.current.circle.setRadius(Number(form.radius) || 200);
    }, [form.radius]);
    useEffect(() => {
        if (leafletRef.current?.marker && site) {
            const lat = Number(form.lat), lng = Number(form.lng);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                leafletRef.current.marker.setLatLng([lat, lng]);
                leafletRef.current.circle.setLatLng([lat, lng]);
                leafletRef.current.map.setView([lat, lng], leafletRef.current.map.getZoom());
            }
        }
    }, [form.lat, form.lng]);

    const handleSaveSite = () => {
        if (!canEdit) { showToast('Hanya own region bisa edit titik', false); return; }
        if (!form.nama_lokasi.trim()) { showToast('Nama titik wajib', false); return; }
        const lat = Number(form.lat), lng = Number(form.lng), radius = Number(form.radius);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) { showToast('Lat/Lng tidak valid', false); return; }
        if (radius < 50 || radius > 1000) { showToast('Radius 50–1000m', false); return; }
        setRegions((prev) => {
            const next = prev.map((r) => r.id !== region.id ? r : { ...r, locations: r.locations.map((s) => s.id !== site.id ? s : { ...s, nama_lokasi: form.nama_lokasi.trim(), lat, lng, radius, address: form.address.trim() }) });
            saveRegions(next);
            return next;
        });
        setEditOpen(false);
        showToast('Titik diperbarui');
    };

    const handleRemoveFromSite = (empId) => {
        if (!canEdit) { showToast('Hanya own region', false); return; }
        setEmployees((prev) => {
            const next = prev.map((e) => e.id === empId ? { ...e, office_location_id: null } : e);
            saveEmployees(next); return next;
        });
        setConfirmRemove(null);
        showToast('Karyawan dikeluarkan dari titik — jadi Tanpa titik (tidak bisa absen)');
    };

    const handleAssign = (empId) => {
        if (!canEdit) return;
        setEmployees((prev) => {
            const next = prev.map((e) => e.id === empId ? { ...e, office_location_id: site.id } : e);
            saveEmployees(next); return next;
        });
        setSelectedIds((prev) => { const n = new Set(prev); n.delete(empId); return n; });
        showToast('Karyawan ditambahkan ke titik');
    };

    const toggleSelect = (id) => {
        setSelectedIds((prev) => {
            const n = new Set(prev);
            if (n.has(id)) n.delete(id); else n.add(id);
            return n;
        });
    };
    const toggleSelectAll = () => {
        if (selectedIds.size === kandidatTambah.length) setSelectedIds(new Set());
        else setSelectedIds(new Set(kandidatTambah.map((e) => e.id)));
    };
    const handleBulkAssign = () => {
        if (!canEdit || selectedIds.size === 0) return;
        setEmployees((prev) => {
            const next = prev.map((e) => selectedIds.has(e.id) ? { ...e, office_location_id: site.id } : e);
            saveEmployees(next); return next;
        });
        const n = selectedIds.size;
        setSelectedIds(new Set());
        setAddOpen(false); setAddQ('');
        showToast(`${n} karyawan ditambahkan ke titik`);
    };
    const handleConfirmRemove = () => {
        if (confirmRemove) handleRemoveFromSite(confirmRemove.id);
    };

    const handleMove = (targetSiteId) => {
        if (!moveOpen || !canEdit) return;
        setEmployees((prev) => {
            const next = prev.map((e) => e.id === moveOpen.id ? { ...e, office_location_id: targetSiteId } : e);
            saveEmployees(next); return next;
        });
        setMoveOpen(null);
        showToast('Karyawan dipindah titik');
    };

    if (!region || !site) {
        return (
            <AdminLayout>
                <div className="py-10 text-center">
                    <p className="text-sm text-[#64748B]">Titik tidak ditemukan</p>
                    <Link href={`${base}/regions`} className="mt-3 inline-block text-sm text-[#1E3A8A] font-medium">← Kembali ke daftar wilayah</Link>
                </div>
            </AdminLayout>
        );
    }

    const otherSitesInRegion = region.locations.filter((s) => s.id !== site.id);

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-2 text-xs text-[#64748B]">
                    <Link href={`${base}/regions`} className="hover:text-[#0F172A]">{isWilayah ? 'Titik Proyek' : 'Kantor Wilayah'}</Link>
                    <span>›</span>
                    <Link href={`${base}/regions`} className="hover:text-[#0F172A]">{region.name}</Link>
                    <span>›</span>
                    <span className="font-medium text-[#0F172A]">{site.nama_lokasi}</span>
                </div>

                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{site.nama_lokasi}</h1>
                        <p className="text-sm text-[#64748B]">{region.name} • {site.lat.toFixed(4)}, {site.lng.toFixed(4)} • {site.radius} m • {anggota.length} anggota (karyawan per titik saja)</p>
                        {!canEdit && <span className="inline-block mt-2 text-xs bg-white border border-[#E2E8F0] px-2 py-1 rounded-full text-[#64748B]">Read-only — bukan own region</span>}
                    </div>
                    {canEdit && <button type="button" onClick={() => setEditOpen(true)} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0">Edit Titik</button>}
                </div>

                <div className="grid lg:grid-cols-[1.1fr_0.9fr] gap-4">
                    <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                        <div className="px-4 py-3 border-b flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-[#0F172A]">Peta Titik — klik peta / drag marker untuk ubah koordinat</h3>
                            <span className="text-xs bg-[#FFF7E6] text-[#92400E] px-2 py-1 rounded-full border border-[#FCB833]/20">{site.radius} m radius</span>
                        </div>
                        <div ref={mapRef} className="h-[360px] w-full bg-[#F1F5F9]" />
                        <div className="px-4 py-3 bg-[#F8FAFC] flex flex-wrap gap-2 text-xs">
                            <span className="bg-white border border-[#E2E8F0] px-2.5 py-1 rounded-full font-mono">{Number(site.lat).toFixed(6)}, {Number(site.lng).toFixed(6)}</span>
                            <span className="text-[#64748B] self-center">Geser slider di Edit untuk ubah radius • Klik peta saat edit</span>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] p-4">
                            <h3 className="text-sm font-semibold text-[#0F172A]">Detail Titik</h3>
                            <dl className="mt-3 space-y-2 text-sm">
                                <div className="flex justify-between gap-2"><dt className="text-[#64748B]">Nama</dt><dd className="font-medium text-[#0F172A] text-right">{site.nama_lokasi}</dd></div>
                                <div className="flex justify-between gap-2"><dt className="text-[#64748B]">Wilayah</dt><dd className="font-medium text-[#0F172A]">{region.name} ({region.kantor})</dd></div>
                                <div className="flex justify-between gap-2"><dt className="text-[#64748B]">Koordinat</dt><dd className="font-mono text-xs text-[#0F172A]">{Number(site.lat).toFixed(6)}, {Number(site.lng).toFixed(6)}</dd></div>
                                <div className="flex justify-between gap-2"><dt className="text-[#64748B]">Radius</dt><dd className="font-medium text-[#92400E] bg-[#FFF7E6] px-2 py-0.5 rounded-full border border-[#FCB833]/20 text-xs">{site.radius} m</dd></div>
                                {site.address && <div className="flex justify-between gap-2"><dt className="text-[#64748B]">Alamat</dt><dd className="text-xs text-[#334155] text-right max-w-[60%]">{site.address}</dd></div>}
                            </dl>
                            <p className="mt-3 text-xs text-[#94A3B8]">Absen valid hanya jika karyawan assigned ke titik ini & dalam radius — 1 karyawan = 1 titik saja.</p>
                        </div>

                        <div className="bg-[#FFF7E6] rounded-2xl p-4 flex gap-3">
                            <span className="w-8 h-8 rounded-xl bg-[#FCB833] flex items-center justify-center shrink-0">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.6"><path d="M12 21s7-5.5 7-11a7 7 0 10-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            </span>
                            <div>
                                <p className="text-sm font-medium text-[#0F172A]">Karyawan per titik saja</p>
                                <p className="text-xs text-[#92400E]">Pindah titik = re-assign — karyawan hanya bisa absen di titik yang ditetapkan. Admin Wilayah kelola own region saja.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-4 py-3 flex items-center justify-between border-b">
                        <h3 className="text-sm font-semibold text-[#0F172A]">Anggota — {anggota.length} karyawan di titik ini</h3>
                        {canEdit && <button type="button" onClick={() => setAddOpen(true)} className="text-xs font-semibold bg-[#0F172A] text-white px-3 py-1.5 rounded-lg">+ Tambah Anggota</button>}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]"><tr><th className="text-left px-4 py-3">Karyawan</th><th className="text-left px-4 py-3">Jabatan</th><th className="text-left px-4 py-3">Status</th><th className="px-4 py-3"></th></tr></thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {anggota.map((e) => (
                                    <tr key={e.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <img src={e.foto} alt={e.nama} className="w-8 h-8 rounded-full object-cover" />
                                                <div>
                                                    <p className="font-medium text-[#0F172A] text-sm">{e.nama}</p>
                                                    <p className="text-xs text-[#64748B]">{e.email} • {e.nik}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">{e.jabatan} • {e.unit}</td>
                                        <td className="px-4 py-3 text-xs"><span className="bg-[#F1F5F9] px-2 py-1 rounded-full">{e.status}</span></td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end">
                                                <button type="button" onClick={() => setMoveOpen(e)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Pindah</button>
                                                <button type="button" onClick={() => setConfirmRemove(e)} title="Keluarkan → jadi Tanpa titik (tidak bisa absen)" className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Keluarkan</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {anggota.length === 0 && <p className="text-center text-sm text-[#94A3B8] py-8">Belum ada anggota di titik ini — tambah karyawan own region.</p>}
                    <div className="px-4 py-3 bg-[#F8FAFC] text-xs text-[#64748B]">1 karyawan = 1 titik — dipindah lewat tombol Pindah • Di luar titik assigned ditolak 422.</div>
                </div>

                {toast && <p className={`text-xs text-center rounded-xl py-2 px-3 ${toast.ok ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{toast.msg}</p>}

                {editOpen && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={() => setEditOpen(false)}>
                        <div className="bg-white rounded-2xl w-full max-w-[520px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">Edit Titik Proyek</h3>
                                <button type="button" onClick={() => setEditOpen(false)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-3">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama titik</label>
                                    <input value={form.nama_lokasi} onChange={(e) => setForm({ ...form, nama_lokasi: e.target.value })} placeholder="Bendungan Bili-Bili" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Alamat titik (opsional)</label>
                                    <input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} placeholder="Jl. Poros Malino — Gowa" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Lat</label>
                                        <input value={form.lat} onChange={(e) => setForm({ ...form, lat: e.target.value })} type="number" step="0.000001" className="mt-1.5 w-full rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Lng</label>
                                        <input value={form.lng} onChange={(e) => setForm({ ...form, lng: e.target.value })} type="number" step="0.000001" className="mt-1.5 w-full rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none" />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Radius {form.radius} m</label>
                                    <input value={form.radius} onChange={(e) => setForm({ ...form, radius: e.target.value })} type="range" min="50" max="1000" step="10" className="mt-1 w-full accent-[#FCB833]" />
                                    <div className="flex justify-between text-xs text-[#94A3B8]"><span>50m</span><span>1000m</span></div>
                                </div>
                                <p className="text-xs text-[#94A3B8]">Klik/drag di peta untuk ubah lat/lng — circle update live.</p>
                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={() => setEditOpen(false)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={handleSaveSite} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan Titik</button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {addOpen && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={() => { setAddOpen(false); setSelectedIds(new Set()); }}>
                        <div className="bg-white rounded-2xl w-full max-w-[520px] max-h-[80vh] overflow-hidden shadow-xl flex flex-col" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4 border-b shrink-0">
                                <h3 className="font-semibold text-[#0F172A]">Tambah Anggota ke {site.nama_lokasi}</h3>
                                <p className="text-xs text-[#64748B]">Hanya karyawan own region tanpa titik ({region.name}) — 1 karyawan = 1 titik. Pilih beberapa sekaligus.</p>
                                <input value={addQ} onChange={(e) => setAddQ(e.target.value)} placeholder="Cari nama / email / NIK..." className="mt-3 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                                {kandidatTambah.length > 0 && (
                                    <div className="mt-3 flex items-center justify-between">
                                        <label className="flex items-center gap-2 text-xs font-medium text-[#334155] cursor-pointer">
                                            <input type="checkbox" checked={selectedIds.size === kandidatTambah.length && kandidatTambah.length > 0} onChange={toggleSelectAll} className="rounded border-[#CBD5E1]" /> Pilih semua ({kandidatTambah.length})
                                        </label>
                                        <span className="text-xs text-[#64748B]">{selectedIds.size} dipilih</span>
                                    </div>
                                )}
                            </div>
                            <div className="overflow-y-auto flex-1 divide-y divide-[#F1F5F9]">
                                {kandidatTambah.map((e) => (
                                    <label key={e.id} className="px-4 py-3 flex items-center gap-3 hover:bg-[#F8FAFC] cursor-pointer">
                                        <input type="checkbox" checked={selectedIds.has(e.id)} onChange={() => toggleSelect(e.id)} className="rounded border-[#CBD5E1] shrink-0" />
                                        <img src={e.foto} alt={e.nama} className="w-8 h-8 rounded-full object-cover shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-[#0F172A] truncate">{e.nama}</p>
                                            <p className="text-xs text-[#64748B] truncate">{e.email} • {e.jabatan}</p>
                                        </div>
                                        <button type="button" onClick={(ev) => { ev.preventDefault(); handleAssign(e.id); }} className="shrink-0 text-xs font-semibold bg-white border border-[#E2E8F0] text-[#0F172A] px-3 py-1.5 rounded-lg hover:bg-[#F8FAFC]">+1</button>
                                    </label>
                                ))}
                                {kandidatTambah.length === 0 && (
                                    <div className="py-8 px-6 text-center">
                                        <p className="text-sm text-[#94A3B8]">Tidak ada kandidat — semua karyawan {region.name} sudah punya titik, atau filter tidak cocok.</p>
                                        <Link href={`${base}/employees`} className="mt-3 inline-block text-xs font-semibold bg-[#0F172A] text-white px-4 py-2 rounded-xl">+ Buat karyawan baru di {region.name} →</Link>
                                    </div>
                                )}
                            </div>
                            <div className="px-4 py-3 flex items-center justify-between gap-2 border-t bg-white shrink-0">
                                <p className="text-xs text-[#64748B]">{selectedIds.size === 0 ? 'Centang beberapa, atau +1 per baris' : `${selectedIds.size} akan ditambah ke ${site.nama_lokasi}`}</p>
                                <button type="button" onClick={handleBulkAssign} disabled={selectedIds.size === 0} title={selectedIds.size === 0 ? 'Pilih minimal 1 karyawan' : `Tambah ${selectedIds.size} ke titik`} className={`text-sm font-semibold px-4 py-2 rounded-xl shrink-0 ${selectedIds.size === 0 ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>Tambah ({selectedIds.size})</button>
                            </div>
                        </div>
                    </div>
                )}

                {confirmRemove && (
                    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" onClick={() => setConfirmRemove(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4">
                                <h3 className="font-semibold text-[#0F172A]">Keluarkan {confirmRemove.nama}?</h3>
                                <p className="text-sm text-[#64748B] mt-2">Akan jadi <span className="font-semibold text-[#991B1B]">Tanpa titik</span> — tidak bisa absen (422) & Love ditolak sampai di-assign lagi ke titik di {region.name}.</p>
                                <p className="text-xs text-[#94A3B8] mt-2">Dari: {site.nama_lokasi} • {site.radius} m</p>
                            </div>
                            <div className="px-6 pb-5 flex gap-2">
                                <button type="button" onClick={() => setConfirmRemove(null)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                <button type="button" onClick={handleConfirmRemove} className="flex-1 rounded-xl bg-[#EF4444] text-white py-3 text-sm font-semibold">Ya, keluarkan</button>
                            </div>
                        </div>
                    </div>
                )}

                {moveOpen && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={() => setMoveOpen(null)}>
                        <div className="bg-white rounded-2xl w-full max-w-[420px] shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="px-6 py-4 border-b">
                                <h3 className="font-semibold text-[#0F172A]">Pindah {moveOpen.nama}</h3>
                                <p className="text-xs text-[#64748B]">Dari {site.nama_lokasi} ke titik lain di {region.name} — 1 karyawan = 1 titik.</p>
                            </div>
                            <div className="p-4 space-y-2">
                                {otherSitesInRegion.length === 0 && <p className="text-sm text-[#94A3B8] text-center py-4">Tidak ada titik lain di wilayah ini — tambah titik dulu.</p>}
                                {otherSitesInRegion.map((s) => (
                                    <button key={s.id} type="button" onClick={() => handleMove(s.id)} className="w-full text-left bg-[#F8FAFC] hover:bg-[#EFF6FF] rounded-xl px-4 py-3 flex items-center justify-between">
                                        <span>
                                            <p className="text-sm font-medium text-[#0F172A]">{s.nama_lokasi}</p>
                                            <p className="text-xs text-[#64748B]">{s.lat.toFixed(4)}, {s.lng.toFixed(4)} • {s.radius} m</p>
                                        </span>
                                        <span className="text-xs font-semibold text-[#1E3A8A]">Pindah →</span>
                                    </button>
                                ))}
                                <button type="button" onClick={() => { setMoveOpen(null); setConfirmRemove(moveOpen); }} className="w-full mt-2 rounded-xl bg-[#FEF2F2] text-[#991B1B] py-2.5 text-sm font-semibold">Keluarkan (tanpa titik)</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
