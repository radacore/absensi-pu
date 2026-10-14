import AdminLayout from '@/Layouts/AdminLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function getBase(url) {
    if (url.startsWith('/super-admin')) return '/super-admin';
    if (url.startsWith('/admin')) return '/admin';
    if (url.startsWith('/wilayah')) return '/wilayah';
    return '/admin';
}

const JENIS_CAKUPAN = [
    { value: 'global', label: 'Semua wilayah (bawaan)' },
    { value: 'wilayah', label: 'Per wilayah' },
    { value: 'lokasi', label: 'Per lokasi' },
];

/** Keterangan singkat cakupan yang sedang berlaku. */
const CAKUPAN_AKTIF = {
    bertingkat: 'Pola bisa dibuat untuk semua wilayah, satu wilayah, atau satu lokasi. Yang paling spesifik dipakai.',
    wilayah: 'Pola hanya diatur per wilayah — pola per lokasi diabaikan.',
    global: 'Pola hanya diatur sekali untuk semua wilayah.',
};

/** Jam selesai shift ke-n, dihitung dari jam mulai + panjang shift. */
function jamSelesai(jamMulai, jumlahShift, ke) {
    const [h, m] = String(jamMulai || '07:00').split(':').map((v) => parseInt(v, 10) || 0);
    const panjang = (24 / jumlahShift) * 60;
    const total = (h * 60 + m + (ke - 1) * panjang) % 1440;
    const hh = String(Math.floor(total / 60)).padStart(2, '0');
    const mm = String(total % 60).padStart(2, '0');
    return `${hh}:${mm}`;
}

function labelShift(jumlahShift, ke) {
    const dasar = ['Pagi', 'Siang', 'Sore', 'Malam'];
    if (jumlahShift === 2) return ke === 1 ? 'Pagi' : 'Malam';
    return dasar[ke - 1] || `Shift ${ke}`;
}

