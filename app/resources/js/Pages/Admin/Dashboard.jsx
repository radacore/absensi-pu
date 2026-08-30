import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { loadRegions, loadEmployees } from './_shared';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

const wilayahList = ['Semua','Kota Makassar','Kab. Gowa','Kab. Maros','Kab. Bone','Kota Parepare','Kota Palopo','Kab. Bantaeng','Kab. Barru','Kab. Bulukumba','Kab. Enrekang','Kab. Jeneponto','Kab. Kepulauan Selayar','Kab. Luwu','Kab. Luwu Timur','Kab. Luwu Utara','Kab. Pangkajene dan Kepulauan','Kab. Pinrang','Kab. Sinjai','Kab. Soppeng','Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo','Kab. Sidrap'];

const attendanceSeed = [
    { name: 'Kantor Pusat', wilayah: 'Kota Makassar', hadir: 142, total: 148, late: 6 },
    { name: 'Kantor Gowa', wilayah: 'Kab. Gowa', hadir: 68, total: 72, late: 2 },
    { name: 'Kantor Maros', wilayah: 'Kab. Maros', hadir: 54, total: 58, late: 1 },
    { name: 'Kantor Bone', wilayah: 'Kab. Bone', hadir: 48, total: 52, late: 3 },
    { name: 'Kantor Parepare', wilayah: 'Kota Parepare', hadir: 36, total: 38, late: 0 },
    { name: 'Kantor Palopo', wilayah: 'Kota Palopo', hadir: 42, total: 44, late: 2 },
    { name: 'Kantor Takalar', wilayah: 'Kab. Takalar', hadir: 28, total: 32, late: 4 },
    { name: 'Kantor Bantaeng', wilayah: 'Kab. Bantaeng', hadir: 22, total: 24, late: 1 },
    { name: 'Kantor Bulukumba', wilayah: 'Kab. Bulukumba', hadir: 31, total: 34, late: 2 },
    { name: 'Kantor Sinjai', wilayah: 'Kab. Sinjai', hadir: 18, total: 20, late: 0 },
];

