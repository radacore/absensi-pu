import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { useState } from 'react';

export default function Absensi() {
    const [captured, setCaptured] = useState(false);
    const history = [
        { tgl: '24 Agu 2026', jam: '07:38', type: 'Masuk', status: 'Tepat waktu', jarak: '38 m', tone: 'emerald' },
        { tgl: '23 Agu 2026', jam: '16:05', type: 'Pulang', status: 'Tepat waktu', jarak: '21 m', tone: 'emerald' },
        { tgl: '23 Agu 2026', jam: '07:52', type: 'Masuk', status: 'Terlambat', jarak: '12 m', tone: 'amber' },
        { tgl: '22 Agu 2026', jam: '07:31', type: 'Masuk', status: 'Tepat waktu', jarak: '18 m', tone: 'emerald' },
    ];
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Absensi</h2>
                    <p className="text-sm text-[#64748B] mt-1">Kantor Wilayah Gowa • Radius 200 m</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="rounded-2xl bg-[#F8FAFC] h-[240px] flex flex-col items-center justify-center p-6">
                        {!captured ? (
                            <>
                                <span className="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><path d="M14 4a2 2 0 012 2v1h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2V6a2 2 0 012-2h4z"/><circle cx="12" cy="13" r="3.5"/><path d="M16 6h1"/></svg>
                                </span>
                                <p className="text-sm font-medium text-[#0F172A] mt-3">Siap absen</p>
                                <p className="text-xs text-[#64748B] text-center mt-1">Aktifkan kamera dan lokasi untuk pratinjau jarak</p>
                                <button type="button" onClick={() => setCaptured(true)} className="mt-4 bg-[#0F172A] text-white rounded-xl px-5 py-2.5 text-sm font-semibold">Buka kamera &amp; lokasi</button>
                            </>
                        ) : (
                            <div className="w-full text-center">
                                <div className="mx-auto w-20 h-20 rounded-full bg-white shadow-sm flex items-center justify-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/><circle cx="12" cy="8" r="4"/><path d="M8 12h8"/></svg>
                                </div>
                                <p className="text-sm font-semibold text-[#0F172A] mt-3">Pratinjau selfie</p>
                                <p className="text-xs font-medium text-[#065F46] bg-[#ECFDF5] inline-block px-2.5 py-1 rounded-full mt-1">42 m • Dalam radius</p>
                                <p className="text-xs text-[#94A3B8] mt-1">07:42 WITA • Kab. Gowa</p>
                                <div className="flex gap-2 justify-center mt-4">
                                    <button type="button" onClick={() => setCaptured(false)} className="rounded-xl bg-white shadow-sm px-4 py-2 text-sm font-medium text-[#334155]">Ulangi</button>
                                    <button type="button" className="rounded-xl bg-[#0D9488] text-white px-5 py-2 text-sm font-semibold">Kirim absen masuk</button>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" className="rounded-xl bg-[#F8FAFC] py-3 text-sm font-medium text-[#334155]">Absen pulang</button>
                        <button type="button" className="rounded-xl bg-[#0F172A] text-white py-3 text-sm font-semibold">Absen masuk</button>
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3 text-center">Di luar radius 200 m tidak dapat absen — tombol terkunci otomatis</p>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 py-4 flex items-center justify-between">
                        <h3 className="font-medium text-sm text-[#0F172A]">Riwayat</h3>
                        <span className="text-xs text-[#64748B]">7 hari • Love 3/4</span>
                    </div>
                    <div className="divide-y divide-[#F1F5F9]">
                        {history.map((r) => (
                            <div key={`${r.tgl}-${r.jam}`} className="px-5 py-3.5">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-[#0F172A]">{r.tgl} • {r.jam} • {r.type}</p>
                                        <p className="text-xs text-[#64748B]">{r.jarak} dari kantor</p>
                                    </div>
                                    <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${r.tone === 'emerald' ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FFFBEB] text-[#92400E]'}`}>{r.status}</span>
                                </div>
                                {r.status === 'Terlambat' && (
                                    <div className="mt-3 bg-[#FFF7E6] rounded-xl p-3 flex items-center justify-between">
                                        <p className="text-xs text-[#92400E]">Terlambat • bisa pakai 1 Love</p>
                                        <a href="/karyawan/love" className="bg-[#FCB833] text-[#0F172A] rounded-lg px-3 py-1.5 text-xs font-semibold">Gunakan Love</a>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </KaryawanLayout>
    );
}
