import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

function haversineM(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = (x) => (x * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}

const STATUS_META = {
    on_time: { label: 'Tepat waktu', cls: 'bg-[#ECFDF5] text-[#065F46]' },
    late: { label: 'Terlambat', cls: 'bg-[#FFFBEB] text-[#92400E]' },
    excused_love: { label: 'Toleransi', cls: 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30' },
    early_leave: { label: 'Pulang cepat', cls: 'bg-[#FEF2F2] text-[#991B1B]' },
    libur: { label: 'Libur', cls: 'bg-[#EFF6FF] text-[#1E3A8A]' },
};
const statusMeta = (s) => STATUS_META[s] ?? { label: s, cls: 'bg-[#F1F5F9] text-[#334155]' };

export default function Absensi() {
    const { props } = usePage();
    const me = props.me ?? { id: 1, nama: '—', foto: '', region: '' };
    const assigned = props.assigned ?? null;
    const settings = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15 };
    const history = props.history ?? [];
    const alreadyToday = !!props.alreadyToday;
    const todayISO = props.todayISO ?? new Date().toISOString().slice(0, 10);
    const absenLibur = props.absenLibur ?? { aktif: false, mode: 'tolak', hariIniLibur: false, label: null, ditolak: false };
    const blokirAbsen = !!absenLibur.ditolak && !alreadyToday;

    const [captured, setCaptured] = useState(false);
    const [photoPreview, setPhotoPreview] = useState(me.foto || null);
    const [myPos, setMyPos] = useState(null);
    const [geoError, setGeoError] = useState(null);
    const [geoLoading, setGeoLoading] = useState(false);
    const [stream, setStream] = useState(null);
    const [camError, setCamError] = useState(null);
    const [selfieFile, setSelfieFile] = useState(null);
    const [selfiePreview, setSelfiePreview] = useState(null);
    const [mode, setMode] = useState('masuk'); // 'masuk' | 'pulang'
    const [catatanPulang, setCatatanPulang] = useState('');
    const videoRef = useRef(null);

    useEffect(() => { if (me.foto) setPhotoPreview(me.foto); }, [me.foto]);
    useEffect(() => {
        if (videoRef.current && stream) videoRef.current.srcObject = stream;
    }, [stream, captured]);
    useEffect(() => () => stopStream(), []); // eslint-disable-line react-hooks/exhaustive-deps

    const stopStream = () => {
        setStream((s) => {
            if (s) s.getTracks().forEach((t) => t.stop());
            return null;
        });
    };
    const resetCapture = () => {
        stopStream();
        setCaptured(false); setMyPos(null); setGeoError(null);
        setCamError(null);
        if (selfiePreview) URL.revokeObjectURL(selfiePreview);
        setSelfieFile(null); setSelfiePreview(null);
        setCatatanPulang('');
    };

    const openRow = useMemo(
        () => history.some((h) => h.tgl === todayISO && !h.pulang),
        [history, todayISO],
    );

    const startCamera = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            setCamError('Kamera wajib untuk absen — perangkat/browser ini tidak mendukung kamera');
            return;
        }
        try {
            const s = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } },
                audio: false,
            });
            setCamError(null);
            setStream(s);
        } catch (e) {
            setCamError('Kamera wajib untuk absen — izinkan akses kamera di browser ini');
        }
    };

    const takePhoto = () => {
        const video = videoRef.current;
        if (!video || !stream) { toast.error('Kamera belum siap'); return; }
        const w = video.videoWidth || 720;
        const h = video.videoHeight || 720;
        const scale = Math.min(1, 720 / Math.max(w, h));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(w * scale);
        canvas.height = Math.round(h * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) { toast.error('Gagal mengambil foto'); return; }
            const file = new File([blob], `selfie-${Date.now()}.jpg`, { type: 'image/jpeg' });
            if (selfiePreview) URL.revokeObjectURL(selfiePreview);
            setSelfieFile(file);
            setSelfiePreview(URL.createObjectURL(file));
            stopStream();
        }, 'image/jpeg', 0.82);
    };

    const jarak = useMemo(() => {
        if (!assigned || !myPos) return null;
        return Math.round(haversineM(myPos.lat, myPos.lng, assigned.lat, assigned.lng));
    }, [assigned, myPos]);
    const inRadius = assigned && jarak != null ? jarak <= assigned.radius : false;
    const tanpaTitik = !assigned;

    const requestPos = () => {
        if (!assigned) return;
        if (!navigator.geolocation) { setGeoError('Perangkat tidak mendukung GPS — aktifkan layanan lokasi'); setMyPos(null); return; }
        setGeoLoading(true); setGeoError(null);
        navigator.geolocation.getCurrentPosition(
            (p) => { setMyPos({ lat: p.coords.latitude, lng: p.coords.longitude }); setGeoLoading(false); },
            (err) => { setGeoError(`Gagal membaca GPS (${err.message || 'coba lagi di area terbuka'}) — absen diblokir sampai lokasi asli tersedia`); setMyPos(null); setGeoLoading(false); },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 },
        );
    };
    const openPanel = (next) => {
        setMode(next);
        if (!captured) setCaptured(true);
        requestPos();
        startCamera();
    };
    const handleOpenCapture = () => openPanel('masuk');

    const handleKirim = () => {
        if (tanpaTitik) { toast.error('Titik belum di-assign — tidak bisa absen'); return; }
        if (blokirAbsen) { toast.error(`Absen ditolak — ${absenLibur.label}`); return; }
        if (jarak == null) { toast.error(geoLoading ? 'Menunggu GPS...' : 'Lokasi belum siap — aktifkan GPS'); return; }
        if (!inRadius) { toast.error(`${jarak} m / ${assigned.radius} m — di luar radius`); return; }
        if (alreadyToday) { toast.error('Sudah absen hari ini'); return; }
        if (!selfieFile) { toast.error('Ambil foto selfie dulu — wajib untuk absen masuk'); return; }
        router.post('/karyawan/absensi/clock-in', {
            lat: myPos.lat,
            lng: myPos.lng,
            selfie: selfieFile,
        }, {
            preserveScroll: true,
            onSuccess: () => { resetCapture(); setMode('masuk'); },
        });
    };

    const handlePulang = () => {
        if (tanpaTitik) { toast.error('Titik belum di-assign'); return; }
        if (!openRow) { toast.error('Belum absen masuk hari ini'); return; }
        if (!captured || mode !== 'pulang') {
            openPanel('pulang');
            toast.success('Ambil foto selfie lalu kirim absen pulang');
            return;
        }
        if (jarak == null) { toast.error(geoLoading ? 'Menunggu GPS...' : 'Aktifkan GPS lalu tekan kirim lagi'); return; }
        if (!inRadius) { toast.error(`${jarak} m / ${assigned.radius} m — di luar radius`); return; }
        if (!selfieFile) { toast.error('Ambil foto selfie dulu — wajib untuk absen pulang'); return; }
        if (!catatanPulang.trim()) { toast.error('Isi catatan pekerjaan dulu — wajib saat absen pulang'); return; }
        router.post('/karyawan/absensi/clock-out', {
            lat: myPos.lat,
            lng: myPos.lng,
            selfie_pulang: selfieFile,
            catatan_pulang: catatanPulang.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => { resetCapture(); setMode('masuk'); },
        });
    };

    if (tanpaTitik) {
        return (
            <KaryawanLayout>
                <div className="bg-white rounded-2xl p-6 text-center">
                    <p className="font-medium text-[#0F172A]">Titik belum di-assign</p>
                    <p className="text-sm text-[#64748B] mt-1">Hubungi admin wilayah untuk penugasan titik.</p>
                </div>
            </KaryawanLayout>
        );
    }

    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Absensi</h2>
                    <p className="text-sm text-[#64748B] mt-1">Radius {assigned.radius} m</p>
                </div>

                {absenLibur.hariIniLibur && (
                    <div className={`rounded-2xl p-4 border ${absenLibur.ditolak ? 'bg-[#FEF2F2] border-[#FECACA]' : 'bg-[#FFFBEB] border-[#FDE68A]'}`}>
                        <p className={`text-xs font-medium ${absenLibur.ditolak ? 'text-[#991B1B]' : 'text-[#92400E]'}`}>
                            {absenLibur.ditolak ? 'Absen ditutup hari ini' : 'Di luar hari kerja'}
                        </p>
                        <p className={`text-sm font-semibold mt-1 ${absenLibur.ditolak ? 'text-[#991B1B]' : 'text-[#92400E]'}`}>{absenLibur.label}</p>
                        <p className="text-xs text-[#64748B] mt-1">
                            {absenLibur.ditolak
                                ? 'Absen masuk tidak tersedia di luar hari kerja. Ajukan dispensasi ke Admin Wilayah bila Anda bertugas.'
                                : 'Absen tetap bisa dikirim dan akan dicatat dengan status "Libur" — tidak dihitung sebagai kehadiran hari kerja.'}
                        </p>
                    </div>
                )}

                <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-4">
                    <p className="text-xs font-medium text-[#64748B]">Titik assigned kamu • {assigned.regionName}</p>
                    <p className="text-sm font-semibold text-[#0F172A] mt-1">{assigned.nama_lokasi}</p>
                    <p className="text-xs font-mono text-[#64748B]">{assigned.lat.toFixed(4)}, {assigned.lng.toFixed(4)} • {assigned.radius} m • Jam {settings.jamMasuk}–{settings.jamPulang} WITA</p>
                    {assigned.address && <p className="text-xs text-[#64748B] mt-1">{assigned.address}</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="rounded-2xl bg-[#F8FAFC] p-4">
                        {!captured ? (
                            <div className="flex flex-col items-center justify-center text-center py-8 px-6">
                                <span className="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><path d="M14 4a2 2 0 012 2v1h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2V6a2 2 0 012-2h4z" /><circle cx="12" cy="13" r="3.5" /><path d="M16 6h1" /></svg>
                                </span>
                                <p className="text-sm font-medium text-[#0F172A] mt-3">Siap absen</p>
                                <p className="text-xs text-[#64748B] text-center mt-1">Ambil swafoto + kunci GPS di titik ini</p>
                                <button type="button" onClick={handleOpenCapture} disabled={blokirAbsen} title={blokirAbsen ? `Absen ditolak — ${absenLibur.label}` : ''} className={`mt-4 rounded-xl px-5 py-2.5 text-sm font-semibold ${blokirAbsen ? 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>Buka kamera &amp; lokasi</button>
                            </div>
                        ) : (
                            <div className="w-full text-center">
                                <div className="relative mx-auto w-full aspect-[4/3] max-h-[380px] rounded-2xl bg-white shadow-sm flex items-center justify-center overflow-hidden">
                                    {selfiePreview ? <img src={selfiePreview} alt="selfie" className="w-full h-full object-cover" />
                                        : stream ? <video ref={videoRef} autoPlay playsInline muted className="w-full h-full object-cover" style={{ transform: 'scaleX(-1)' }} />
                                        : photoPreview ? <img src={photoPreview} alt="foto profil" className="w-full h-full object-cover" />
                                        : <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4" /><path d="M4 20a8 8 0 0116 0" /></svg>}
                                    {(stream || selfiePreview) && (
                                        <button
                                            type="button"
                                            onClick={selfiePreview ? () => { URL.revokeObjectURL(selfiePreview); setSelfieFile(null); setSelfiePreview(null); startCamera(); } : takePhoto}
                                            className="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-[#0F172A]/85 px-5 py-2 text-sm font-semibold text-white backdrop-blur"
                                        >
                                            {selfiePreview ? 'Foto ulang' : 'Ambil foto'}
                                        </button>
                                    )}
                                </div>
                                {camError && <p className="text-xs text-[#92400E] mt-2">{camError}</p>}
                                {geoLoading ? (
                                    <p className="text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 bg-[#F1F5F9] text-[#64748B]">Mengambil lokasi GPS...</p>
                                ) : jarak == null ? (
                                    <p className="text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 bg-[#FEF2F2] text-[#991B1B]">Lokasi belum siap</p>
                                ) : (
                                    <p className={`text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 ${inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{jarak} m / {assigned.radius} m • {inRadius ? 'Dalam radius' : 'Di luar radius'}</p>
                                )}
                                {geoError && <p className="text-xs text-[#92400E] mt-1">{geoError}</p>}
                                {mode === 'pulang' && (
                                    <div className="mt-4 text-left">
                                        <label htmlFor="catatan-pulang" className="block text-xs font-medium text-[#334155]">
                                            Catatan pekerjaan hari ini <span className="text-[#991B1B]">*</span>
                                        </label>
                                        <textarea
                                            id="catatan-pulang"
                                            rows={3}
                                            maxLength={500}
                                            value={catatanPulang}
                                            onChange={(e) => setCatatanPulang(e.target.value)}
                                            placeholder="Contoh: menyelesaikan laporan harian, pengecekan jaringan di titik 201"
                                            className="mt-1.5 w-full rounded-xl bg-[#F8FAFC] border-0 px-3 py-2.5 text-sm placeholder:text-[#64748B] outline-none focus:bg-white focus:ring-2 focus:ring-[#0D9488]/20"
                                        />
                                        <p className="mt-1 text-right text-[11px] text-[#64748B]">{catatanPulang.length}/500</p>
                                    </div>
                                )}
                                <div className="flex gap-2 justify-center mt-4">
                                    {mode === 'pulang' ? (
                                        <button
                                            type="button"
                                            onClick={handlePulang}
                                            disabled={jarak == null || !inRadius || !selfieFile || !openRow || !catatanPulang.trim()}
                                            title={jarak == null ? 'Menunggu GPS' : !inRadius ? `${jarak} m / ${assigned.radius} m — di luar radius` : !selfieFile ? 'Ambil foto selfie dulu' : !openRow ? 'Belum absen masuk hari ini' : !catatanPulang.trim() ? 'Isi catatan pekerjaan dulu' : ''}
                                            className={`rounded-xl px-5 py-2 text-sm font-semibold ${jarak != null && inRadius && selfieFile && openRow && catatanPulang.trim() ? 'bg-[#0D9488] text-white' : 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed'}`}
                                        >
                                            Absen Pulang
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={handleKirim}
                                            disabled={jarak == null || !inRadius || !selfieFile || alreadyToday || blokirAbsen}
                                            title={blokirAbsen ? `Absen ditolak — ${absenLibur.label}` : jarak == null ? 'Menunggu GPS' : !inRadius ? `${jarak} m / ${assigned.radius} m — di luar radius` : !selfieFile ? 'Ambil foto selfie dulu' : alreadyToday ? 'Sudah absen hari ini' : ''}
                                            className={`rounded-xl px-5 py-2 text-sm font-semibold ${jarak != null && inRadius && selfieFile && !alreadyToday && !blokirAbsen ? 'bg-[#0D9488] text-white' : 'bg-[#F1F5F9] text-[#64748B] cursor-not-allowed'}`}
                                        >
                                            Absen Masuk
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" onClick={() => openPanel('pulang')} className={`rounded-xl py-3 text-sm font-semibold ${openRow ? 'bg-[#0F172A] text-white' : 'bg-[#F8FAFC] text-[#334155] hover:bg-[#EFF6FF] font-medium'}`}>Absen pulang</button>
                        <button type="button" onClick={() => openPanel('masuk')} className={`rounded-xl py-3 text-sm font-semibold ${!alreadyToday ? 'bg-[#0F172A] text-white' : 'bg-[#F8FAFC] text-[#334155] hover:bg-[#EFF6FF] font-medium'}`}>Absen masuk</button>
                    </div>
                    <p className="text-xs text-[#64748B] mt-3 text-center">Absen tercatat hanya di dalam radius titik penugasan</p>
                </div>

                <div className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                    <div className="px-5 pt-5 pb-4 border-b border-[#F1F5F9] flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <span className="w-8 h-8 rounded-xl bg-[#F1F5F9] flex items-center justify-center">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.8"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4" /><path d="M16 3v4" /><path d="M3 10h18" /></svg>
                            </span>
                            <div>
                                <h3 className="font-semibold text-sm text-[#0F172A] leading-tight">Riwayat Absensi</h3>
                                <p className="text-xs text-[#64748B]">{history.length} entri</p>
                            </div>
                        </div>
                    </div>
                    {history.length === 0 ? (
                        <p className="text-sm text-[#64748B] text-center py-6">Belum ada absensi</p>
                    ) : (
                        <div>
                            <table className="w-full text-sm border-collapse">
                                <thead>
                                    <tr className="border-b border-[#E2E8F0]">
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Tanggal</th>
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Jam<span className="normal-case font-normal text-[#64748B]"> WITA</span></th>
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#F1F5F9]">
                                    {history.map((r) => (
                                        <tr key={`${r.tgl}-${r.datang}-${r.id}`} className={`hover:bg-[#F8FAFC]/50 ${r.tgl === todayISO ? 'bg-[#F0F7FF]' : ''}`}>
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-[#0F172A] whitespace-nowrap">{new Date(r.tgl + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <p className="font-medium text-[#0F172A]"><span className="text-[11px] font-normal text-[#64748B] inline-block w-12">Masuk</span>{r.datang}</p>
                                                <p className="mt-0.5">{r.pulang ? <><span className="text-[11px] font-normal text-[#64748B] inline-block w-12">Pulang</span><span className="font-medium text-[#0F172A]">{r.pulang}</span></> : <span className="text-[#CBD5E1]">— belum pulang</span>}</p>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-block text-xs font-medium px-2.5 py-1 rounded-full ${statusMeta(r.status).cls}`}>{statusMeta(r.status).label}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </KaryawanLayout>
    );
}
