import KaryawanLayout from '@/Layouts/KaryawanLayout';
import { usePage } from '@inertiajs/react';

function tanggalPendek(iso) {
    const d = new Date(`${iso}T00:00:00`);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

export default function Jadwal() {
    const { props } = usePage();
    const me = props.me ?? { nama: '—', region: '', kantor: '' };
    const isShift = !!props.isShift;
    const polaRingkasan = props.polaRingkasan ?? null;
    const hariKerjaLabel = props.hariKerjaLabel ?? '';
    const jamKerja = props.jamKerja ?? null;
    const minggu = props.minggu ?? [];
    const rentang = props.rentang ?? { mulai: '', selesai: '' };

    return (
        <KaryawanLayout>
            <div className="px-5 py-5 space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight text-[#0F172A]">Jadwal Kerja</h1>
                    <p className="text-sm text-[#64748B] mt-1">
                        {isShift
                            ? 'Jadwal shift Anda empat minggu ke depan. Perubahan jadwal dilakukan Admin Wilayah.'
                            : 'Hari kerja, jam kerja, dan libur nasional empat minggu ke depan.'}
                    </p>
                    <p className="text-xs text-[#64748B] mt-1">{rentang.mulai} — {rentang.selesai}</p>
                </div>

                <div className="rounded-2xl bg-[#0F172A] text-white px-4 py-3">
                    <p className="text-[10px] tracking-[0.14em] text-white/60">{isShift ? 'POLA SHIFT' : 'JAM KERJA'}</p>
                    <p className="font-semibold text-sm mt-0.5">
                        {isShift
                            ? (polaRingkasan || 'Pola shift belum ditetapkan')
                            : (jamKerja ? `${jamKerja.masuk} — ${jamKerja.pulang}${jamKerja.pulangJumat ? ` · Jumat s.d. ${jamKerja.pulangJumat}` : ''}` : '—')}
                    </p>
                    <p className="text-xs text-white/70 mt-0.5">
                        {isShift ? (me.kantor || me.region) : `Hari kerja: ${hariKerjaLabel || '—'}`}
                    </p>
                </div>

                {minggu.map((pekan) => (
                    <div key={pekan.nomor} className="bg-white rounded-2xl shadow-[0_2px_16px_rgba(15,23,42,0.04)] overflow-hidden">
                        <div className="px-4 py-3 bg-[#F8FAFC] flex items-center justify-between">
                            <p className="text-xs font-medium text-[#64748B]">Minggu {pekan.nomor}</p>
                            <p className="text-xs text-[#64748B]">{tanggalPendek(pekan.mulai)} — {tanggalPendek(pekan.selesai)}</p>
                        </div>
                        <div className="divide-y divide-[#F1F5F9]">
                            {pekan.hari.map((h) => (
                                <div key={h.tanggal} className={`px-4 py-2.5 flex items-start justify-between gap-3 ${h.hariIni ? 'bg-[#EFF6FF]' : ''}`}>
                                    <div>
                                        <p className="text-sm font-medium text-[#0F172A]">
                                            {h.hari}
                                            <span className="text-xs font-normal text-[#64748B]"> • {tanggalPendek(h.tanggal)}</span>
                                            {h.hariIni && <span className="ml-1.5 text-[10px] font-medium bg-white text-[#1E3A8A] px-2 py-0.5 rounded-full">Hari ini</span>}
                                        </p>
                                        {h.liburNasional && (
                                            <p className="text-xs text-[#92400E] mt-0.5">Libur nasional: {h.liburNasional}</p>
                                        )}
                                        {h.catatan && <p className="text-xs text-[#64748B] mt-0.5">{h.catatan}</p>}
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className={`inline-block text-xs font-medium px-2.5 py-1 rounded-full ${h.libur ? 'bg-[#F1F5F9] text-[#64748B]' : 'bg-[#ECFDF5] text-[#065F46]'}`}>
                                            {h.label}
                                        </span>
                                        {!h.libur && h.jamMulai && h.jamSelesai && (
                                            <p className="text-xs text-[#64748B] mt-1">
                                                {h.jamMulai} — {h.jamSelesai}
                                                {h.lewatTengahMalam ? ' (+1 hari)' : ''}
                                            </p>
                                        )}
                                        {h.belumDijadwalkan && (
                                            <p className="text-[10px] text-[#92400E] mt-1">Hubungi Admin Wilayah</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </KaryawanLayout>
    );
}