export default function PolaShift() {
    const { url, props } = usePage();
    const base = getBase(url);
    const isWilayah = base === '/admin' || base === '/wilayah';
    const patterns = props.patterns ?? [];
    const regions = props.regions ?? [];
    const confirm = useConfirm();

    // Cakupan yang boleh dipakai mengikuti setelan `cakupan_pola_shift` (K5).
    const cakupanDiizinkan = props.cakupanDiizinkan ?? ['global', 'wilayah', 'lokasi'];
    const opsiCakupan = JENIS_CAKUPAN.filter((c) => cakupanDiizinkan.includes(c.value) && (!isWilayah || c.value !== 'global'));
    const cakupanAwal = opsiCakupan[0]?.value ?? (isWilayah ? 'wilayah' : 'global');

    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({
        nama: '',
        jumlahShift: 2,
        jamMulaiShift1: '07:00',
        cakupan: cakupanAwal,
        regionId: regions[0]?.id ?? null,
        siteId: regions[0]?.sites?.[0]?.id ?? null,
    });

    const wilayahTerpilih = useMemo(
        () => regions.find((r) => Number(r.id) === Number(form.regionId)) ?? null,
        [regions, form.regionId],
    );

    const preview = useMemo(() => {
        const n = Number(form.jumlahShift) || 2;
        return Array.from({ length: n }, (_, i) => ({
            ke: i + 1,
            label: labelShift(n, i + 1),
            mulai: jamSelesai(form.jamMulaiShift1, n, i + 1),
            selesai: jamSelesai(form.jamMulaiShift1, n, i + 2 > n ? 1 : i + 2),
        }));
    }, [form.jumlahShift, form.jamMulaiShift1]);

    const bukaTambah = () => {
        setEditing(null);
        setForm({
            nama: '',
            jumlahShift: 2,
            jamMulaiShift1: '07:00',
            cakupan: cakupanAwal,
            regionId: regions[0]?.id ?? null,
            siteId: regions[0]?.sites?.[0]?.id ?? null,
        });
        setOpen(true);
    };

    const bukaEdit = (p) => {
        setEditing(p);
        setForm({
            nama: p.nama,
            jumlahShift: p.jumlahShift,
            jamMulaiShift1: p.jamMulaiShift1,
            cakupan: p.cakupan,
            regionId: p.regionId ?? regions[0]?.id ?? null,
            siteId: p.siteId ?? null,
        });
        setOpen(true);
    };

    const simpan = () => {
        if (!form.nama.trim() || form.nama.trim().length < 2) {
            toast.error('Nama pola minimal 2 karakter');
            return;
        }
        if (!/^\d{2}:\d{2}$/.test(form.jamMulaiShift1)) {
            toast.error('Jam mulai wajib format HH:MM');
            return;
        }
        if (form.cakupan === 'wilayah' && !form.regionId) {
            toast.error('Pilih wilayah dulu');
            return;
        }
        if (form.cakupan === 'lokasi' && !form.siteId) {
            toast.error('Pilih lokasi dulu');
            return;
        }
        const payload = {
            nama: form.nama.trim(),
            jumlahShift: Number(form.jumlahShift),
            jamMulaiShift1: form.jamMulaiShift1,
            cakupan: form.cakupan,
            regionId: form.cakupan === 'wilayah' ? Number(form.regionId) : null,
            siteId: form.cakupan === 'lokasi' ? Number(form.siteId) : null,
        };
        if (editing) {
            router.put(`${base}/pola-shift/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setOpen(false) });
        } else {
            router.post(`${base}/pola-shift`, payload, { preserveScroll: true, onSuccess: () => setOpen(false) });
        }
    };

    const toggleAktif = async (p) => {
        const ok = await confirm({
            title: p.isActive ? `Nonaktifkan pola ${p.nama}?` : `Aktifkan pola ${p.nama}?`,
            description: p.isActive
                ? 'Karyawan pada cakupan ini tidak lagi memakai pola ini sampai diaktifkan kembali.'
                : 'Pola ini akan dipakai untuk absensi karyawan pada cakupannya.',
            confirmLabel: p.isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan',
            tone: p.isActive ? 'danger' : 'warning',
        });
        if (!ok) return;
        router.put(`${base}/pola-shift/${p.id}/toggle`, {}, { preserveScroll: true });
    };

    const hapus = async (p) => {
        const ok = await confirm({
            title: `Hapus pola ${p.nama}?`,
            description: 'Jadwal yang sudah disusun dengan pola ini akan kehilangan acuan jamnya. Data absensi yang sudah tercatat tidak berubah.',
            confirmLabel: 'Ya, hapus',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`${base}/pola-shift/${p.id}`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Pola Shift</h1>
                        <p className="text-sm text-[#64748B]">
                            Panjang shift dihitung otomatis: 24 jam dibagi jumlah shift — 2 shift = 12 jam, 3 shift = 8 jam, 4 shift = 6 jam.
                        </p>
                        <p className="text-xs text-[#64748B] mt-1">
                            {opsiCakupan.length === 0
                                ? 'Cakupan pola sedang dipersempit ke global — pola hanya bisa dibuat Super Admin.'
                                : isWilayah
                                    ? 'Anda menyusun pola untuk wilayah Anda.'
                                    : CAKUPAN_AKTIF[cakupanDiizinkan.length === 1 ? cakupanDiizinkan[0] : 'bertingkat']}
                        </p>
                    </div>
                    <button type="button" onClick={bukaTambah} disabled={opsiCakupan.length === 0} className={`rounded-xl px-4 py-2.5 text-sm font-semibold shrink-0 ${opsiCakupan.length === 0 ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>+ Tambah Pola</button>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-[#F8FAFC] text-xs font-medium text-[#64748B]">
                                <tr>
                                    <th className="text-left px-4 py-3">Pola</th>
                                    <th className="text-left px-4 py-3">Cakupan</th>
                                    <th className="text-left px-4 py-3">Pembagian Shift</th>
                                    <th className="text-left px-4 py-3">Status</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#F1F5F9]">
                                {patterns.map((p) => (
                                    <tr key={p.id} className="hover:bg-[#F8FAFC]/50">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-[#0F172A]">{p.nama}</p>
                                            <p className="text-xs text-[#64748B]">{p.jumlahShift} shift × {p.durasiJam} jam</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <span className="bg-[#EFF6FF] text-[#1E3A8A] px-2 py-1 rounded-full">
                                                {p.cakupan === 'global' ? 'Semua wilayah' : p.cakupan === 'wilayah' ? p.regionNama : `${p.siteNama} • ${p.regionNama}`}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#334155]">
                                            <div className="flex flex-wrap gap-1">
                                                {p.shiftLabels.map((s) => (
                                                    <span key={s.ke} className="bg-[#F1F5F9] px-2 py-1 rounded-lg font-mono text-[11px]">
                                                        {s.label} {s.mulai}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {p.isActive
                                                ? <span className="text-xs bg-[#DCFCE7] text-[#065F46] px-2 py-1 rounded-full">Aktif</span>
                                                : <span className="text-xs bg-[#F1F5F9] text-[#64748B] px-2 py-1 rounded-full">Nonaktif</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex gap-1 justify-end flex-wrap">
                                                <button type="button" onClick={() => toggleAktif(p)} className="text-xs font-medium text-[#92400E] bg-[#FFF7E6] px-3 py-1.5 rounded-lg">
                                                    {p.isActive ? 'Nonaktifkan' : 'Aktifkan'}
                                                </button>
                                                <button type="button" onClick={() => bukaEdit(p)} className="text-xs font-medium text-[#1E3A8A] bg-[#EFF6FF] px-3 py-1.5 rounded-lg">Edit</button>
                                                <button type="button" onClick={() => hapus(p)} className="text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {patterns.length === 0 && (
                        <p className="text-center text-sm text-[#64748B] py-8">
                            Belum ada pola shift. Tambahkan dulu sebelum menyusun jadwal mingguan.
                        </p>
                    )}
                </div>

                {open && (
                    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={() => setOpen(false)}>
                        <div className="bg-white rounded-2xl w-full max-w-[560px] max-h-[90vh] overflow-y-auto shadow-xl" onClick={(e) => e.stopPropagation()}>
                            <div className="sticky top-0 bg-white px-6 py-4 flex items-center justify-between border-b">
                                <h3 className="font-semibold text-[#0F172A]">{editing ? 'Edit Pola Shift' : 'Tambah Pola Shift'}</h3>
                                <button type="button" onClick={() => setOpen(false)} className="w-8 h-8 rounded-full bg-[#F1F5F9] flex items-center justify-center">✕</button>
                            </div>
                            <div className="px-6 py-5 space-y-4">
                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Nama pola</label>
                                    <input
                                        value={form.nama}
                                        onChange={(e) => setForm({ ...form, nama: e.target.value })}
                                        placeholder="Shift Regu Pabrik"
                                        className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Jumlah shift</label>
                                        <select
                                            value={form.jumlahShift}
                                            onChange={(e) => setForm({ ...form, jumlahShift: Number(e.target.value) })}
                                            className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                        >
                                            <option value={2}>2 shift — 12 jam</option>
                                            <option value={3}>3 shift — 8 jam</option>
                                            <option value={4}>4 shift — 6 jam</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-medium text-[#334155]">Jam mulai shift 1</label>
                                        <input
                                            type="time"
                                            value={form.jamMulaiShift1}
                                            onChange={(e) => setForm({ ...form, jamMulaiShift1: e.target.value })}
                                            className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                        />
                                    </div>
                                </div>

                                <div className="rounded-xl bg-[#F8FAFC] px-3 py-3">
                                    <p className="text-xs font-medium text-[#334155] mb-2">Pratinjau pembagian</p>
                                    <div className="space-y-1">
                                        {preview.map((s) => (
                                            <div key={s.ke} className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-[#0F172A]">{s.label}</span>
                                                <span className="font-mono text-[#64748B]">{s.mulai} — {s.selesai}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-[#334155]">Berlaku untuk</label>
                                    <select
                                        value={form.cakupan}
                                        onChange={(e) => setForm({ ...form, cakupan: e.target.value })}
                                        className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                    >
                                        {opsiCakupan.map((c) => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>
                                </div>

                                {form.cakupan !== 'global' && (
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="text-xs font-medium text-[#334155]">Wilayah</label>
                                            <select
                                                value={form.regionId ?? ''}
                                                disabled={isWilayah}
                                                onChange={(e) => {
                                                    const rid = Number(e.target.value);
                                                    const r = regions.find((x) => Number(x.id) === rid);
                                                    setForm({ ...form, regionId: rid, siteId: r?.sites?.[0]?.id ?? null });
                                                }}
                                                className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none disabled:opacity-60"
                                            >
                                                {regions.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                                            </select>
                                        </div>
                                        {form.cakupan === 'lokasi' && (
                                            <div>
                                                <label className="text-xs font-medium text-[#334155]">Lokasi</label>
                                                <select
                                                    value={form.siteId ?? ''}
                                                    onChange={(e) => setForm({ ...form, siteId: Number(e.target.value) })}
                                                    className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"
                                                >
                                                    <option value="" disabled>— Pilih lokasi —</option>
                                                    {(wilayahTerpilih?.sites ?? []).map((s) => <option key={s.id} value={s.id}>{s.nama}</option>)}
                                                </select>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex gap-2 pt-2">
                                    <button type="button" onClick={() => setOpen(false)} className="flex-1 rounded-xl bg-[#F1F5F9] py-3 text-sm font-semibold text-[#64748B]">Batal</button>
                                    <button type="button" onClick={simpan} className="flex-1 rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Simpan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