export default function Dashboard() {
    const { url } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const OWN_DASH = 'Kab. Gowa';
    const [regionsData, setRegionsData] = useState(() => loadRegions());
    const [employees, setEmployees] = useState(() => loadEmployees());
    const [wilayah, setWilayah] = useState(isWilayah ? OWN_DASH : 'Semua');
    const [siteFilter, setSiteFilter] = useState('Semua');
    const [tgl] = useState(() => new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }));

    useEffect(() => {
        const sync = () => { setRegionsData(loadRegions()); setEmployees(loadEmployees()); };
        window.addEventListener('focus', sync);
        const onVis = () => { if (document.visibilityState === 'visible') sync(); };
        document.addEventListener('visibilitychange', onVis);
        return () => { window.removeEventListener('focus', sync); document.removeEventListener('visibilitychange', onVis); };
    }, []);
    useEffect(() => { setSiteFilter('Semua'); }, [wilayah]);

    const filtered = useMemo(() => {
        if (isWilayah) return attendanceSeed.filter((c) => c.wilayah === OWN_DASH);
        return wilayah === 'Semua' ? attendanceSeed : attendanceSeed.filter((c) => c.wilayah === wilayah);
    }, [wilayah, isWilayah]);
    const hadir = filtered.reduce((s, c) => s + c.hadir, 0);
    const total = filtered.reduce((s, c) => s + c.total, 0);
    const late = filtered.reduce((s, c) => s + c.late, 0);
    const pct = total ? Math.round((hadir / total) * 100) : 0;

    // per-titik breakdown: pakai regionsData + employees per titik
    const activeRegionName = isWilayah ? OWN_DASH : wilayah;
    const activeRegion = useMemo(() => regionsData.find((r) => r.name === activeRegionName) || null, [regionsData, activeRegionName]);
    const sitesForActive = activeRegion ? activeRegion.locations : [];
    const countsPerSite = useMemo(() => {
        if (!activeRegion) return [];
        return sitesForActive.map((s) => ({
            site: s,
            anggota: employees.filter((e) => e.office_location_id === s.id).length,
        }));
    }, [sitesForActive, employees, activeRegion]);
    const siteCards = siteFilter === 'Semua' ? countsPerSite : countsPerSite.filter((x) => String(x.site.id) === String(siteFilter));
    const totalTanpaTitik = useMemo(() => {
        if (!activeRegion) return 0;
        return employees.filter((e) => e.regionId === activeRegion.id && e.office_location_id == null).length;
    }, [employees, activeRegion]);

    const stats = isWilayah
        ? [
            { label: 'Total Karyawan', value: String(total), sub: `${OWN_DASH} • 1 kantor`, accent: 'gold', href: `${base}/employees` },
            { label: 'Hadir hari ini', value: String(hadir), sub: `${pct}% • ${late} late • ${OWN_DASH}`, accent: 'emerald', href: `${base}/attendances` },
            { label: 'Cuti pending', value: '3', sub: `${OWN_DASH} • butuh approval`, accent: 'amber', href: `${base}/cuti` },
            { label: 'Love pending', value: '2', sub: `${OWN_DASH} • claim hari ini`, accent: 'gold', href: `${base}/love` },
          ]
        : [
            { label: 'Total Karyawan', value: wilayah === 'Semua' ? '1,248' : String(total), sub: wilayah === 'Semua' ? '24 wilayah' : `${wilayah}`, accent: 'gold', href: `${base}/employees` },
            { label: 'Hadir hari ini', value: String(hadir), sub: `${pct}% • ${late} late`, accent: 'emerald', href: `${base}/attendances` },
            { label: 'Cuti pending', value: '23', sub: 'butuh approval • semua wilayah', accent: 'amber', href: `${base}/cuti` },
            { label: 'Love pending', value: '8', sub: 'claim hari ini • semua wilayah', accent: 'gold', href: `${base}/love` },
          ];

    const wilayahActivities = [
        { t: '07:52 — Andi Saputra (Gowa) terlambat 7m — dalam radius 42m', tag: 'late', color: 'amber' },
        { t: '08:10 — Love claim oleh Andi Saputra — menunggu Admin Gowa (bulan sama)', tag: 'love', color: 'gold' },
        { t: '07:44 — Dewi Lestari (Gowa) hadir tepat waktu — 31m', tag: 'on_time', color: 'emerald' },
        { t: '09:05 — Cuti diajukan Nurul (Gowa) — Tahunan 2 hari — level 1', tag: 'cuti', color: 'sky' },
        { t: '07:38 — Rudi Hartono (Gowa) hadir — radius 27m — multi-lokasi terdekat', tag: 'on_time', color: 'emerald' },
    ];
    const allActivities = [
        { t: '07:52 — Andi Saputra (Gowa) terlambat 7m — dalam radius 42m', tag: 'late', color: 'amber' },
        { t: '07:38 — Siti Rahma (Makassar) hadir tepat waktu — 38m', tag: 'on_time', color: 'emerald' },
        { t: '08:10 — Love claim oleh Andi Saputra — menunggu Admin Gowa (bulan sama)', tag: 'love', color: 'gold' },
        { t: '09:15 — Cuti diajukan Rudi (Bone) — Tahunan 3 hari — level 1', tag: 'cuti', color: 'sky' },
        { t: '07:40 — Budi Santoso (Maros) hadir — radius 21m — multi-lokasi terdekat', tag: 'on_time', color: 'emerald' },
    ];
    const activities = isWilayah ? wilayahActivities : allActivities;

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">{isWilayah ? `Dashboard ${OWN_DASH}` : `Dashboard ${wilayah === 'Semua' ? 'Super Admin' : 'Admin Wilayah'}`}</h1>
                        <p className="text-sm text-[#64748B]">{isWilayah ? `${OWN_DASH} • ${tgl} • WITA` : `Kantor Pusat Makassar • 24 Kantor Wilayah Sulsel • ${tgl} • WITA`}</p>
                        <p className="text-xs text-[#94A3B8] mt-1">{isWilayah ? `Hanya data ${OWN_DASH} — tidak tampil wilayah lain` : wilayah === 'Semua' ? 'Super Admin — semua wilayah' : `Filter: ${wilayah}`}</p>
                    </div>
                    <div className="flex items-center gap-2 shrink-0 flex-wrap">
                        {isWilayah ? (
                            <span className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm font-medium text-[#0F172A]">{OWN_DASH}</span>
                        ) : (
                            <select value={wilayah} onChange={(e)=>setWilayah(e.target.value)} className="rounded-xl bg-white border border-[#E2E8F0] px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#1E3A8A]/10">
                                {wilayahList.map((w)=><option key={w} value={w}>{w}</option>)}
                            </select>
                        )}
                        {(isWilayah || wilayah !== 'Semua') && activeRegion && sitesForActive.length > 0 && (
                            <select value={siteFilter} onChange={(e)=>setSiteFilter(e.target.value)} className="rounded-xl bg-[#FFF7E6] border border-[#FCB833]/30 px-3 py-2.5 text-sm outline-none">
                                <option value="Semua">Semua titik ({sitesForActive.length})</option>
                                {sitesForActive.map((s)=><option key={s.id} value={String(s.id)}>{s.nama_lokasi} • {s.radius}m</option>)}
                            </select>
                        )}
                        <Link href={`${base}/attendances`} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">Lihat Absensi →</Link>
                    </div>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {stats.map((s) => (
                        <Link key={s.label} href={s.href} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] hover:shadow-[0_4px_24px_rgba(15,23,42,0.08)] transition text-left">
                            <p className="text-xs font-medium text-[#94A3B8]">{s.label}</p>
                            <p className="text-2xl font-semibold tracking-tight text-[#0F172A] mt-1">{s.value}</p>
                            <p className="text-xs text-[#64748B] mt-1">{s.sub}</p>
                            <span className={`mt-3 inline-block w-8 h-1 rounded-full ${s.accent === 'gold' ? 'bg-[#FCB833]' : s.accent === 'emerald' ? 'bg-[#10B981]' : 'bg-[#F59E0B]'}`}></span>
                        </Link>
                    ))}
                </div>

                {/* Per-titik breakdown ketika fokus 1 wilayah / own region */}
                {(isWilayah || wilayah !== 'Semua') && activeRegion && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <div className="flex items-center justify-between">
                            <h3 className="font-medium text-sm text-[#0F172A]">Breakdown per Titik — {activeRegion.name} • {activeRegion.kantor}</h3>
                            <span className="text-xs text-[#94A3B8]">{sitesForActive.length} titik • 1 karyawan = 1 titik • {totalTanpaTitik} tanpa titik</span>
                        </div>
                        <div className="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {siteCards.map(({ site, anggota }) => (
                                <Link key={site.id} href={`${base}/regions/${activeRegion.id}/sites/${site.id}`} className="rounded-2xl border border-[#E2E8F0] p-4 hover:border-[#FCB833]/40 hover:bg-[#FFF7E6]/40 transition text-left">
                                    <p className="text-sm font-semibold text-[#0F172A] truncate">{site.nama_lokasi}</p>
                                    <p className="text-xs font-mono text-[#64748B] mt-1">{site.lat.toFixed(4)}, {site.lng.toFixed(4)} • {site.radius} m</p>
                                    <div className="mt-3 flex items-center justify-between">
                                        <span className="text-xs bg-[#0F172A] text-white px-2 py-1 rounded-full">{anggota} anggota</span>
                                        <span className="text-xs font-semibold text-[#1E3A8A]">Kelola →</span>
                                    </div>
                                    {site.address && <p className="text-xs text-[#94A3B8] mt-2 truncate">{site.address}</p>}
                                </Link>
                            ))}
                        </div>
                        {sitesForActive.length === 0 && <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada titik di {activeRegion.name} — tambah 1 titik di Kelola Wilayah.</p>}
                        {totalTanpaTitik > 0 && <p className="text-xs text-[#92400E] bg-[#FFF7E6] border border-[#FCB833]/20 rounded-xl px-3 py-2 mt-3">{totalTanpaTitik} karyawan {activeRegion.name} belum punya titik — tidak bisa absen (422). Assign di halaman titik.</p>}
                    </div>
                )}

                <div className="grid lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2 bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                        <div className="flex items-center justify-between">
                            <h3 className="font-medium text-sm text-[#0F172A]">{isWilayah ? `Kehadiran ${OWN_DASH} (hari ini)` : 'Kehadiran per wilayah (hari ini)'}</h3>
                            <span className="text-xs text-[#94A3B8]">{isWilayah ? `1 kantor • ${OWN_DASH}` : `${filtered.length} wilayah ${wilayah !== 'Semua' ? `• ${wilayah}` : '• top 10'}`}</span>
                        </div>
                        <div className="mt-4 space-y-3 max-h-[320px] overflow-y-auto pr-1">
                            {filtered.map((c) => (
                                <div key={c.name} className="flex items-center gap-3">
                                    <span className="text-xs font-medium text-[#334155] w-[160px] truncate" title={c.wilayah}>{c.name} <span className="text-[#94A3B8] font-normal">• {c.wilayah.replace('Kab. ','').replace('Kota ','')}</span></span>
                                    <div className="flex-1 h-2 bg-[#F1F5F9] rounded-full overflow-hidden">
                                        <div className="h-full bg-[#0F172A]" style={{ width: `${(c.hadir / c.total) * 100}%` }}></div>
                                    </div>
                                    <span className="text-xs text-[#64748B] whitespace-nowrap">{c.hadir}/{c.total} • <span className="text-[#92400E] font-medium">{c.late} late</span></span>
                                </div>
                            ))}
                            {filtered.length===0 && <p className="text-xs text-[#94A3B8] text-center py-6">Tidak ada data untuk wilayah ini (seed top 10)</p>}
                        </div>
                          <div className="mt-4 flex flex-wrap gap-2">
                            {isWilayah ? (
                                <>
                                    <Link href={`${base}/regions`} className="text-xs font-medium bg-[#F8FAFC] text-[#334155] px-3 py-1.5 rounded-full border">{OWN_DASH} • Kelola Lokasi →</Link>
                                    <Link href={`${base}/employees`} className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-3 py-1.5 rounded-full border border-[#FCB833]/20">Karyawan {OWN_DASH}</Link>
                                </>
                            ) : (
                                <>
                                    <Link href={`${base}/regions`} className="text-xs font-medium bg-[#F8FAFC] text-[#334155] px-3 py-1.5 rounded-full border">Kelola 24 Kantor Wilayah →</Link>
                                    <Link href={`${base}/employees`} className="text-xs font-medium bg-[#FFF7E6] text-[#92400E] px-3 py-1.5 rounded-full border border-[#FCB833]/20">Karyawan</Link>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="bg-[#0F172A] rounded-2xl p-5 text-white flex flex-col">
                        <h3 className="font-medium text-sm">{isWilayah ? `Info ${OWN_DASH}` : 'Global Settings'}</h3>
                        <p className="text-xs text-white/50 mt-1">{isWilayah ? `Kebijakan dari Pusat • read-only` : 'Hanya Super Admin bisa edit • berlaku konsisten'}</p>
                        <div className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between"><span className="text-white/60">Jam kerja</span><span className="font-medium">07:30–16:00 WITA</span></div>
                            <div className="flex justify-between"><span className="text-white/60">Toleransi</span><span className="font-medium">15 menit</span></div>
                            <div className="flex justify-between items-center"><span className="text-white/60">Love / bulan</span><span className="font-medium bg-[#FCB833] text-[#0F172A] px-2 py-0.5 rounded-full text-xs">4</span></div>
                            <div className="flex justify-between"><span className="text-white/60">Hari kerja</span><span className="font-medium">Sen–Jum</span></div>
                            <div className="flex justify-between"><span className="text-white/60">Timezone</span><span className="font-medium">Asia/Makassar</span></div>
                        </div>
                        <p className="text-xs text-white/50 mt-4">{isWilayah ? 'Diatur Pusat — lihat di Pengaturan (read-only)' : 'Fleksibel — Super Admin atur di Pengaturan, berlaku bulan depan • Reset Love 1st 00:00 WITA'}</p>
                        <Link href={`${base}/settings`} className="mt-4 bg-white text-[#0F172A] rounded-xl py-2.5 text-sm font-semibold text-center">{isWilayah ? 'Lihat Pengaturan' : 'Buka Pengaturan'}</Link>
                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <Link href={`${base}/cuti`} className="bg-white/10 rounded-xl py-2 text-xs font-medium text-center">{isWilayah ? `Cuti ${OWN_DASH.replace('Kab. ','')}` : 'Cuti berjenjang'}</Link>
                            <Link href={`${base}/love`} className="bg-[#FCB833] text-[#0F172A] rounded-xl py-2 text-xs font-semibold text-center">Love claims</Link>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">{isWilayah ? `Aktivitas terbaru — ${OWN_DASH}` : 'Aktivitas terbaru'}</h3>
                        <span className="text-xs text-[#94A3B8]">Hari ini • {hadir} hadir {isWilayah ? `• ${OWN_DASH}` : ''}</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {activities.map((a, i) => (
                            <div key={i} className="px-5 py-3 flex items-center gap-3">
                                <span className={`w-2 h-2 rounded-full shrink-0 ${a.color === 'gold' ? 'bg-[#FCB833]' : a.color === 'emerald' ? 'bg-[#10B981]' : a.color === 'amber' ? 'bg-[#F59E0B]' : 'bg-[#0EA5E9]'}`}></span>
                                <p className="text-sm text-[#334155] flex-1 min-w-0 truncate">{a.t}</p>
                                <span className="text-xs font-medium px-2 py-1 rounded-full bg-[#F8FAFC] text-[#64748B] shrink-0">{a.tag}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
