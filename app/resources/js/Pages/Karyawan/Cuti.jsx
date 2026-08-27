import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useState } from 'react';

export default function Cuti() {
    const [showForm, setShowForm] = useState(false);
    const list = [
        { id: 1, jenis: 'Tahunan', tgl: '28–30 Agu 2026', alasan: 'Acara keluarga di Makassar', status: 'Menunggu persetujuan', tone: 'amber', step: 2 },
        { id: 2, jenis: 'Sakit', tgl: '20 Agu 2026', alasan: 'Demam — surat dokter terlampir', status: 'Disetujui', tone: 'emerald', step: 3 },
        { id: 3, jenis: 'Besar', tgl: '10–12 Agu 2026', alasan: 'Keperluan ibadah', status: 'Ditolak', tone: 'red', step: 1 },
    ];
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Cuti</h2>
                        <p className="text-sm text-[#64748B]">Pengajuan berjenjang 3 tahap</p>
                    </div>
                    <button type="button" onClick={() => setShowForm(!showForm)} className="bg-[#0F172A] text-white rounded-xl px-4 py-2.5 text-sm font-semibold">{showForm ? 'Tutup' : 'Ajukan cuti'}</button>
                </div>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="jenis" className="text-xs font-medium text-[#334155]">Jenis</label>
                                <select id="jenis" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A]/10 focus:bg-white outline-none"><option>Tahunan</option><option>Sakit</option><option>Besar</option></select>
                            </div>
                            <div>
                                <label htmlFor="mulai" className="text-xs font-medium text-[#334155]">Mulai</label>
                                <input id="mulai" type="date" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="selesai" className="text-xs font-medium text-[#334155]">Selesai</label>
                                <input id="selesai" type="date" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="dok" className="text-xs font-medium text-[#334155]">Dokumen</label>
                                <input id="dok" type="file" className="mt-1.5 w-full text-xs text-[#64748B]" />
                            </div>
                        </div>
                        <div>
                            <label htmlFor="alasan" className="text-xs font-medium text-[#334155]">Alasan</label>
                            <textarea id="alasan" rows={2} placeholder="Tuliskan alasan cuti" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none"></textarea>
                        </div>
                        <button type="button" className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#1E3A8A] transition">Kirim pengajuan</button>
                    </div>
                )}

                <div className="space-y-3">
                    {list.map((r) => (
                        <div key={r.id} className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-sm text-[#0F172A]">{r.jenis} • {r.tgl}</p>
                                    <p className="text-sm text-[#475569] mt-1">{r.alasan}</p>
                                </div>
                                <span className={`shrink-0 text-xs font-medium px-2.5 py-1 rounded-full ${r.tone === 'emerald' ? 'bg-[#ECFDF5] text-[#065F46]' : r.tone === 'amber' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{r.status}</span>
                            </div>
                            <div className="mt-4 flex items-center gap-1.5">
                                {['Atasan', 'Admin Gowa', 'Pusat Makassar'].map((s, i) => (
                                    <div key={s} className="flex items-center gap-1.5">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${i < r.step ? 'bg-[#0F172A] text-white' : i === r.step ? 'bg-[#FEF3C7] text-[#92400E]' : 'bg-[#F1F5F9] text-[#64748B]'}`}>{s}</span>
                                        {i < 2 && <span className="text-[#CBD5E1]">—</span>}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </KaryawanLayout>
    );
}
