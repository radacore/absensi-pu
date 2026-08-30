import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';

export default function Settings() {
    const [jamMasuk, setJamMasuk] = useState('07:30');
    const [jamPulang, setJamPulang] = useState('16:00');
    const [toleransi, setToleransi] = useState(15);
    const [loveMax, setLoveMax] = useState(4);
    const [saved, setSaved] = useState(false);
    return (
        <AdminLayout>
            <div className="space-y-5 max-w-[720px]">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Pengaturan Global</h1>
                    <p className="text-sm text-[#64748B]">Hanya Super Admin Kantor Pusat bisa edit • Admin Wilayah read-only • Berlaku bulan depan untuk Love</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-5">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-lg bg-[#F1F5F9] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        Jam Kerja Global (WITA)
                    </h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="masuk" className="text-xs font-medium text-[#334155]">Jam masuk</label>
                            <input id="masuk" type="time" value={jamMasuk} onChange={(e) => setJamMasuk(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="pulang" className="text-xs font-medium text-[#334155]">Jam pulang</label>
                            <input id="pulang" type="time" value={jamPulang} onChange={(e) => setJamPulang(e.target.value)} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label htmlFor="tol" className="text-xs font-medium text-[#334155]">Toleransi (menit)</label>
                            <input id="tol" type="number" min="0" max="60" value={toleransi} onChange={(e) => setToleransi(Number(e.target.value))} className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm outline-none focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10" />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Hari kerja</label>
                            <div className="mt-1.5 bg-[#F8FAFC] rounded-xl px-3 py-2.5 text-sm text-[#334155]">Senin — Jumat</div>
                        </div>
                    </div>
                    <p className="text-xs text-[#94A3B8]">Timezone Asia/Makassar • Tepat waktu ≤ jam_masuk + toleransi → on_time</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-xl bg-[#FFF7E6] flex items-center justify-center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB833" stroke="#FCB833" strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z"/></svg>
                        </span>
                        Love — Fleksibel per Bulan
                    </h3>
                    <div>
                        <label htmlFor="love" className="text-xs font-medium text-[#334155]">Total Love / bulan (1–10)</label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <input id="love" type="range" min="1" max="10" value={loveMax} onChange={(e) => setLoveMax(Number(e.target.value))} className="flex-1 accent-[#FCB833]" />
                            <span className="w-10 h-10 rounded-xl bg-[#FCB833] text-[#0F172A] flex items-center justify-center font-semibold">{loveMax}</span>
                        </div>
                        <div className="mt-2 flex gap-1.5">
                            {Array.from({ length: 10 }, (_, i) => (<span key={i} className={`flex-1 h-2 rounded-full ${i < loveMax ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                        </div>
                    </div>
                    <p className="text-xs text-[#94A3B8]">Berlaku bulan depan • Reset 1st 00:00 WITA • Saat ini 4 Love untuk semua karyawan (demo)</p>
                </div>

                <button type="button" onClick={() => { setSaved(true); setTimeout(() => setSaved(false), 2000); }} className="w-full bg-[#0F172A] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#1E3A8A] transition">Simpan pengaturan</button>
                {saved && <p className="text-xs text-center text-[#10B981] bg-[#ECFDF5] rounded-xl py-2">Tersimpan (frontend only) — jam & love akan dipakai bulan depan</p>}
            </div>
        </AdminLayout>
    );
}
