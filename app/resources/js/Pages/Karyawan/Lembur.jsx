import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useState } from 'react';

export default function Lembur() {
    const [showForm, setShowForm] = useState(false);
    const list = [
        { tgl: '22 Agu 2026', jam: '17:00–20:00', jenis: 'Lembur', lokasi: 'Kantor BBWS PJ Cab. Gowa', status: 'Disetujui', tone: 'emerald' },
        { tgl: '18 Agu 2026', jam: '08:00–15:00', jenis: 'Dinas Luar', lokasi: 'Jl. Poros Malino — survey', status: 'Menunggu', tone: 'amber' },
        { tgl: '10 Agu 2026', jam: '17:30–19:30', jenis: 'Lembur', lokasi: 'Kantor BBWS PJ Cab. Gowa', status: 'Ditolak', tone: 'red' },
    ];
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Lembur &amp; Dinas Luar</h2>
                        <p className="text-sm text-[#64748B]">Di luar jam 07:30–16:00 &amp; luar radius kantor</p>
                    </div>
                    <span className="bg-[#FCB833] text-[#0F172A] text-xs font-semibold px-3 py-1 rounded-full">Gold</span>
                </div>

                <div className="bg-[#0F172A] rounded-2xl p-5 text-white">
                    <p className="text-xs text-white/60">Total lembur Agustus</p>
                    <p className="text-2xl font-semibold mt-1">6,5 jam</p>
                    <div className="mt-3 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div className="h-full bg-[#FCB833] w-[65%]"></div>
                    </div>
                    <p className="text-xs text-white/60 mt-2">2 disetujui • 1 menunggu • Dinas luar butuh persetujuan Admin Gowa</p>
                </div>

                <button type="button" onClick={() => setShowForm(!showForm)} className="w-full bg-[#FCB833] text-[#0F172A] rounded-xl py-3 text-sm font-semibold">
                    {showForm ? 'Tutup form' : '+ Ajukan lembur / dinas luar'}
                </button>

                {showForm && (
                    <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="jenis" className="text-xs font-medium text-[#334155]">Jenis</label>
                                <select id="jenis" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"><option>Lembur</option><option>Dinas Luar</option></select>
                            </div>
                            <div>
                                <label htmlFor="tgl" className="text-xs font-medium text-[#334155]">Tanggal</label>
                                <input id="tgl" type="date" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="jam1" className="text-xs font-medium text-[#334155]">Jam mulai</label>
                                <input id="jam1" type="time" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                            <div>
                                <label htmlFor="jam2" className="text-xs font-medium text-[#334155]">Jam selesai</label>
                                <input id="jam2" type="time" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none" />
                            </div>
                        </div>
                        <div>
                            <label htmlFor="lok" className="text-xs font-medium text-[#334155]">Lokasi tugas</label>
                            <input id="lok" placeholder="Contoh: Jl. Poros Malino KM 12" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#94A3B8] outline-none" />
                        </div>
                        <div>
                            <label htmlFor="als" className="text-xs font-medium text-[#334155]">Keperluan</label>
                            <textarea id="als" rows={2} placeholder="Uraian tugas lembur/dinas luar" className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none"></textarea>
                        </div>
                        <button type="button" className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold">Kirim pengajuan</button>
                        <p className="text-xs text-[#94A3B8] text-center">Dinas luar: lokasi di luar radius kantor, tetap butuh GPS saat mulai</p>
                    </div>
                )}

                <div className="space-y-3">
                    {list.map((r) => (
                        <div key={`${r.tgl}-${r.jam}`} className="bg-white rounded-2xl p-4 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-sm text-[#0F172A]">{r.jenis} • {r.tgl}</p>
                                    <p className="text-xs text-[#64748B] mt-1">{r.jam} • {r.lokasi}</p>
                                </div>
                                <span className={`text-xs font-medium px-2.5 py-1 rounded-full shrink-0 ${r.tone === 'emerald' ? 'bg-[#ECFDF5] text-[#065F46]' : r.tone === 'amber' ? 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{r.status}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </KaryawanLayout>
    );
}
