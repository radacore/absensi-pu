import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function statusStyle(s) {
    if (s === 'Disetujui') return 'bg-[#DCFCE7] text-[#065F46]';
    if (s === 'Ditolak') return 'bg-[#FEF2F2] text-[#991B1B]';
    return 'bg-[#FFF7E6] text-[#92400E]';
}

function durasi(menit) {
    const jam = Math.floor(menit / 60);
    const sisa = menit % 60;
    if (jam && sisa) return `${jam} jam ${sisa} menit`;
    if (jam) return `${jam} jam`;
    return `${sisa} menit`;
}

export default function Lembur() {
    const { props } = usePage();
    const me = props.me ?? { nama: '—', region: '' };
    const requests = props.requests ?? [];
    const hariIni = props.hariIni ?? new Date().toISOString().slice(0, 10);
    const batasMundur = props.batasMundur ?? hariIni;
    const menitBulanIni = props.menitBulanIni ?? 0;
    const approvers = props.approvers ?? [];

    const [tgl, setTgl] = useState(hariIni);
    const [jamMulai, setJamMulai] = useState('');
    const [jamSelesai, setJamSelesai] = useState('');
    const [keterangan, setKeterangan] = useState('');
    const [approverId, setApproverId] = useState(approvers[0]?.id ?? null);
    const [qApprover, setQApprover] = useState('');
    const [openApprover, setOpenApprover] = useState(false);

    useEffect(() => { if (approvers.length && approverId == null) setApproverId(approvers[0].id); }, [approvers, approverId]);

    const filteredApprovers = useMemo(() => {
        const q = qApprover.trim().toLowerCase();
        if (!q) return approvers;
        return approvers.filter((a) => String(a.nama).toLowerCase().includes(q) || String(a.nip).includes(q));
    }, [approvers, qApprover]);

    const selectedApprover = useMemo(() => approvers.find((a) => a.id === approverId) || null, [approvers, approverId]);

    const kirim = () => {
        if (!tgl) { toast.error('Pilih tanggal lembur'); return; }
        if (tgl > hariIni) { toast.error('Tanggal tidak boleh melebihi hari ini'); return; }
        if (tgl < batasMundur) { toast.error('Tanggal sudah terlalu lama — maksimal 30 hari ke belakang'); return; }
        if (!jamMulai || !jamSelesai) { toast.error('Isi jam mulai dan jam selesai'); return; }
        if (keterangan.trim().length < 5) { toast.error('Isi keterangan pekerjaan minimal 5 karakter'); return; }
        if (!approverId) { toast.error('Pilih atasan untuk persetujuan'); return; }
        router.post('/karyawan/lembur', {
            tanggal: tgl,
            jam_mulai: jamMulai,
            jam_selesai: jamSelesai,
            keterangan: keterangan.trim(),
            approver_id: approverId,
        }, {
            preserveScroll: true,
            onSuccess: () => { setKeterangan(''); setJamMulai(''); setJamSelesai(''); },
        });
    };

    const batal = (r) => {
        router.delete(`/karyawan/lembur/${r.id}`, { preserveScroll: true });
    };

    return (
        <KaryawanLayout>
            <div className="px-5 py-5 space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">Lembur</h1>
                    <p className="text-sm text-[#64748B] mt-1">
                        Lembur diajukan terpisah dari jam kerja biasa. Setelah disetujui Admin Wilayah, menitnya tampil di rekap bulanan sebagai angka tersendiri.
                    </p>
                </div>

                <div className="rounded-2xl bg-[#0F172A] text-white px-4 py-3">
                    <p className="text-[10px] tracking-[0.14em] text-white/60">LEMBUR DISETUJUI BULAN INI</p>
                    <p className="font-semibold text-lg mt-0.5">{durasi(menitBulanIni)}</p>
                    <p className="text-xs text-white/70 mt-0.5">{me.nama} • {me.region}</p>
                </div>

                <div className="bg-white rounded-2xl p-4 space-y-3 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-3">
                            <label className="text-xs font-medium text-[#334155]">Tanggal lembur</label>
                            <input type="date" value={tgl} max={hariIni} min={batasMundur} onChange={(e) => setTgl(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Mulai</label>
                            <input type="time" value={jamMulai} onChange={(e) => setJamMulai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Selesai</label>
                            <input type="time" value={jamSelesai} onChange={(e) => setJamSelesai(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                        </div>
                        <div className="flex items-end">
                            <p className="text-xs text-[#64748B] pb-2">Boleh melewati tengah malam.</p>
                        </div>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-[#334155]">Keterangan pekerjaan</label>
                        <textarea
                            value={keterangan}
                            onChange={(e) => setKeterangan(e.target.value)}
                            rows={3}
                            maxLength={1000}
                            placeholder="Contoh: menyelesaikan rekonsiliasi kas bulanan"
                            className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none resize-none"
                        />
                    </div>

                    <div>
                        <label className="text-xs font-medium text-[#334155]">Atasan (persetujuan)</label>
                        <button type="button" onClick={() => setOpenApprover((v) => !v)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] px-3 py-2.5 text-sm text-left outline-none">
                            {selectedApprover ? `${selectedApprover.nama}${selectedApprover.jabatan ? ' — ' + selectedApprover.jabatan : ''}` : '— Pilih atasan —'}
                        </button>
                        {openApprover && (
                            <div className="mt-2 rounded-xl border border-[#E2E8F0] overflow-hidden">
                                <input
                                    value={qApprover}
                                    onChange={(e) => setQApprover(e.target.value)}
                                    placeholder="Cari nama / NIP"
                                    className="w-full border-0 bg-[#F8FAFC] px-3 py-2 text-sm outline-none"
                                />
                                <div className="max-h-[180px] overflow-y-auto divide-y divide-[#F1F5F9]">
                                    {filteredApprovers.map((a) => (
                                        <button
                                            key={a.id}
                                            type="button"
                                            onClick={() => { setApproverId(a.id); setOpenApprover(false); }}
                                            className="w-full text-left px-3 py-2 text-sm hover:bg-[#F8FAFC]"
                                        >
                                            <span className="block text-[#0F172A]">{a.nama}</span>
                                            <span className="block text-xs text-[#64748B]">{a.jabatan || ''} {a.nip ? `• ${a.nip}` : ''}</span>
                                        </button>
                                    ))}
                                    {filteredApprovers.length === 0 && <p className="px-3 py-3 text-xs text-[#64748B]">Tidak ada atasan yang cocok.</p>}
                                </div>
                            </div>
                        )}
                    </div>

                    <button type="button" onClick={kirim} className="w-full rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Kirim Pengajuan Lembur</button>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <p className="px-4 py-3 text-xs font-medium text-[#64748B] bg-[#F8FAFC]">Riwayat pengajuan</p>
                    <div className="divide-y divide-[#F1F5F9]">
                        {requests.map((r) => (
                            <div key={r.id} className="px-4 py-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-[#0F172A]">{r.tanggal} • {r.jamMulai}—{r.jamSelesai}</p>
                                        <p className="text-xs text-[#64748B] mt-0.5">{r.keterangan}</p>
                                        <p className="text-xs text-[#64748B] mt-0.5">Durasi {durasi(r.menit)} • Atasan: {r.approverNama || '—'}</p>
                                        {r.status === 'Ditolak' && r.note && <p className="text-xs text-[#991B1B] mt-0.5">Ditolak: {r.note}</p>}
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className={`inline-block text-xs px-2 py-1 rounded-full ${statusStyle(r.status)}`}>{r.status}</span>
                                        {r.status === 'Menunggu' && (
                                            <button type="button" onClick={() => batal(r)} className="block mt-2 text-xs font-medium text-[#991B1B] bg-[#FEF2F2] px-3 py-1.5 rounded-lg">Batalkan</button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                        {requests.length === 0 && <p className="text-center text-sm text-[#64748B] py-6">Belum ada pengajuan lembur.</p>}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
