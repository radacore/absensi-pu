import KaryawanLayout from '@/Layouts/KaryawanLayout';

export default function Pengumuman() {
    const items = [
        { title: 'Apel pagi Senin — Pusat Makassar', meta: 'Pusat • 24 Agu 2026 • 07:00', desc: 'Apel pukul 07:30 di halaman kantor pusat. Kehadiran tercatat melalui absensi.', pinned: true, unread: true },
        { title: 'Pemeliharaan jalan poros — Gowa', meta: 'Cabang Gowa • 23 Agu 2026', desc: 'Penutupan sementara ruas poros 08:00–16:00. Radius absensi tetap 200 m.', pinned: false, unread: true },
        { title: 'Jadwal pencairan THR', meta: 'Pusat • 20 Agu 2026', desc: 'Pencairan 15 Agustus. Rincian tersedia di slip gaji.', pinned: false, unread: false },
    ];
    return (
        <KaryawanLayout>
            <div className="space-y-5">
                <div>
                    <h2 className="font-semibold text-[17px] tracking-tight text-[#0F172A]">Pengumuman</h2>
                    <p className="text-sm text-[#64748B]">Dari pusat dan cabang Anda</p>
                </div>
                <div className="space-y-3">
                    {items.map((it) => (
                        <div key={it.title} className={`bg-white rounded-2xl p-5 shadow-[0_2px_16px_rgba(15,23,42,0.04)] ${it.unread ? 'ring-1 ring-[#E0F2FE]' : ''}`}>
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="font-medium text-sm leading-snug text-[#0F172A]">{it.pinned ? '📌 ' : ''}{it.title}</h3>
                                {it.unread && <span className="mt-1 w-2 h-2 rounded-full bg-[#1E3A8A] shrink-0"></span>}
                            </div>
                            <p className="text-xs text-[#94A3B8] mt-1">{it.meta} {it.pinned ? '• Disematkan' : ''} {it.unread ? '• Belum dibaca' : '• Sudah dibaca'}</p>
                            <p className="text-sm text-[#475569] leading-relaxed mt-3">{it.desc}</p>
                            <div className="mt-3 flex gap-2">
                                <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${it.meta.includes('Pusat') ? 'bg-[#0F172A] text-white' : 'bg-[#F1F5F9] text-[#334155]'}`}>{it.meta.includes('Pusat') ? 'Pusat Makassar' : 'Cabang'}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </KaryawanLayout>
    );
}
