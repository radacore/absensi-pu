import AdminLayout from '@/Layouts/AdminLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function getBase(url) { if (url.startsWith('/super-admin')) return '/super-admin'; if (url.startsWith('/admin')) return '/admin'; if (url.startsWith('/wilayah')) return '/wilayah'; return '/admin'; }

const HARI = { '1': 'Senin', '2': 'Selasa', '3': 'Rabu', '4': 'Kamis', '5': 'Jumat', '6': 'Sabtu', '7': 'Minggu' };

export default function Settings() {
    const { url, props } = usePage();
    const base = getBase(url);
    const hariKerjaLabel = (props.settings?.hariKerja ?? ['1', '2', '3', '4', '5']).map((d) => HARI[String(d)] ?? d).join(', ');
    const init = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15, loveMax: 4, cutiTahunanMax: 12, cakupanPolaShift: 'bertingkat', cutiShiftMax: 0, absenLiburAktif: false, absenLiburMode: 'tolak', hariKerja: ['1', '2', '3', '4', '5'] };
    const readOnly = !!props.readOnly;
    const [jamMasuk, setJamMasuk] = useState(init.jamMasuk);
    const [jamPulang, setJamPulang] = useState(init.jamPulang);
    const [toleransi, setToleransi] = useState(init.toleransi);
    const [loveMax, setLoveMax] = useState(init.loveMax);
    const [cutiTahunanMax, setCutiTahunanMax] = useState(init.cutiTahunanMax ?? 12);
    const [cakupanPolaShift, setCakupanPolaShift] = useState(init.cakupanPolaShift ?? 'bertingkat');
    const [cutiShiftMax, setCutiShiftMax] = useState(init.cutiShiftMax ?? 0);
    const [absenLiburAktif, setAbsenLiburAktif] = useState(!!init.absenLiburAktif);
    const [absenLiburMode, setAbsenLiburMode] = useState(init.absenLiburMode ?? 'tolak');
    const [hariKerja, setHariKerja] = useState((init.hariKerja ?? ['1', '2', '3', '4', '5']).map(String));

    useEffect(() => {
        setJamMasuk(init.jamMasuk); setJamPulang(init.jamPulang); setToleransi(init.toleransi); setLoveMax(init.loveMax);
        setCutiTahunanMax(init.cutiTahunanMax ?? 12);
        setCakupanPolaShift(init.cakupanPolaShift ?? 'bertingkat');
        setCutiShiftMax(init.cutiShiftMax ?? 0);
        setAbsenLiburAktif(!!init.absenLiburAktif); setAbsenLiburMode(init.absenLiburMode ?? 'tolak');
        setHariKerja((init.hariKerja ?? ['1', '2', '3', '4', '5']).map(String));
    }, [init.jamMasuk, init.jamPulang, init.toleransi, init.loveMax, init.cutiTahunanMax, init.cakupanPolaShift, init.cutiShiftMax, init.absenLiburAktif, init.absenLiburMode, init.hariKerja]);

    const toggleHari = (d) => {
        if (readOnly) return;
        setHariKerja((prev) => (prev.includes(d) ? prev.filter((x) => x !== d) : [...prev, d]));
    };

    const handleSave = () => {
        if (readOnly) return;
        if (jamMasuk >= jamPulang) { toast.error('Jam masuk harus lebih awal dari jam pulang'); return; }
        if (toleransi < 0 || toleransi > 60) { toast.error('Kelonggaran harus antara 0–60 menit'); return; }
        if (loveMax < 1 || loveMax > 10) { toast.error('Kuota toleransi harus antara 1–10 per bulan'); return; }
        if (cutiTahunanMax < 1 || cutiTahunanMax > 60) { toast.error('Kuota cuti tahunan harus antara 1–60 hari kerja'); return; }
        if (cutiShiftMax < 0 || cutiShiftMax > 60) { toast.error('Jatah cuti pekerja shift harus 0–60 hari kerja'); return; }
        if (hariKerja.length === 0) { toast.error('Pilih minimal satu hari kerja'); return; }
        router.put(`${base}/settings`, { jamMasuk, jamPulang, toleransi, loveMax, cutiTahunanMax, cakupanPolaShift, cutiShiftMax, absenLiburAktif, absenLiburMode, hariKerja }, { preserveScroll: true });
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
                            <label htmlFor="masuk" className="text-xs font-medium text-[#334155]">Jam masuk {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                            <input id="masuk" type="time" value={jamMasuk} onChange={(e) => !readOnly && setJamMasuk(e.target.value)} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div>
                            <label htmlFor="pulang" className="text-xs font-medium text-[#334155]">Jam pulang {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                            <input id="pulang" type="time" value={jamPulang} onChange={(e) => !readOnly && setJamPulang(e.target.value)} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div>
                            <label htmlFor="tol" className="text-xs font-medium text-[#334155]">Kelonggaran (menit) {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                            <input id="tol" type="number" min="0" max="60" value={toleransi} onChange={(e) => !readOnly && setToleransi(Number(e.target.value))} disabled={readOnly} className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`} />
                        </div>
                        <div className="col-span-2">
                            <label className="text-xs font-medium text-[#334155]">Hari kerja {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                            <div className="mt-1.5 flex flex-wrap gap-1.5">
                                {Object.entries(HARI).map(([val, nama]) => {
                                    const aktif = hariKerja.includes(val);
                                    return (
                                        <button
                                            key={val}
                                            type="button"
                                            onClick={() => toggleHari(val)}
                                            disabled={readOnly}
                                            aria-pressed={aktif}
                                            className={`rounded-lg px-3 py-2 text-xs font-medium transition ${aktif ? 'bg-[#0F172A] text-white' : 'bg-[#F8FAFC] text-[#334155] border border-[#E2E8F0] hover:bg-[#F1F5F9]'} ${readOnly ? 'opacity-60 cursor-not-allowed' : ''}`}
                                        >
                                            {nama}
                                        </button>
                                    );
                                })}
                            </div>
                            <p className="mt-2 text-xs text-[#64748B]">Berlaku langsung untuk absensi, jatah cuti, dan rekap presensi. Ini aturan umum — karyawan tertentu boleh dikecualikan lewat menu Karyawan ("Ikut hari kerja umum" dilepas).</p>
                        </div>
                    </div>
                    <p className="text-xs text-[#64748B]">Timezone Asia/Makassar • Tepat waktu ≤ jam_masuk + kelonggaran → on_time</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-xl bg-[#FFF7E6] flex items-center justify-center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB833" stroke="#FCB833" strokeWidth="1.6"><path d="M12 21s-6.5-4.2-8.5-8.5A4.5 4.5 0 0112 5a4.5 4.5 0 018.5 7.5C18.5 16.8 12 21 12 21z" /></svg>
                        </span>
                        Toleransi — Fleksibel per Bulan
                    </h3>
                    <div>
                        <label htmlFor="love" className="text-xs font-medium text-[#334155]">Total Toleransi / bulan (1–10) {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <input id="love" type="range" min="1" max="10" value={loveMax} onChange={(e) => !readOnly && setLoveMax(Number(e.target.value))} disabled={readOnly} className={`flex-1 accent-[#FCB833] ${readOnly ? 'opacity-40 cursor-not-allowed' : ''}`} />
                            <span className="w-10 h-10 rounded-xl bg-[#FCB833] text-[#0F172A] flex items-center justify-center font-semibold">{loveMax}</span>
                        </div>
                        <div className="mt-2 flex gap-1.5">
                            {Array.from({ length: 10 }, (_, i) => (<span key={i} className={`flex-1 h-2 rounded-full ${i < loveMax ? 'bg-[#FCB833]' : 'bg-[#F1F5F9]'}`}></span>))}
                        </div>
                    </div>
                    <p className="text-xs text-[#64748B]">Berlaku bulan depan • Reset 1st 00:00 WITA • Saat ini {loveMax} Toleransi untuk semua karyawan</p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-xl bg-[#ECFDF5] flex items-center justify-center">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#065F46" strokeWidth="1.7"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4" /><path d="M16 3v4" /><path d="M3 10h18" /><path d="M8 14h3" /><path d="M13 14h3" /><path d="M8 17.5h3" /></svg>
                        </span>
                        Kuota Cuti Tahunan
                    </h3>
                    <div>
                        <label htmlFor="cuti" className="text-xs font-medium text-[#334155]">Jatah cuti tahunan (1–60 hari kerja) {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <input id="cuti" type="range" min="1" max="60" value={cutiTahunanMax} onChange={(e) => !readOnly && setCutiTahunanMax(Number(e.target.value))} disabled={readOnly} className={`flex-1 accent-[#0D9488] ${readOnly ? 'opacity-40 cursor-not-allowed' : ''}`} />
                            <span className="w-12 h-10 rounded-xl bg-[#0D9488] text-white flex items-center justify-center font-semibold">{cutiTahunanMax}</span>
                        </div>
                    </div>
                    <p className="text-xs text-[#64748B]">
                        Dihitung dalam <span className="font-medium text-[#334155]">hari kerja</span> per tahun kalender: akhir pekan ({hariKerjaLabel}) dan hari libur nasional tidak dihitung.
                        Hanya jenis <span className="font-medium text-[#334155]">Tahunan</span> yang memotong kuota — Sakit, Besar, dan Melahirkan tidak dibatasi.
                        Pengajuan berstatus <span className="font-medium text-[#334155]">Menunggu</span> sudah memotong kuota; yang Ditolak tidak.
                    </p>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-5">
                    <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                        <span className="w-7 h-7 rounded-xl bg-[#EFF6FF] flex items-center justify-center">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1E3A8A" strokeWidth="1.7"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        </span>
                        Shift Kerja
                    </h3>

                    <div>
                        <label htmlFor="cakupan" className="text-xs font-medium text-[#334155]">Cakupan pola shift {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                        <select
                            id="cakupan"
                            value={cakupanPolaShift}
                            disabled={readOnly}
                            onChange={(e) => !readOnly && setCakupanPolaShift(e.target.value)}
                            className={`mt-1.5 w-full rounded-xl border-0 px-3 py-2.5 text-sm outline-none ${readOnly ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#F8FAFC] focus:bg-white focus:ring-2 focus:ring-[#1E3A8A]/10'}`}
                        >
                            <option value="bertingkat">Bertingkat — lokasi, lalu wilayah, lalu global</option>
                            <option value="wilayah">Per wilayah saja — lokasi diabaikan</option>
                            <option value="global">Global saja — wilayah dan lokasi diabaikan</option>
                        </select>
                        <p className="mt-2 text-xs text-[#64748B]">
                            Menentukan seberapa dalam pola shift dicari untuk tiap pekerja shift. Yang paling spesifik menang.
                            Pilihan ini juga menyaring cakupan yang boleh dibuat di halaman <span className="font-medium text-[#334155]">Pola Shift</span>.
                        </p>
                    </div>

                    <div>
                        <label htmlFor="cutiShift" className="text-xs font-medium text-[#334155]">Jatah cuti pekerja shift (0–60 hari kerja) {readOnly && <span className="text-[#64748B]">— read-only</span>}</label>
                        <div className="mt-1.5 flex items-center gap-3">
                            <input id="cutiShift" type="range" min="0" max="60" value={cutiShiftMax} onChange={(e) => !readOnly && setCutiShiftMax(Number(e.target.value))} disabled={readOnly} className={`flex-1 accent-[#1E3A8A] ${readOnly ? 'opacity-40 cursor-not-allowed' : ''}`} />
                            <span className="w-14 h-10 rounded-xl bg-[#1E3A8A] text-white flex items-center justify-center font-semibold text-sm">{cutiShiftMax === 0 ? 'Umum' : cutiShiftMax}</span>
                        </div>
                        <p className="mt-2 text-xs text-[#64748B]">
                            <span className="font-medium text-[#334155]">0 = ikut kuota umum.</span> Bila diisi, angka ini jadi jatah khusus pekerja shift.
                            Hari cuti pekerja shift dihitung dari <span className="font-medium text-[#334155]">jadwal shift-nya sendiri</span> — hari yang tidak dijadwalkan kerja tidak memotong cuti.
                        </p>
                    </div>
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] space-y-4">
                    <div className="flex items-start justify-between gap-3">
                        <h3 className="font-medium text-sm text-[#0F172A] flex items-center gap-2">
                            <span className="w-7 h-7 rounded-xl bg-[#EFF6FF] flex items-center justify-center">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1E3A8A" strokeWidth="1.7"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4" /><path d="M16 3v4" /><path d="M3 10h18" /><path d="M9 15h6" /></svg>
                            </span>
                            Absen di Akhir Pekan / Hari Libur
                        </h3>
                        <label className="inline-flex items-center gap-2 cursor-pointer shrink-0">
                            <span className="text-xs font-medium text-[#64748B]">{absenLiburAktif ? 'Aktif' : 'Nonaktif'}</span>
                            <input
                                type="checkbox"
                                role="switch"
                                checked={absenLiburAktif}
                                disabled={readOnly}
                                onChange={(e) => !readOnly && setAbsenLiburAktif(e.target.checked)}
                                className={`h-5 w-9 appearance-none rounded-full transition relative cursor-pointer
                                    before:content-[''] before:absolute before:top-0.5 before:left-0.5 before:h-4 before:w-4 before:rounded-full before:bg-white before:transition
                                    checked:before:translate-x-4 ${absenLiburAktif ? 'bg-[#0D9488]' : 'bg-[#CBD5E1]'} ${readOnly ? 'opacity-50 cursor-not-allowed' : ''}`}
                            />
                        </label>
                    </div>

                    <div className={`grid grid-cols-1 gap-2.5 ${!absenLiburAktif ? 'opacity-50 pointer-events-none' : ''}`}>
                        <label className={`flex items-start gap-3 rounded-xl border px-3.5 py-3 cursor-pointer transition ${absenLiburMode === 'tolak' ? 'border-[#0F172A] bg-[#F8FAFC]' : 'border-[#E2E8F0] bg-white'}`}>
                            <input type="radio" name="absenLiburMode" value="tolak" checked={absenLiburMode === 'tolak'} disabled={readOnly || !absenLiburAktif} onChange={() => setAbsenLiburMode('tolak')} className="mt-0.5 accent-[#0F172A]" />
                            <span>
                                <span className="block text-sm font-medium text-[#0F172A]">Tolak absen (422)</span>
                                <span className="block text-xs text-[#64748B] mt-0.5">Karyawan tidak bisa absen di luar hari kerja. Pengajuan dispensasi lewat Admin Wilayah.</span>
                            </span>
                        </label>
                        <label className={`flex items-start gap-3 rounded-xl border px-3.5 py-3 cursor-pointer transition ${absenLiburMode === 'catat' ? 'border-[#0F172A] bg-[#F8FAFC]' : 'border-[#E2E8F0] bg-white'}`}>
                            <input type="radio" name="absenLiburMode" value="catat" checked={absenLiburMode === 'catat'} disabled={readOnly || !absenLiburAktif} onChange={() => setAbsenLiburMode('catat')} className="mt-0.5 accent-[#0F172A]" />
                            <span>
                                <span className="block text-sm font-medium text-[#0F172A]">Tetap dicatat, status khusus <span className="font-mono text-xs bg-[#F1F5F9] px-1.5 py-0.5 rounded">libur</span></span>
                                <span className="block text-xs text-[#64748B] mt-0.5">Absen tetap tersimpan, tetapi tidak dihitung sebagai kehadiran hari kerja.</span>
                            </span>
                        </label>
                    </div>

                    <p className="text-xs text-[#64748B]">
                        Hari non-kerja = di luar hari kerja ({hariKerjaLabel}) atau terdaftar di menu Hari Libur.
                        {!absenLiburAktif && ' Saat nonaktif, absen di luar hari kerja dicatat seperti hari kerja biasa.'}
                    </p>
                </div>

                <button type="button" onClick={handleSave} disabled={readOnly} className={`w-full rounded-xl py-3 text-sm font-semibold transition ${readOnly ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#0F172A] text-white hover:bg-[#1E3A8A]'}`}>{readOnly ? 'Read-only — hanya Super Admin bisa simpan' : 'Simpan pengaturan'}</button>
                {readOnly && <p className="text-xs text-center text-[#64748B]">Wilayah lihat saja — perubahan hanya di /super-admin/settings oleh Super Admin</p>}
            </div>
        </AdminLayout>
    );
}
