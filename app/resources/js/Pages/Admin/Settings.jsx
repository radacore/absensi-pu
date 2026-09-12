import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

export default function Settings() {
    const { url, props } = usePage();
    const base = getBase(url);
    const init = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15, loveMax: 4 };
    const readOnly = !!props.readOnly;
    const [jamMasuk, setJamMasuk] = useState(init.jamMasuk);
    const [jamPulang, setJamPulang] = useState(init.jamPulang);
    const [toleransi, setToleransi] = useState(init.toleransi);
    const [loveMax, setLoveMax] = useState(init.loveMax);

    useEffect(() => {
        setJamMasuk(init.jamMasuk); setJamPulang(init.jamPulang); setToleransi(init.toleransi); setLoveMax(init.loveMax);
    }, [init.jamMasuk, init.jamPulang, init.toleransi, init.loveMax]);

    const handleSave = () => {
        if (readOnly) return;
        if (jamMasuk >= jamPulang) { toast.error('Jam masuk harus lebih awal dari jam pulang'); return; }
        if (toleransi < 0 || toleransi > 60) { toast.error('Kelonggaran harus antara 0–60 menit'); return; }
        if (loveMax < 1 || loveMax > 10) { toast.error('Kuota toleransi harus antara 1–10 per bulan'); return; }
        router.put(`${base}/settings`, { jamMasuk, jamPulang, toleransi, loveMax }, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <div className="space-y-5 max-w-[720px]">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-[#0F172A]">Pengaturan Global</h1>
                    <p className="text-sm text-[#64748B]">Hanya Super Admin Kantor Pusat bisa edit • Admin Wilayah read-only • Berlaku bulan depan untuk Toleransi</p>
                    {readOnly && <span className="inline-block mt-2 text-xs font-medium bg-[#FEF2F2] text-[#991B1B] px-2.5 py-1 rounded-full border">Read-only untuk Admin Wilayah</span>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-5">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-lg bg-[#F1F5F9] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        </span>
                        Jam Kerja Global (WITA)
                    </h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="masuk" className="text-xs font-medium text-[#334155]">Jam masuk {readOnly && <span className="text-[#94A3B8]">— read-only</span>}</label>
                            <input id="masuk" type="time" value={jamMasuk} onChange={(e) => !readOnly && setJamMasuk(e.target.value)} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div>
                            <label htmlFor="pulang" className="text-xs font-medium text-[#334155]">Jam pulang {readOnly && <span className="text-[#94A3B8]">— read-only</span>}</label>
                            <input id="pulang" type="time" value={jamPulang} onChange={(e) => !readOnly && setJamPulang(e.target.value)} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div>
                            <label htmlFor="tol" className="text-xs font-medium text-[#334155]">Kelonggaran (menit) {readOnly && <span className="text-[#94A3B8]">— read-only</span>}</label>
                            <input id="tol" type="number" min="0" max="60" value={toleransi} onChange={(e) => !readOnly && setToleransi(Number(e.target.value))} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-[#334155]">Hari kerja</label>
                            <div className="mt-1.5 bg-[#F8FAFC] rounded-xl px-3 py-2.5 text-sm text-[#334155]">Senin — Jumat</div>
                        </div>
                    </div>
                    <p className="text-xs text-[#94A3B8]">Timezone Asia/Makassar • Tepat waktu ≤ jam_masuk + kelonggaran → on_time</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-xl bg-[#FFF7E6] flex items-center justify-center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB833" stroke="#FCB833" strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z" /></svg>
                        </span>
                        Toleransi — Fleksibel per Bulan
                    </h3>
                    <div>
                        <label htmlFor="love" className="text-xs font-medium text-[#334155]">Total Toleransi / bulan (1–10) {readOnly && <span className="text-[#94A3B8]">— read-only</span>}</label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <input id="love" type="range" min="1" max="10" value={loveMax} onChange={(e) => !readOnly && setLoveMax(Number(e.target.value))} disabled={readOnly} className={`flex-1 accent-[#FCB833] ${readOnly ? 'opacity-40 cursor-not-allowed' : ''}`} />
                            <span className="w-10 h-10 rounded-xl bg-[#FCB833] text-[#0F172A] flex items-center justify-center font-semibold">{loveMax}</span>
                        </div>
                        <div className="mt-2 flex gap-1.5">
                            {Array.from({ length: 10 }, (_, i) => (<span key={i} className={`flex-1 h-2 rounded-full ${i < loveMax ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                        </div>
                    </div>
                    <p className="text-xs text-[#94A3B8]">Berlaku bulan depan • Reset 1st 00:00 WITA • Saat ini {loveMax} Toleransi untuk semua karyawan</p>
                </div>

                <button type="button" onClick={handleSave} disabled={readOnly} className={`w-full rounded-xl py-3 text-sm font-semibold transition ${readOnly ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white hover:bg-[#1E3A8A]'}`}>{readOnly ? 'Read-only — hanya Super Admin bisa simpan' : 'Simpan pengaturan'}</button>
                {readOnly && <p className="text-xs text-center text-[#94A3B8]">Wilayah lihat saja — perubahan hanya di /super-admin/settings oleh Super Admin</p>}
            </div>
        </AdminLayout>
    );
}
