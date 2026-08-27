import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useState } from 'react';

const initialLate = [
    { id: 101, tgl: '24 Agu 2026', jam: '07:52', jarak: '12 m', alasan: '', status: 'late' },
];

export default function Love() {
    const [sisa, setSisa] = useState(3);
    const max = 4;
    const [lateList, setLateList] = useState(initialLate);
    const [claims, setClaims] = useState([
        { id: 1, tgl: '12 Agu 2026 • 07:55', alasan: 'Macet poros Gowa', status: 'Disetujui', tone: 'emerald' },
        { id: 2, tgl: '05 Agu 2026 • 07:50', alasan: 'Antar anak sakit', status: 'Ditolak', tone: 'red' },
    ]);
    const [selected, setSelected] = useState(null);
    const [alasan, setAlasan] = useState('');
    const [dokumen, setDokumen] = useState(null);

    const handleClaim = () => {
        if (!selected || !alasan.trim()) return;
        if (sisa <= 0) return;
        const newClaim = {
            id: Date.now(),
            tgl: new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' • ' + selected.jam,
            alasan,
            status: 'Menunggu',
            tone: 'amber',
        };
        setClaims([newClaim, ...claims]);
        setSisa((s) => Math.max(0, s - 1));
        setLateList((list) => list.filter((l) => l.id !== selected.id));
        setSelected(null);
        setAlasan('');
        setDokumen(null);
    };

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Love</h2>
                    <p className="text-sm text-[#64748B]">4 Love/bulan • Reset 1 Sep 00:00 WITA</p>
                </div>

                {/* Balance — no outline */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                            <span className="w-7 h-7 rounded-lg bg-[#FFF7E6] flex items-center justify-center">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB833" stroke="#FCB833" strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
                            </span>
                            Sisa toleransi
                        </h3>
                        <span className="text-xs font-semibold text-[#0F172A] bg-[#FFF7E6] px-3 py-1 rounded-full border border-[#FCB833]/20">{sisa} / {max}</span>
                    </div>
                    <div className="mt-4 flex gap-2">
                        {[1,2,3,4].map((i) => (
                            <span key={i} className={`flex-1 h-2.5 rounded-full ${i <= sisa ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>
                        ))}
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3">Pakai 1 Love untuk 1 keterlambatan <span className="font-medium text-[#0F172A]">dalam radius</span> + dokumen (hari yang sama) → approval 1 level Admin Cabang Gowa</p>
                    {sisa === 0 && <p className="text-xs font-medium text-[#EF4444] mt-2">Sisa 0 — keterlambatan berikutnya tidak bisa di-excuse</p>}
                </div>

                {/* Eligible late — functional */}
                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <h3 className="font-medium text-sm text-[#0F172A]">Terlambat yang bisa pakai Love</h3>
                    <p className="text-xs text-[#94A3B8] mt-1">Hanya late hari ini dalam radius • Di luar radius ditolak</p>
                    <div className="mt-4 space-y-3">
                        {lateList.length === 0 ? (
                            <p className="text-sm text-[#64748B] text-center py-4">Tidak ada keterlambatan yang bisa pakai Love</p>
                        ) : (
                            lateList.map((l) => (
                                <div key={l.id} className={`rounded-xl p-3 flex items-center justify-between ${selected?.id === l.id ? 'bg-[#FFF7E6] border border-[#FCB833]/30' : 'bg-[#F8FAFC]'}`}>
                                    <div>
                                        <p className="text-sm font-medium text-[#0F172A]">{l.tgl} • {l.jam}</p>
                                        <p className="text-xs text-[#64748B]">{l.jarak} dari kantor • dalam radius</p>
                                    </div>
                                    <button type="button" onClick={() => setSelected(l)} className={`rounded-xl px-3 py-2 text-xs font-semibold shrink-0 ${selected?.id === l.id ? 'bg-[#0F172A] text-white' : 'bg-[#FCB833] text-[#0F172A]'}`}>
                                        {selected?.id === l.id ? 'Dipilih' : 'Pilih'}
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                    {selected && (
                        <div className="mt-4 space-y-3 border-t border-[#F1F5F9] pt-4">
                            <p className="text-xs font-medium text-[#0F172A]">Ajukan Love untuk {selected.tgl} • {selected.jam}</p>
                            <div>
                                <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                                <textarea id="alasan" rows={2} value={alasan} onChange={(e) => setAlasan(e.target.value)} placeholder="Contoh: Macet poros Gowa karena perbaikan jalan" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none focus:bg-white focus:ring-2 focus:ring-[#FCB833]/20"></textarea>
                            </div>
                            <div>
                                <label htmlFor="dok" className="text-xs font-medium text-[#334155]">Dokumen pendukung</label>
                                <input id="dok" type="file" onChange={(e) => setDokumen(e.target.files?.[0] || null)} className="mt-1.5 w-full text-xs text-[#64748B] file:mr-3 file:rounded-lg file:border-0 file:bg-[#0F172A] file:text-white file:px-3 file:py-1.5 file:text-xs file:font-medium" />
                                {dokumen && <p className="text-xs text-[#10B981] mt-1">Terpilih: {dokumen.name}</p>}
                            </div>
                            <button type="button" onClick={handleClaim} disabled={!alasan.trim() || sisa <= 0} className="w-full rounded-xl py-3 text-sm font-semibold bg-[#FCB833] text-[#0F172A] disabled:bg-[#F1F5F9] disabled:text-[#94A3B8]">Gunakan 1 Love — Kirim ke Admin Cabang</button>
                            <p className="text-xs text-[#94A3B8] text-center">Butuh approval Admin Cabang Gowa (1 level) • hanya hari yang sama</p>
                        </div>
                    )}
                </div>

                {/* History */}
                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat Love</h3>
                        <span className="text-xs text-[#94A3B8]">Bulan ini</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {claims.map((c) => (
                            <div key={c.id} className="px-5 py-3.5 flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-[#0F172A]">{c.tgl}</p>
                                    <p className="text-xs text-[#64748B] mt-0.5">{c.alasan}</p>
                                </div>
                                <span className={`text-xs font-medium px-2.5 py-1 rounded-full shrink-0 ${c.tone === 'emerald' ? 'bg-[#ECFDF5] text-[#065F46]' : c.tone === 'amber' ? 'bg-[#FFF7E6] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{c.status}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
