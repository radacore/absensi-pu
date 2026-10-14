import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function statusStyle(s) {
    if (s === 'Disetujui') return 'bg-[#DCFCE7] text-[#065F46]';
    if (s === 'Ditolak') return 'bg-[#FEF2F2] text-[#991B1B]';
    return 'bg-[#FFF7E6] text-[#92400E]';
}

export default function PulangAwal() {
    const { props } = usePage();
    const me = props.me ?? { nama: '—', region: '' };
    const requests = props.requests ?? [];
    const shift = props.shift ?? null;
    const isShift = !!props.isShift;
    const hariIni = props.hariIni ?? new Date().toISOString().slice(0, 10);
    const batasMundur = props.batasMundur ?? hariIni;
    const approvers = props.approvers ?? [];

    const [tgl, setTgl] = useState(shift?.tanggal ?? hariIni);
    const [jam, setJam] = useState('');
    const [alasan, setAlasan] = useState('');
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
        if (!tgl) { toast.error('Pilih tanggal'); return; }
        if (tgl > hariIni) { toast.error('Tanggal tidak boleh melebihi hari ini'); return; }
        if (tgl < batasMundur) { toast.error('Tanggal sudah terlalu lama — maksimal 7 hari ke belakang'); return; }
        if (!jam || !/^\d{2}:\d{2}$/.test(jam)) { toast.error('Jam rencana wajib format HH:MM'); return; }
        if (alasan.trim().length < 5) { toast.error('Isi alasan minimal 5 karakter'); return; }
        if (!approverId) { toast.error('Pilih atasan untuk persetujuan'); return; }
        router.post('/karyawan/pulang-cepat', {
            tanggal: tgl, jamRencana: jam, alasan: alasan.trim(), approver_id: approverId,
        }, {
            preserveScroll: true,
            onSuccess: () => { setAlasan(''); setJam(''); },
        });
    };

    const batal = (r) => {
        router.delete(`/karyawan/pulang-cepat/${r.id}`, { preserveScroll: true });
    };

    return (
        <KaryawanLayout>
            <div className="px-5 py-5 space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">Pulang Cepat</h1>
                    <p className="text-sm text-[#64748B] mt-1">
                        {isShift
                            ? 'Absen pulang hanya bisa dilakukan setelah jam shift berakhir. Bila harus pulang lebih awal, ajukan di sini dan tunggu persetujuan Admin Wilayah.'
                            : 'Ajukan izin pulang lebih awal beserta alasannya. Persetujuan diberikan Admin Wilayah.'}
                    </p>
                </div>

                {shift && (
                    <div className="rounded-2xl bg-[#0F172A] text-white px-4 py-3">
                        <p className="text-[10px] tracking-[0.14em] text-white/60">SHIFT BERJALAN</p>
                        <p className="font-semibold text-sm mt-0.5">{shift.label} • {shift.jamMulai} — {shift.jamSelesai}</p>
                        <p className="text-xs text-white/70 mt-0.5">Tanggal kerja {shift.tanggal}</p>
                    </div>
                )}

                <div className="bg-white rounded-2xl p-4 space-y-3 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Tanggal</label>
                            <input type="date" value={tgl} max={hariIni} min={batasMundur} onChange={(e) => setTgl(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Rencana pulang</label>
                            <input type="time" value={jam} onChange={(e) => setJam(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                        </div>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-[#334155]">Alasan</label>
                        <textarea
                            value={alasan}
                            onChange={(e) => setAlasan(e.target.value)}
                            rows={3}
                            placeholder="Contoh: anak sakit, harus ke rumah sakit"
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

                    <button type="button" onClick={kirim} className="w-full rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Kirim Pengajuan</button>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <p className="px-4 py-3 text-xs font-medium text-[#64748B] bg-[#F8FAFC]">Riwayat pengajuan</p>
                    <div className="divide-y divide-[#F1F5F9]">
                        {requests.map((r) => (
                            <div key={r.id} className="px-4 py-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-[#0F172A]">{r.tanggal} • rencana {r.jamRencana}</p>
                                        <p className="text-xs text-[#64748B] mt-0.5">{r.alasan}</p>
                                        <p className="text-xs text-[#64748B] mt-0.5">Atasan: {r.approverNama || '—'}</p>
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
                        {requests.length === 0 && <p className="text-center text-sm text-[#64748B] py-6">Belum ada pengajuan.</p>}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
