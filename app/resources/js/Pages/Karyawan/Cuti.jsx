import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useConfirm } from '@/Components/ConfirmDialog';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const HARI_KERJA_DEFAULT = ['1', '2', '3', '4', '5'];

/**
 * Hitung hari kerja pada rentang tanggal — cermin dari App\Support\CutiKuota.
 * Dipakai hanya untuk pratinjau di klien; server tetap penentu akhir.
 * `hariKerja` memakai nomor ISO (1 = Senin … 7 = Minggu).
 */
function hitungHariKerja(mulai, selesai, hariKerja, libur) {
    if (!mulai || !selesai || mulai > selesai) return 0;
    const setHari = new Set((hariKerja?.length ? hariKerja : HARI_KERJA_DEFAULT).map(String));
    const setLibur = new Set(libur ?? []);
    let total = 0;
    const d = new Date(`${mulai}T00:00:00`);
    const akhir = new Date(`${selesai}T00:00:00`);
    while (d <= akhir) {
        const iso = d.getDay() === 0 ? 7 : d.getDay();
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        if (setHari.has(String(iso)) && !setLibur.has(key)) total += 1;
        d.setDate(d.getDate() + 1);
    }
    return total;
}

/**
 * Hitung hari pada rentang untuk pekerja shift — cermin dari
 * App\Support\CutiKuota: hanya tanggal yang dijadwalkan shift yang dihitung.
 */
function hitungHariShift(mulai, selesai, jadwalKerja) {
    if (!mulai || !selesai || mulai > selesai) return 0;
    return (jadwalKerja ?? []).filter((t) => t >= mulai && t <= selesai).length;
}

