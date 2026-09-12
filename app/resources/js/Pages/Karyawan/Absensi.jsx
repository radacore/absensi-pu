import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { toast } from '@/lib/toast';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function haversineM(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = (x) => (x * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}

export default function Absensi() {
    const { props } = usePage();
    const me = props.me ?? { id: 1, nama: '—', foto: '', region: '' };
    const assigned = props.assigned ?? null;
    const settings = props.settings ?? { jamMasuk: '07:30', jamPulang: '16:00', toleransi: 15 };
    const history = props.history ?? [];
    const alreadyToday = !!props.alreadyToday;
    const todayISO = props.todayISO ?? new Date().toISOString().slice(0, 10);

    const [captured, setCaptured] = useState(false);
    const [photoPreview, setPhotoPreview] = useState(me.foto || null);
    const [myPos, setMyPos] = useState(null);
    const [geoError, setGeoError] = useState(null);
    const [geoLoading, setGeoLoading] = useState(false);

    useEffect(() => { if (me.foto) setPhotoPreview(me.foto); }, [me.foto]);

    const jarak = useMemo(() => {
        if (!assigned || !myPos) return null;
        return Math.round(haversineM(myPos.lat, myPos.lng, assigned.lat, assigned.lng));
    }, [assigned, myPos]);
    const inRadius = assigned && jarak != null ? jarak <= assigned.radius : false;
    const tanpaTitik = !assigned;

    const nowStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    const requestPos = () => {
        if (!assigned) return;
        if (!navigator.geolocation) { setGeoError('Geolocation tidak didukung — pakai demo 39m'); setMyPos({ lat: assigned.lat + 0.00035, lng: assigned.lng }); return; }
        setGeoLoading(true); setGeoError(null);
        navigator.geolocation.getCurrentPosition(
            (p) => { setMyPos({ lat: p.coords.latitude, lng: p.coords.longitude }); setGeoLoading(false); },
            (err) => { setGeoError(err.message || 'Gagal GPS — pakai demo 39m'); setMyPos({ lat: assigned.lat + 0.00035, lng: assigned.lng }); setGeoLoading(false); },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 },
        );
    };
    const handleOpenCapture = () => { setCaptured(true); requestPos(); };

    const handleKirim = () => {
        if (tanpaTitik) { toast.error('Titik belum di-assign — tidak bisa absen'); return; }
        if (jarak == null) { toast.error(geoLoading ? 'Menunggu GPS...' : 'Lokasi belum siap — aktifkan GPS'); return; }
        if (!inRadius) { toast.error(`${jarak} m / ${assigned.radius} m — di luar radius`); return; }
        if (alreadyToday) { toast.error('Sudah absen hari ini'); return; }
        router.post('/karyawan/absensi/clock-in', {
            lat: myPos.lat,
            lng: myPos.lng,
            selfie_url: photoPreview || null,
        }, {
            preserveScroll: true,
            onSuccess: () => setCaptured(false),
        });
    };

    const handlePulang = () => {
        if (tanpaTitik) { toast.error('Titik belum di-assign'); return; }
        if (!history.some((h) => h.tgl === todayISO && !h.pulang)) { toast.error('Belum absen masuk hari ini'); return; }
        if (jarak == null) {
            if (!captured) { setCaptured(true); requestPos(); }
            toast.error('Aktifkan GPS lalu tekan Absen pulang lagi');
            return;
        }
        if (!inRadius) { toast.error(`${jarak} m / ${assigned.radius} m — di luar radius`); return; }
        router.post('/karyawan/absensi/clock-out', { lat: myPos.lat, lng: myPos.lng }, {
            preserveScroll: true,
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
                    <p className="text-sm text-[#64748B] mt-1">{assigned.regionName} • {assigned.nama_lokasi} • Radius {assigned.radius} m • 1 karyawan = 1 titik</p>
                    <p className="text-xs text-[#94A3B8] mt-1">Valid hanya di titik assigned dalam radius titiknya — di luar / titik lain ditolak 422 • Jam {settings.jamMasuk}–{settings.jamPulang} WITA kelonggaran {settings.toleransi}m</p>
                </div>

                <div className="bg-[#EFF6FF] border border-[#DBEAFE] rounded-2xl p-4">
                    <p className="text-xs font-medium text-[#94A3B8]">Titik assigned kamu</p>
                    <p className="text-sm font-semibold text-[#0F172A] mt-1">{assigned.nama_lokasi}</p>
                    <p className="text-xs font-mono text-[#64748B]">{assigned.lat.toFixed(4)}, {assigned.lng.toFixed(4)} • {assigned.radius} m</p>
                    {assigned.address && <p className="text-xs text-[#94A3B8] mt-1">{assigned.address}</p>}
                </div>

                <div className="bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)]">
                    <div className="rounded-2xl bg-[#F8FAFC] h-[240px] flex flex-col items-center justify-center p-6">
                        {!captured ? (
                            <>
                                <span className="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><path d="M14 4a2 2 0 012 2v1h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2h2V6a2 2 0 012-2h4z" /><circle cx="12" cy="13" r="3.5" /><path d="M16 6h1" /></svg>
                                </span>
                                <p className="text-sm font-medium text-[#0F172A] mt-3">Siap absen</p>
                                <p className="text-xs text-[#64748B] text-center mt-1">Kamera + lokasi untuk pratinjau jarak ke <span className="font-medium text-[#0F172A]">{assigned.nama_lokasi}</span> — hitung haversine ke {assigned.radius} m</p>
                                <button type="button" onClick={handleOpenCapture} className="mt-4 bg-[#0F172A] text-white rounded-xl px-5 py-2.5 text-sm font-semibold">Buka kamera &amp; lokasi</button>
                            </>
                        ) : (
                            <div className="w-full text-center">
                                <div className="mx-auto w-20 h-20 rounded-full bg-white shadow-sm flex items-center justify-center overflow-hidden">
                                    {photoPreview ? <img src={photoPreview} alt="selfie" className="w-full h-full object-cover" /> : <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#334155" strokeWidth="1.6"><circle cx="12" cy="8" r="4" /><path d="M4 20a8 8 0 0116 0" /></svg>}
                                </div>
                                <p className="text-sm font-semibold text-[#0F172A] mt-3">Pratinjau selfie</p>
                                {geoLoading ? (
                                    <p className="text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 bg-[#F1F5F9] text-[#64748B]">Mengambil lokasi GPS...</p>
                                ) : jarak == null ? (
                                    <p className="text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 bg-[#FEF2F2] text-[#991B1B]">Lokasi belum siap</p>
                                ) : (
                                    <p className={`text-xs font-medium inline-block px-2.5 py-1 rounded-full mt-1 ${inRadius ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#FEF2F2] text-[#991B1B]'}`}>{jarak} m / {assigned.radius} m • {inRadius ? 'Dalam radius titik assigned' : 'Di luar radius — ditolak 422'}</p>
                                )}
                                {geoError && <p className="text-xs text-[#92400E] mt-1">{geoError}</p>}
                                {myPos && <p className="text-xs font-mono text-[#94A3B8] mt-1">{myPos.lat.toFixed(6)}, {myPos.lng.toFixed(6)}</p>}
                                <p className="text-xs text-[#94A3B8] mt-1">{nowStr} WITA • {assigned.regionName} • {assigned.nama_lokasi}</p>
                                <div className="flex gap-2 justify-center mt-4">
                                    <button type="button" onClick={() => { setCaptured(false); setMyPos(null); setGeoError(null); }} className="rounded-xl bg-white shadow-sm px-4 py-2 text-sm font-medium text-[#334155]">Ulangi</button>
                                    <button type="button" onClick={requestPos} className="rounded-xl bg-white border border-[#E2E8F0] px-4 py-2 text-sm font-medium text-[#334155]">Refresh GPS</button>
                                    <button type="button" onClick={handleKirim} disabled={jarak == null || !inRadius || alreadyToday} title={jarak == null ? 'Menunggu GPS' : !inRadius ? `${jarak} m / ${assigned.radius} m — di luar radius` : alreadyToday ? 'Sudah absen hari ini' : ''} className={`rounded-xl px-5 py-2 text-sm font-semibold ${jarak != null && inRadius && !alreadyToday ? 'bg-[#0D9488] text-white' : 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed'}`}>Kirim absen masuk</button>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" onClick={handlePulang} className="rounded-xl py-3 text-sm font-medium bg-[#F8FAFC] text-[#334155] hover:bg-[#EFF6FF]">Absen pulang</button>
                        <button type="button" onClick={handleOpenCapture} disabled={captured && jarak != null && !inRadius} title={captured && jarak != null && !inRadius ? `${jarak} m / ${assigned.radius} m — di luar radius` : ''} className={`rounded-xl py-3 text-sm font-semibold ${captured && jarak != null && !inRadius ? 'bg-[#F1F5F9] text-[#94A3B8] cursor-not-allowed' : 'bg-[#0F172A] text-white'}`}>Absen masuk</button>
                    </div>
                    <p className="text-xs text-[#94A3B8] mt-3 text-center">{captured && jarak != null && !inRadius ? `${jarak} m / ${assigned.radius} m — di luar radius ${assigned.nama_lokasi}` : `Absen hanya dapat dilakukan di dalam radius titik penugasan`}</p>
                    {alreadyToday && <p className="text-xs text-center text-[#92400E] mt-2">Sudah absen hari ini ({todayISO}) — lihat riwayat</p>}
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
                        <span className="text-xs text-[#64748B] bg-[#F8FAFC] rounded-full px-3 py-1.5 whitespace-nowrap">{assigned.nama_lokasi}</span>
                    </div>
                    {history.length === 0 ? (
                        <p className="text-sm text-[#94A3B8] text-center py-6">Belum ada absensi</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[440px] text-sm border-collapse">
                                <thead>
                                    <tr className="border-b border-[#E2E8F0]">
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Tanggal</th>
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Jam masuk<span className="normal-case font-normal text-[#94A3B8]"> WITA</span></th>
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Jam keluar<span className="normal-case font-normal text-[#94A3B8]"> WITA</span></th>
                                        <th className="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#F1F5F9]">
                                    {history.map((r) => (
                                        <tr key={`${r.tgl}-${r.datang}-${r.id}`} className={`hover:bg-[#F8FAFC]/50 ${r.tgl === todayISO ? 'bg-[#F0F7FF]' : ''}`}>
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-[#0F172A] whitespace-nowrap">{new Date(r.tgl + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</p>
                                                <p className="text-xs text-[#64748B]">{r.jarak} m {r.tgl === todayISO && <span className="text-[#1E3A8A] font-medium">• Hari ini</span>}</p>
                                            </td>
                                            <td className="px-5 py-3 font-mono text-[#0F172A] whitespace-nowrap tabular-nums">{r.datang}</td>
                                            <td className="px-5 py-3 font-mono whitespace-nowrap tabular-nums">{r.pulang ? <span className="text-[#0F172A]">{r.pulang}</span> : <span className="text-[#CBD5E1]">—</span>}</td>
                                            <td className="px-5 py-3">
                                                <span className={`inline-block text-xs font-medium px-2.5 py-1 rounded-full ${r.status === 'on_time' ? 'bg-[#ECFDF5] text-[#065F46]' : r.status === 'excused_love' ? 'bg-[#FFF7E6] text-[#92400E] border border-[#FCB833]/30' : r.status === 'late' ? 'bg-[#FFFBEB] text-[#92400E]' : 'bg-[#F1F5F9] text-[#334155]'}`}>{r.status === 'on_time' ? 'Tepat waktu' : r.status === 'late' ? 'Terlambat' : r.status === 'excused_love' ? 'Toleransi' : r.status}</span>
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