export default function Cuti() {
    const { props } = usePage();
    const me = props.me ?? { nama: '—', region: '' };
    const assigned = props.assigned ?? null;
    const list = props.list ?? [];
    const approvers = props.approvers ?? [];
    const kuota = props.kuota ?? null;
    const jenisBerkuota = kuota?.jenisBerkuota ?? ['Tahunan'];
    const confirm = useConfirm();

    const [showForm, setShowForm] = useState(false);
    const [jenis, setJenis] = useState('Tahunan');
    const [mulai, setMulai] = useState('');
    const [selesai, setSelesai] = useState('');
    const [alasan, setAlasan] = useState('');
    const [approverId, setApproverId] = useState('');

    const dibatasiKuota = jenisBerkuota.includes(jenis);
    const isShift = !!kuota?.isShift;
    const hariKerjaDiminta = dibatasiKuota
        ? (isShift
            ? hitungHariShift(mulai, selesai, kuota?.jadwalKerja)
            : hitungHariKerja(mulai, selesai, kuota?.hariKerja, kuota?.libur))
        : 0;
    const sisaKuota = kuota?.sisa ?? null;

    const handleSubmit = () => {
        if (!mulai || !selesai) { toast.error('Tanggal mulai & selesai wajib'); return; }
        if (mulai > selesai) { toast.error('Tanggal mulai tidak boleh setelah selesai'); return; }
        if (!alasan.trim()) { toast.error('Alasan wajib diisi'); return; }
        if (dibatasiKuota && sisaKuota !== null && hariKerjaDiminta > sisaKuota) {
            toast.error(`Sisa cuti tahunan ${sisaKuota} hari kerja, pengajuan ini ${hariKerjaDiminta} hari kerja`);
            return;
        }
        router.post('/karyawan/cuti', { jenis, mulai, selesai, alasan: alasan.trim(), approver_id: approverId || null }, {
            preserveScroll: true,
            onSuccess: () => { setJenis('Tahunan'); setMulai(''); setSelesai(''); setAlasan(''); setApproverId(''); setShowForm(false); },
        });
    };
    const handleCancel = async (id) => {
        const ok = await confirm({
            title: 'Batalkan pengajuan cuti?',
            description: 'Pengajuan cuti akan dihapus permanen dan tidak dapat dikembalikan.',
            confirmLabel: 'Ya, batalkan',
            cancelLabel: 'Tidak',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/karyawan/cuti/${id}`, { preserveScroll: true });
    };

    const tone = (s) => s === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : s === 'Menunggu' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]';

    if (!assigned) {
        return (<KaryawanLayout><div className="bg-white rounded-2xl p-6 text-center"><p className="text-sm text-[#64748B]">Titik belum di-assign — hubungi Admin</p></div></KaryawanLayout>);
    }

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Cuti</h2>
                        <p className="text-sm text-[#64748B]">Berjenjang 3 tahap • {assigned.site.nama_lokasi} • {assigned.site.radius}m</p>
                        {kuota && (
                            <p className="text-xs text-[#64748B] mt-1">
                                Sisa cuti tahunan {kuota.tahun}:
                                <span className={`ml-1 font-semibold ${kuota.sisa > 0 ? 'text-[#065F46]' : 'text-[#991B1B]'}`}>{kuota.sisa} hari kerja</span>
                                <span className="text-[#64748B]"> / {kuota.max}</span>
                                {kuota.terpakai > 0 && <span> • terpakai {kuota.terpakai} hari kerja (termasuk yang Menunggu)</span>}
                                {isShift && <span className="block mt-0.5">Dihitung dari jadwal shift Anda — hari yang tidak dijadwalkan kerja tidak memotong cuti.</span>}
                            </p>
                        )}
                    </div>
                    <button type="button" onClick={() => setShowForm(!showForm)} className="shrink-0 whitespace-nowrap bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">{showForm ? 'Tutup' : 'Ajukan cuti'}</button>
                </div>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="jenis" className="text-xs font-medium text-[#334155]">Jenis</label>
                                <select id="jenis" value={jenis} onChange={(e)=>setJenis(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white outline-none"><option>Tahunan</option><option>Sakit</option><option>Besar</option><option>Melahirkan</option></select>
                            </div>
                            <div>
                                <label htmlFor="mulai" className="text-xs font-medium text-[#334155]">Mulai</label>
                                <input id="mulai" type="date" value={mulai} onChange={(e)=>setMulai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="selesai" className="text-xs font-medium text-[#334155]">Selesai</label>
                                <input id="selesai" type="date" value={selesai} onChange={(e)=>setSelesai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                        </div>
                        {dibatasiKuota && mulai && selesai && mulai <= selesai && (
                            <p className={`text-xs rounded-xl px-3 py-2 ${sisaKuota !== null && hariKerjaDiminta > sisaKuota ? 'bg-[#FEF2F2] text-[#991B1B]' : 'bg-[#F8FAFC] text-[#475569]'}`}>
                                Pengajuan ini <span className="font-semibold">{hariKerjaDiminta} hari kerja</span>
                                {sisaKuota !== null && <> • sisa kuota {sisaKuota} hari kerja</>}
                                {hariKerjaDiminta === 0 && <>{isShift ? ' • tidak ada jadwal shift pada rentang ini' : ' • tidak ada hari kerja pada rentang ini (akhir pekan/libur nasional)'}</>}
                                {sisaKuota !== null && hariKerjaDiminta > sisaKuota && <> • melebihi sisa kuota</>}
                            </p>
                        )}
                        <div>
                            <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                            <textarea id="alasan" rows={2} value={alasan} onChange={(e)=>setAlasan(e.target.value)} placeholder="Tuliskan alasan cuti" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#64748B] outline-none"></textarea>
                        </div>
                        <div>
                            <label htmlFor="approver" className="text-xs font-medium text-[#334155]">Approver level 1 <span className="text-[#64748B]">— akun admin (opsional)</span></label>
                            <select id="approver" value={approverId} onChange={(e)=>setApproverId(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white outline-none">
                                <option value="">— pilih approver —</option>
                                {approvers.map((a) => (<option key={a.id} value={a.id}>{a.nama}{a.scope ? ` • ${a.scope}` : ''}</option>))}
                            </select>
                            <p className="text-xs text-[#64748B] mt-1">Hanya akun admin yang bisa menyetujui cuti. Level 3 (final) oleh Kantor Pusat.</p>
                        </div>
                        <button type="button" onClick={handleSubmit} className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#1E3A8A] transition">Kirim pengajuan</button>
                    </div>
                )}

                <div className="space-y-3">
                    {list.length === 0 ? (
                        <p className="text-sm text-[#64748B] bg-white rounded-2xl p-6 text-center">Belum ada pengajuan — ajukan cuti pertama</p>
                    ) : list.map((r) => (
                        <div key={r.id} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-sm text-[#0F172A]">{r.jenis} • {r.tgl}</p>
                                    <p className="text-sm text-[#475569] mt-1">{r.alasan}</p>
                                    <p className="text-xs text-[#64748B] mt-1">{r.wilayah} • titik {r.office_location_id}</p>
                                    {(r.approver_nama || r.approved_by_nama) && (
                                        <p className="text-xs text-[#64748B] mt-0.5">
                                            {r.approver_nama && `Approver: ${r.approver_nama}`}
                                            {r.approver_nama && r.approved_by_nama && ' • '}
                                            {r.approved_by_nama && `Diputus oleh: ${r.approved_by_nama}`}
                                        </p>
                                    )}
                                </div>
                                <span className={`shrink-0 text-xs font-medium px-2.5 py-1 rounded-full ${tone(r.status)}`}>{r.status}</span>
                            </div>
                            <div className="mt-4 flex items-center gap-1.5">
                                {['Atasan', 'Admin Wilayah', 'Kantor Pusat'].map((s, i) => (
                                    <div key={s} className="flex items-center gap-1.5">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${i < r.level ? 'bg-[#0F172A] text-white' : i === r.level && r.status === 'Menunggu' ? 'bg-[#FEF3C7] text-[#92400E]' : i === r.level && r.status === 'Disetujui' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F1F5F9] text-[#64748B]'}`}>{s}</span>
                                        {i < 2 && <span className="text-[#CBD5E1]">—</span>}
                                    </div>
                                ))}
                            </div>
                            {r.status === 'Menunggu' && r.level === 0 && (
                                <button type="button" onClick={()=>handleCancel(r.id)} className="mt-3 text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Batalkan pengajuan</button>
                            )}
                            {r.status === 'Ditolak' && r.note && <p className="text-xs text-[#991B1B] bg-[#FEF2F2] rounded-lg px-3 py-1.5 mt-2">Catatan: {r.note}</p>}
                        </div>
                    ))}
                </div>
            </div>
        </KaryawanLayout>
    );
}
